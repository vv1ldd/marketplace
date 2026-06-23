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
        private readonly IdentityGovernanceStreamCredentialLocator $credentialLocator,
        private readonly IdentityGovernanceSl1eProofIssuer $proofIssuer,
        private readonly IdentityGovernanceStreamHandoffService $handoff,
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
     * @return array{flowId: string, options: array<string, mixed>, entityAddress?: string}
     */
    public function issueAuthenticationOptions(Sl1eAuthorizeRequestContext $context, ?string $entityAddressHint = null): array
    {
        $entityAddressHint = strtolower(trim((string) $entityAddressHint));

        if ($entityAddressHint === '') {
            return $this->issueDiscoverableAuthenticationOptions($context);
        }

        return $this->issueHintedAuthenticationOptions($context, $entityAddressHint);
    }

    /**
     * @param  array<string, mixed>  $authenticationResponse
     * @return array<string, mixed>
     */
    public function verifyAuthentication(
        Sl1eAuthorizeRequestContext $context,
        string $flowId,
        array $authenticationResponse,
    ): array {
        $flow = Cache::pull($this->flowCacheKey($flowId));

        if (! is_array($flow)) {
            throw new HttpException(422, 'Authorization flow expired.');
        }

        $streamId = strtolower(trim((string) ($flow['stream_id'] ?? '')));
        $optionsJson = (string) ($flow['options_json'] ?? '');
        $rpId = (string) ($flow['rp_id'] ?? $context->rpId());
        $discoverable = (bool) ($flow['discoverable'] ?? false);

        if ($optionsJson === '') {
            throw new HttpException(422, 'Authorization flow is invalid.');
        }

        $rawId = $this->rawCredentialIdFromAssertion($authenticationResponse);

        if ($streamId === '' && $discoverable) {
            $streamId = (string) ($this->credentialLocator->findStreamIdByRawCredentialId($rawId) ?? '');
        }

        if ($streamId === '') {
            throw new HttpException(422, 'Credential not found in identity stream projection.');
        }

        $projection = $this->credentialProjection($streamId);
        $material = $this->findMaterialByRawCredentialId($projection, $rawId);

        if ($material === null) {
            throw new HttpException(422, 'Credential not found in identity stream projection.');
        }

        $verified = $this->verifier->verify(
            material: $material,
            streamId: $streamId,
            publicKeyCredentialJson: json_encode($authenticationResponse, JSON_THROW_ON_ERROR),
            optionsJson: $optionsJson,
            rpId: $rpId,
        );

        if (! $verified) {
            throw new HttpException(422, 'WebAuthn assertion verification failed.');
        }

        $completion = $this->proofIssuer->issueCompletion($context, $streamId, $material, 'login');

        if ($context->handoffId !== null && $context->handoffToken !== null) {
            $this->handoff->complete(
                handoffId: $context->handoffId,
                handoffToken: $context->handoffToken,
                entityAddress: $streamId,
                redirectUrl: $completion['redirectUrl'],
            );

            return [
                'success' => true,
                'active' => true,
                'entityAddress' => $streamId,
                'factorId' => $material->factorId,
                'handoffCompleted' => true,
            ];
        }

        return [
            'success' => true,
            'active' => true,
            'entityAddress' => $streamId,
            'factorId' => $material->factorId,
            'redirectUrl' => $completion['redirectUrl'],
        ];
    }

    /**
     * @return array{flowId: string, options: array<string, mixed>, entityAddress: string}
     */
    private function issueHintedAuthenticationOptions(Sl1eAuthorizeRequestContext $context, string $streamId): array
    {
        $streamId = strtolower($streamId);
        $projection = $this->credentialProjection($streamId);

        if ($projection->activeCredentials === []) {
            throw new HttpException(422, 'Identity has no replay-ready credentials.');
        }

        $allowCredentials = IdentityGovernanceWebAuthnCredentialSourceFactory::descriptorsFromProjection($projection);
        $rpId = $context->rpId();

        $options = new PublicKeyCredentialRequestOptions(
            challenge: random_bytes(32),
            rpId: $rpId,
            allowCredentials: $allowCredentials,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $optionsJson = Serializer::make()->toJson($options);
        $flowId = (string) Str::uuid();

        Cache::put($this->flowCacheKey($flowId), [
            'stream_id' => $streamId,
            'options_json' => $optionsJson,
            'rp_id' => $rpId,
            'discoverable' => false,
        ], now()->addMinutes(10));

        return [
            'flowId' => $flowId,
            'options' => json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR),
            'entityAddress' => $streamId,
        ];
    }

    /**
     * @return array{flowId: string, options: array<string, mixed>}
     */
    private function issueDiscoverableAuthenticationOptions(Sl1eAuthorizeRequestContext $context): array
    {
        $rpId = $context->rpId();

        $options = new PublicKeyCredentialRequestOptions(
            challenge: random_bytes(32),
            rpId: $rpId,
            allowCredentials: [],
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $optionsJson = Serializer::make()->toJson($options);
        $flowId = (string) Str::uuid();

        Cache::put($this->flowCacheKey($flowId), [
            'stream_id' => '',
            'options_json' => $optionsJson,
            'rp_id' => $rpId,
            'discoverable' => true,
        ], now()->addMinutes(10));

        return [
            'flowId' => $flowId,
            'options' => json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR),
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
