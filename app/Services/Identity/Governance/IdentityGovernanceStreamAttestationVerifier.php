<?php

namespace App\Services\Identity\Governance;

use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;

final class IdentityGovernanceStreamAttestationVerifier
{
    /**
     * @return array{webauthn: array<string, mixed>, public_key_source: PublicKeyCredentialSource}
     */
    public function verify(
        string $publicKeyCredentialJson,
        string $optionsJson,
        string $rpId,
    ): array {
        $publicKeyCredential = Serializer::make()->fromJson(
            $publicKeyCredentialJson,
            PublicKeyCredential::class,
        );

        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new HttpException(422, 'WebAuthn attestation response is invalid.');
        }

        $options = Serializer::make()->fromJson(
            $optionsJson,
            PublicKeyCredentialCreationOptions::class,
        );

        $configureCeremonyStepManagerFactoryAction = Config::getAction(
            'configure_ceremony_step_manager_factory',
            ConfigureCeremonyStepManagerFactoryAction::class,
        );
        $csmFactory = $configureCeremonyStepManagerFactoryAction->execute();

        try {
            $source = AuthenticatorAttestationResponseValidator::create(
                $csmFactory->creationCeremony(),
            )->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: $options,
                host: $rpId,
            );
        } catch (Throwable) {
            throw new HttpException(422, 'WebAuthn attestation verification failed.');
        }

        $webauthn = [
            'credential_id' => \Spatie\LaravelPasskeys\Models\Passkey::encodeCredentialId($source->publicKeyCredentialId),
            'public_key' => base64_encode($source->credentialPublicKey),
            'sign_count' => (int) $source->counter,
            'aaguid' => $source->aaguid->toRfc4122(),
            'transports' => array_values($source->transports),
            'user_handle' => base64_encode((string) $source->userHandle),
            'rp_id' => $rpId,
        ];

        return [
            'webauthn' => $webauthn,
            'public_key_source' => $source,
        ];
    }
}
