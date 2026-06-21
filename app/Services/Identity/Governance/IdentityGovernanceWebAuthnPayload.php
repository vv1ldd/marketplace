<?php

namespace App\Services\Identity\Governance;

use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Config;

/**
 * Build replay-ready credential.bound.webauthn payloads from registration artifacts.
 */
final class IdentityGovernanceWebAuthnPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function fromPasskey(Passkey $passkey): array
    {
        $source = $passkey->data;

        return [
            'credential_id' => Passkey::encodeCredentialId($source->publicKeyCredentialId),
            'public_key' => base64_encode($source->credentialPublicKey),
            'sign_count' => (int) $source->counter,
            'aaguid' => $source->aaguid->toRfc4122(),
            'transports' => array_values($source->transports),
            'user_handle' => base64_encode((string) $source->userHandle),
            'rp_id' => Config::getRelyingPartyId(),
        ];
    }

    /**
     * @param  array<string, mixed>  $webauthn
     * @return array<string, mixed>
     */
    public static function boundPayload(string $factorId, array $webauthn, array $extra = []): array
    {
        return array_merge([
            'factor_id' => $factorId,
            'class' => 'possession',
            'type' => 'passkey',
            'purpose' => 'daily',
            'webauthn' => $webauthn,
        ], $extra);
    }
}
