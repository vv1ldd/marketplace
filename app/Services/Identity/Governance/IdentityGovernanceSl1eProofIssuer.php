<?php

namespace App\Services\Identity\Governance;

use App\Services\L1IdentityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class IdentityGovernanceSl1eProofIssuer
{
    private const PROOF_TTL_SECONDS = 300;

    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
        private readonly L1IdentityService $identity,
    ) {}

    /**
     * @return array{proof_token: string, redirectUrl: string, proof_response: array<string, mixed>}
     */
    public function issueCompletion(
        Sl1eAuthorizeRequestContext $context,
        string $entityAddress,
        IdentityCredentialMaterial $material,
        string $mode,
    ): array {
        $entityAddress = strtolower($entityAddress);
        $registry = $this->registryProjection($entityAddress);
        $username = $registry->username;
        $keyAddress = $this->keyAddressFromMaterial($material);
        $proofType = $mode === 'register' ? 'sl1e.register.proof.v1' : 'sl1e.login.proof.v1';
        $issuedAt = now()->toIso8601String();
        $expiresAt = now()->addSeconds(self::PROOF_TTL_SECONDS)->toIso8601String();

        $proof = [
            'type' => $proofType,
            'clientId' => $context->clientId,
            'redirectUri' => $context->redirectUri,
            'state' => $context->state,
            'nonce' => $context->nonce,
            'mode' => $mode,
            'entityAddress' => $entityAddress,
            'keyAddress' => $keyAddress,
            'issuedAt' => $issuedAt,
            'expiresAt' => $expiresAt,
        ];

        if (is_string($username) && $username !== '') {
            $proof['username'] = $username;
            $proof['displayName'] = $username;
            $proof['alias'] = '@'.$username;
        }

        $proofToken = 'sl1pt_'.Str::lower(Str::uuid()->toString());
        $proofResponse = [
            'protocol' => 'simple-l1',
            'active' => true,
            'proof_token' => $proofToken,
            'proof' => $proof,
            'identity' => [
                'entity_l1_address' => $entityAddress,
                'key_l1_address' => $keyAddress,
                'username' => $username,
            ],
        ];

        Cache::put($this->proofCacheKey($proofToken), $proofResponse, now()->addSeconds(self::PROOF_TTL_SECONDS));

        return [
            'proof_token' => $proofToken,
            'redirectUrl' => $this->buildRedirectUrl($context, $proofToken),
            'proof_response' => $proofResponse,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function introspect(string $proofToken): array
    {
        $proofToken = trim($proofToken);
        abort_if($proofToken === '', 422, 'proof_token is required.');

        $payload = Cache::get($this->proofCacheKey($proofToken));

        if (! is_array($payload)) {
            abort(404, 'Simple L1 proof could not be verified.');
        }

        return $payload;
    }

    public function buildRedirectUrl(Sl1eAuthorizeRequestContext $context, string $proofToken): string
    {
        $query = http_build_query([
            'state' => $context->state,
            'proof_token' => $proofToken,
        ]);

        $redirectUri = $context->redirectUri;
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri.$separator.$query;
    }

    private function registryProjection(string $streamId): IdentityRegistryProjection
    {
        return IdentityRegistryReducer::fold($this->appender->loadEvents($streamId));
    }

    private function keyAddressFromMaterial(IdentityCredentialMaterial $material): string
    {
        $rawPublicKey = IdentityGovernanceWebAuthnCredentialSourceFactory::decodeStoredBinary($material->publicKey);
        $canonicalKey = 'base64url:'.rtrim(strtr(base64_encode($rawPublicKey), '+/', '-_'), '=');

        return strtolower($this->identity->keyAddressFromPublicKey($canonicalKey));
    }

    private function proofCacheKey(string $proofToken): string
    {
        return 'identity-governance:sl1e-proof:'.$proofToken;
    }
}
