<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Services\SimpleL1ProtocolClient;
use App\Services\StorefrontTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontIdentityController extends Controller
{
    public function exchange(Request $request, SimpleL1ProtocolClient $simpleL1, StorefrontTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'proof_token' => 'required|string|min:8|max:4096',
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:80',
        ]);

        $proofToken = (string) $data['proof_token'];
        $proof = $simpleL1->introspectProof($proofToken);

        abort_unless((bool) data_get($proof, 'active'), 422, 'Simple L1 proof is not active.');

        $entityAddress = (string) (
            data_get($proof, 'entity_l1_address')
            ?: data_get($proof, 'l1_address')
            ?: data_get($proof, 'identity.entity_l1_address')
            ?: data_get($proof, 'sub')
        );
        abort_if($entityAddress === '', 422, 'Simple L1 proof does not include an entity address.');

        $issued = $tokens->issue([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => data_get($proof, 'key_l1_address') ?: data_get($proof, 'identity.key_l1_address'),
            'alias' => data_get($proof, 'alias') ?: data_get($proof, 'identity.alias'),
            'display_alias' => data_get($proof, 'display_alias') ?: data_get($proof, 'identity.display_alias'),
            'proof_token_hash' => hash('sha256', $proofToken),
        ], $this->allowedScopes($data['scopes'] ?? null));

        return response()->json([
            'contract' => [
                'name' => 'storefront-token-exchange',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'identity_authority' => 'simple-l1',
            ],
            'token_type' => $issued['token_type'],
            'access_token' => $issued['access_token'],
            'expires_in' => $issued['expires_in'],
            'session' => $issued['session'],
        ]);
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json([
            'contract' => [
                'name' => 'storefront-session',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
            ],
            'authenticated' => true,
            'session' => $request->attributes->get('storefront_token_session'),
        ]);
    }

    public function handoff(Request $request, StorefrontTokenService $tokens): JsonResponse
    {
        $data = $request->validate([
            'scopes' => 'nullable|array',
            'scopes.*' => 'string|max:80',
        ]);
        $identity = $request->session()->get('simple_l1_identity');

        abort_unless(is_array($identity), 401, 'Simple L1 session handoff is not available.');

        $entityAddress = (string) (
            data_get($identity, 'entity_l1_address')
            ?: data_get($identity, 'l1_address')
        );
        $proofHandle = (string) data_get($identity, 'proof_handle');
        $proofHash = (string) data_get($identity, 'proof_token_hash');
        abort_if($entityAddress === '' || ($proofHandle === '' && $proofHash === ''), 401, 'Simple L1 session handoff is incomplete.');

        $proofToken = $proofHandle !== '' ? Cache::pull($this->proofTokenCacheKey($proofHandle)) : null;
        abort_if($proofHash === '' && (! is_string($proofToken) || $proofToken === ''), 410, 'Simple L1 session handoff expired.');

        $issued = $tokens->issue([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => data_get($identity, 'key_l1_address'),
            'alias' => data_get($identity, 'alias'),
            'display_alias' => data_get($identity, 'display_alias'),
            'proof_token_hash' => $proofHash !== '' ? $proofHash : hash('sha256', (string) $proofToken),
        ], $this->allowedScopes($data['scopes'] ?? [
            'storefront:read',
            'storefront:checkout',
            'storefront:vault',
            'storefront:partner-registration',
        ]));

        return response()->json([
            'contract' => [
                'name' => 'storefront-session-handoff',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
                'identity_authority' => 'simple-l1',
                'handoff' => 'laravel-session-to-storefront-token',
            ],
            'token_type' => $issued['token_type'],
            'access_token' => $issued['access_token'],
            'expires_in' => $issued['expires_in'],
            'session' => $issued['session'],
        ]);
    }

    /**
     * @param  array<int, string>|null  $requestedScopes
     * @return array<int, string>
     */
    private function allowedScopes(?array $requestedScopes): array
    {
        $allowed = ['storefront:read', 'storefront:checkout', 'storefront:vault', 'storefront:partner-registration'];
        $requestedScopes = $requestedScopes ?: ['storefront:read'];

        return collect($requestedScopes)
            ->filter(fn (string $scope): bool => in_array($scope, $allowed, true))
            ->values()
            ->all() ?: ['storefront:read'];
    }

    private function proofTokenCacheKey(string $handle): string
    {
        return 'simple_l1:proof_token:'.$handle;
    }
}
