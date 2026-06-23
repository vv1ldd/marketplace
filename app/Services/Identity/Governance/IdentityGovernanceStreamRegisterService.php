<?php

namespace App\Services\Identity\Governance;

use App\Models\User;
use App\Services\L1IdentityService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

final class IdentityGovernanceStreamRegisterService
{
    public function __construct(
        private readonly IdentityGovernanceVaultStreamProducer $producer,
        private readonly IdentityGovernanceStreamAttestationVerifier $attestationVerifier,
        private readonly IdentityGovernanceSl1eProofIssuer $proofIssuer,
        private readonly IdentityGovernanceStreamHandoffService $handoff,
        private readonly L1IdentityService $identity,
    ) {}

    /**
     * @return array{success: true, flowId: string, entityAddress: string, options: array<string, mixed>, username?: string}
     */
    public function issueRegistrationOptions(Sl1eAuthorizeRequestContext $context): array
    {
        $username = $this->resolveUsername($context);
        $entityAddress = strtolower($this->identity->newEntityAddress());
        $rpId = $context->rpId();
        $clientName = $context->clientName !== '' ? $context->clientName : (string) config('simple_l1.client_name', 'Meanly');
        $safeTitle = trim((string) config('simple_l1.client_safe_title', 'Digital Safe'));

        if ($context->username !== null) {
            $handle = '@'.$username;
            $userName = $handle;
            $displayName = $clientName.' · '.$handle;
        } else {
            $userName = $safeTitle;
            $displayName = $clientName.' · '.$safeTitle;
        }

        $options = new PublicKeyCredentialCreationOptions(
            rp: new PublicKeyCredentialRpEntity(
                name: Config::getRelyingPartyName(),
                id: $rpId,
                icon: Config::getRelyingPartyIcon(),
            ),
            user: new PublicKeyCredentialUserEntity(
                name: $userName,
                id: $entityAddress,
                displayName: $displayName,
            ),
            challenge: random_bytes(32),
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                null,
                AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        );

        $optionsJson = Serializer::make()->toJson($options);
        $flowId = (string) Str::uuid();

        Cache::put($this->flowCacheKey($flowId), [
            'stream_id' => $entityAddress,
            'username' => $username,
            'options_json' => $optionsJson,
            'rp_id' => $rpId,
            'authorize' => $this->serializeContext($context),
        ], now()->addMinutes(10));

        $payload = [
            'success' => true,
            'flowId' => $flowId,
            'entityAddress' => $entityAddress,
            'options' => json_decode($optionsJson, true, 512, JSON_THROW_ON_ERROR),
        ];

        if ($context->username !== null) {
            $payload['username'] = $username;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $attestationResponse
     * @return array<string, mixed>
     */
    public function verifyRegistration(
        Sl1eAuthorizeRequestContext $context,
        string $flowId,
        array $attestationResponse,
    ): array {
        $flow = Cache::pull($this->flowCacheKey($flowId));

        if (! is_array($flow)) {
            throw new HttpException(422, 'Authorization flow expired.');
        }

        $streamId = strtolower((string) ($flow['stream_id'] ?? ''));
        $optionsJson = (string) ($flow['options_json'] ?? '');
        $username = (string) ($flow['username'] ?? '');
        $rpId = (string) ($flow['rp_id'] ?? $context->rpId());

        if ($streamId === '' || $optionsJson === '') {
            throw new HttpException(422, 'Authorization flow is invalid.');
        }

        $verified = $this->attestationVerifier->verify(
            publicKeyCredentialJson: json_encode($attestationResponse, JSON_THROW_ON_ERROR),
            optionsJson: $optionsJson,
            rpId: $rpId,
        );

        $factorId = IdentityGovernanceVaultStreamProducer::deterministicFactorId(
            'passkey',
            (string) ($verified['webauthn']['credential_id'] ?? $flowId),
        );

        $this->producer->recordVaultCreation(
            streamId: $streamId,
            creationId: 'vault-create:authorize:'.$flowId,
            username: $username,
            credentialPayload: IdentityGovernanceWebAuthnPayload::boundPayload(
                factorId: $factorId,
                webauthn: $verified['webauthn'],
                extra: [
                    'metadata' => [
                        'public_reference' => strtolower($this->identity->keyAddressFromPublicKey(
                            'base64url:'.rtrim(strtr(base64_encode($verified['public_key_source']->credentialPublicKey), '+/', '-_'), '='),
                        )),
                        'label' => $username !== '' ? '@'.$username : $streamId,
                    ],
                ],
            ),
        );

        $material = IdentityCredentialMaterial::fromBoundPayload(
            IdentityGovernanceWebAuthnPayload::boundPayload($factorId, $verified['webauthn']),
        );

        abort_if($material === null, 500, 'Could not materialize registered credential.');

        $completion = $this->proofIssuer->issueCompletion($context, $streamId, $material, 'register');

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

    private function resolveUsername(Sl1eAuthorizeRequestContext $context): string
    {
        if ($context->username !== null) {
            abort_if(
                User::query()->where('username_key', $context->username)->exists(),
                422,
                'Username is already taken.',
            );

            return $context->username;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = User::normalizeUsername('safe_'.bin2hex(random_bytes(4)));
            if ($candidate !== null && ! User::query()->where('username_key', $candidate)->exists()) {
                return $candidate;
            }
        }

        $fallback = User::normalizeUsername('safe_'.dechex(random_int(0, 0xffffff)));

        return $fallback ?? 'safe_'.bin2hex(random_bytes(3));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeContext(Sl1eAuthorizeRequestContext $context): array
    {
        return [
            'client_id' => $context->clientId,
            'redirect_uri' => $context->redirectUri,
            'state' => $context->state,
            'nonce' => $context->nonce,
            'mode' => $context->mode,
        ];
    }

    private function flowCacheKey(string $flowId): string
    {
        return 'identity-governance:register-flow:'.$flowId;
    }
}
