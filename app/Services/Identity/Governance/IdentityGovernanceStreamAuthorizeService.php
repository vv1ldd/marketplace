<?php

namespace App\Services\Identity\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\LaravelPasskeys\Support\Serializer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialRequestOptions;

final class IdentityGovernanceStreamAuthorizeService
{
    public function __construct(
        private readonly IdentityGovernanceStreamAppender $appender,
        private readonly IdentityGovernanceStreamAssertionVerifier $verifier,
    ) {}

    public function credentialProjection(string $streamId): IdentityCredentialProjection
    {
        $streamId = strtolower($streamId);

        if ($this->appender->headVersion($streamId) === 0) {
            throw new HttpException(404, 'Identity stream not found.');
        }

        return IdentityCredentialReducer::fold($this->appender->loadEvents($streamId));
    }

    /**
     * @return array{flowId: string, options: array<string, mixed>, entityAddress: string}
     */
    public function issueAuthenticationOptions(string $streamId, ?string $entityAddressHint = null): array
    {
        $streamId = strtolower($streamId);

        if ($entityAddressHint !== null && $entityAddressHint !== '' && ! hash_equals($streamId, strtolower($entityAddressHint))) {
            throw new HttpException(404, 'Identity not found.');
        }

        $projection = $this->credentialProjection($streamId);

        if ($projection->activeCredentials === []) {
            throw new HttpException(422, 'Identity has no replay-ready credentials.');
        }

        $allowCredentials = IdentityGovernanceWebAuthnCredentialSourceFactory::descriptorsFromProjection($projection);

        $options = new PublicKeyCredentialRequestOptions(
            challenge: random_bytes(32),
            rpId: IdentityGovernanceWebAuthnCredentialSourceFactory::rpId(),
            allowCredentials: $allowCredentials,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $optionsJson = Serializer::make()->toJson($options);
        $flowId = (string) Str::uuid();

        Cache::put($this->flowCacheKey($flowId), [
            'stream_id' => $streamId,
            'options_json' => $optionsJson,
        ], now()->addMinutes(10));

        return [
            'flowId' => $flowId,
            'options' => json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR),
            'entityAddress' => $streamId,
        ];
    }

    /**
     * @param  array<string, mixed>  $authenticationResponse
     * @return array{entityAddress: string, factorId: string}
     */
    public function verifyAuthentication(string $flowId, array $authenticationResponse): array
    {
        $flow = Cache::pull($this->flowCacheKey($flowId));

        if (! is_array($flow)) {
            throw new HttpException(422, 'Authorization flow expired.');
        }

        $streamId = (string) ($flow['stream_id'] ?? '');
        $optionsJson = (string) ($flow['options_json'] ?? '');

        if ($streamId === '' || $optionsJson === '') {
            throw new HttpException(422, 'Authorization flow is invalid.');
        }

        $projection = $this->credentialProjection($streamId);
        $rawId = $this->rawCredentialIdFromAssertion($authenticationResponse);
        $material = $this->findMaterialByRawCredentialId($projection, $rawId);

        if ($material === null) {
            throw new HttpException(422, 'Credential not found in identity stream projection.');
        }

        $verified = $this->verifier->verify(
            material: $material,
            streamId: $streamId,
            publicKeyCredentialJson: json_encode($authenticationResponse, JSON_THROW_ON_ERROR),
            optionsJson: $optionsJson,
        );

        if (! $verified) {
            throw new HttpException(422, 'WebAuthn assertion verification failed.');
        }

        return [
            'entityAddress' => $streamId,
            'factorId' => $material->factorId,
        ];
    }

    /**
     * @param  array<string, mixed>  $authenticationResponse
     */
    private function rawCredentialIdFromAssertion(array $authenticationResponse): string
    {
        $rawId = (string) ($authenticationResponse['rawId'] ?? $authenticationResponse['id'] ?? '');

        if ($rawId === '') {
            return '';
        }

        $decoded = base64_decode(strtr($rawId, '-_', '+/').str_repeat('=', (4 - strlen($rawId) % 4) % 4), true);

        return $decoded !== false ? $decoded : $rawId;
    }

    private function findMaterialByRawCredentialId(
        IdentityCredentialProjection $projection,
        string $rawCredentialId,
    ): ?IdentityCredentialMaterial {
        foreach ($projection->activeCredentials as $material) {
            $storedRaw = IdentityGovernanceWebAuthnCredentialSourceFactory::decodeStoredBinary($material->credentialId);

            if (hash_equals($storedRaw, $rawCredentialId)) {
                return $material;
            }
        }

        return null;
    }

    private function flowCacheKey(string $flowId): string
    {
        return 'identity-governance:authorize-flow:'.$flowId;
    }
}
