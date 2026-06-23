<?php

namespace App\Services\Identity\Governance;

use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Spatie\LaravelPasskeys\Support\Config;
use Spatie\LaravelPasskeys\Support\Serializer;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialRequestOptions;

class IdentityGovernanceStreamAssertionVerifier
{
    public function verify(
        IdentityCredentialMaterial $material,
        string $streamId,
        string $publicKeyCredentialJson,
        string $optionsJson,
        ?string $rpId = null,
    ): bool {
        $publicKeyCredential = Serializer::make()->fromJson(
            $publicKeyCredentialJson,
            PublicKeyCredential::class,
        );

        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return false;
        }

        $options = Serializer::make()->fromJson(
            $optionsJson,
            PublicKeyCredentialRequestOptions::class,
        );

        $counterStore = app(IdentityGovernanceCredentialCounterStore::class);
        $materialWithCounter = new IdentityCredentialMaterial(
            factorId: $material->factorId,
            credentialId: $material->credentialId,
            publicKey: $material->publicKey,
            signCount: $counterStore->effectiveSignCount($material, $streamId),
            aaguid: $material->aaguid,
            transports: $material->transports,
        );

        $source = IdentityGovernanceWebAuthnCredentialSourceFactory::fromMaterial(
            $materialWithCounter,
            $streamId,
        );

        $configureCeremonyStepManagerFactoryAction = Config::getAction(
            'configure_ceremony_step_manager_factory',
            ConfigureCeremonyStepManagerFactoryAction::class,
        );
        $csmFactory = $configureCeremonyStepManagerFactoryAction->execute();

        try {
            $validator = AuthenticatorAssertionResponseValidator::create(
                $csmFactory->requestCeremony(),
            );

            $validated = $validator->check(
                $source,
                $publicKeyCredential->response,
                $options,
                $rpId ?? IdentityGovernanceWebAuthnCredentialSourceFactory::rpId(),
                $source->userHandle,
            );

            $counterStore->write($streamId, $material->factorId, (int) $validated->counter);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
