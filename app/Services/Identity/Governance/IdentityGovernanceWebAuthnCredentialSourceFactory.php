<?php

namespace App\Services\Identity\Governance;

use Spatie\LaravelPasskeys\Support\Config;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

final class IdentityGovernanceWebAuthnCredentialSourceFactory
{
    public static function fromMaterial(
        IdentityCredentialMaterial $material,
        string $streamId,
    ): PublicKeyCredentialSource {
        $rawCredentialId = self::decodeStoredBinary($material->credentialId);
        $publicKey = self::decodeStoredBinary($material->publicKey);
        $userHandle = $streamId;

        $aaguid = Uuid::fromString(
            $material->aaguid !== null && $material->aaguid !== ''
                ? $material->aaguid
                : '00000000-0000-0000-0000-000000000000',
        );

        return PublicKeyCredentialSource::create(
            publicKeyCredentialId: $rawCredentialId,
            type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            transports: $material->transports,
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $aaguid,
            credentialPublicKey: $publicKey,
            userHandle: $userHandle,
            counter: $material->signCount,
        );
    }

    /**
     * @return list<PublicKeyCredentialDescriptor>
     */
    public static function descriptorsFromProjection(IdentityCredentialProjection $projection): array
    {
        $descriptors = [];

        foreach ($projection->activeCredentials as $material) {
            $descriptors[] = new PublicKeyCredentialDescriptor(
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                id: self::decodeStoredBinary($material->credentialId),
                transports: $material->transports,
            );
        }

        return $descriptors;
    }

    public static function rpId(): string
    {
        return Config::getRelyingPartyId();
    }

    public static function decodeStoredBinary(string $value): string
    {
        $decoded = base64_decode($value, true);

        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }

        $base64Url = strtr($value, '-_', '+/');
        $base64Url .= str_repeat('=', (4 - strlen($base64Url) % 4) % 4);
        $decoded = base64_decode($base64Url, true);

        return $decoded !== false ? $decoded : $value;
    }
}
