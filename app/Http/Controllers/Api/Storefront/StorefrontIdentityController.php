<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketplaceIdentityResolver;
use App\Services\MeanlyAnalyticsService;
use App\Services\SimpleL1ProtocolClient;
use App\Services\StorefrontTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
            'username' => data_get($proof, 'username') ?: data_get($proof, 'identity.username'),
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

    public function navigationAuthority(Request $request): JsonResponse
    {
        $identity = $request->session()->get('simple_l1_identity');
        $user = app(MarketplaceIdentityResolver::class)->resolveFromRequest($request);

        return response()->json([
            'contract' => [
                'name' => 'storefront-navigation-authority',
                'version' => 'v1',
                'authority' => 'marketplace-commerce',
            ],
            'authenticated' => $user instanceof User,
            'can_access_ops' => $user?->hasOpsSovereignAccess() === true,
            'can_access_partner' => $user instanceof User && (
                $user->isMerchantNode()
                || $user->legalEntities()->exists()
                || $user->managedLegalEntities()->exists()
            ),
            'vault_label' => $this->navigationVaultLabel($user, $identity),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function navigationVaultLabel(?User $user, mixed $identity): ?string
    {
        if ($user instanceof User) {
            $username = $user->publicUsername();
            if ($username !== null) {
                return $username;
            }
        }

        if (! is_array($identity)) {
            return null;
        }

        $username = trim((string) (data_get($identity, 'username') ?: ''));
        if ($username !== '') {
            return str_starts_with($username, '@') ? $username : '@'.$username;
        }

        $alias = trim((string) (
            data_get($identity, 'display_alias')
            ?: data_get($identity, 'alias')
            ?: ''
        ));

        return $alias !== '' ? $alias : null;
    }

    public function checkUsername(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => 'required|string|max:64',
        ]);

        $username = User::normalizeUsername($data['username']);

        if ($username === null) {
            return response()->json([
                'available' => false,
                'username' => null,
                'reason' => 'invalid',
                'message' => 'Username must be 3–32 characters: letters, numbers, dots, underscores.',
            ]);
        }

        if (User::where('username_key', $username)->exists()) {
            return response()->json([
                'available' => false,
                'username' => $username,
                'reason' => 'taken',
                'message' => 'This username is already taken.',
            ]);
        }

        return response()->json([
            'available' => true,
            'username' => $username,
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
        if ($proofHash === '' && (! is_string($proofToken) || $proofToken === '')) {
            app(MeanlyAnalyticsService::class)->track('handoff.expired', [
                'reason' => 'proof_handle_expired',
                'entity_l1_address_hash' => hash('sha256', strtolower($entityAddress)),
            ], [
                'event_type' => 'identity_connect',
                'surface' => 'storefront',
                'severity' => 'warning',
            ]);
            abort(410, 'Simple L1 session handoff expired.');
        }

        $issued = $tokens->issue([
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => data_get($identity, 'key_l1_address'),
            'username' => data_get($identity, 'username'),
            'alias' => data_get($identity, 'alias'),
            'display_alias' => data_get($identity, 'display_alias'),
            'proof_token_hash' => $proofHash !== '' ? $proofHash : hash('sha256', (string) $proofToken),
        ], $this->allowedScopes($data['scopes'] ?? [
            'storefront:read',
            'storefront:checkout',
            'storefront:vault',
            'storefront:partner-registration',
        ]));

        $connectedAtRaw = (string) data_get($identity, 'connected_at', '');
        if ($connectedAtRaw !== '') {
            try {
                $connectedAt = \Illuminate\Support\Carbon::parse($connectedAtRaw);
                if ($connectedAt->lt(now()->subMinutes(10))) {
                    app(MeanlyAnalyticsService::class)->track('handoff.expired', [
                        'reason' => 'handoff_claim_delayed',
                        'entity_l1_address_hash' => hash('sha256', strtolower($entityAddress)),
                        'connected_at' => $connectedAt->toIso8601String(),
                    ], [
                        'event_type' => 'identity_connect',
                        'surface' => 'storefront',
                        'severity' => 'warning',
                    ]);
                }
            } catch (\Throwable) {
                // Invalid connected_at should not block token issuance.
            }
        }

        app(MeanlyAnalyticsService::class)->track('identity.connect.handoff_claimed', [
            'entity_l1_address_hash' => hash('sha256', strtolower($entityAddress)),
            'scope_count' => count((array) data_get($issued, 'session.scopes', [])),
        ], [
            'event_type' => 'identity_connect',
            'surface' => 'storefront',
        ]);

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
