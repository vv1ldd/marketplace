<?php

namespace App\Services\Identity\Governance;

/**
 * Server-side WebAuthn material required to verify assertions (not the private key).
 */
final class IdentityCredentialMaterial
{
    /**
     * @param  list<string>  $transports
     */
    public function __construct(
        public readonly string $factorId,
        public readonly string $credentialId,
        public readonly string $publicKey,
        public readonly int $signCount,
        public readonly ?string $aaguid = null,
        public readonly array $transports = [],
    ) {}

    /**
     * @param  array<string, mixed>  $boundPayload  credential.bound payload
     */
    public static function fromBoundPayload(array $boundPayload): ?self
    {
        $webauthn = $boundPayload['webauthn'] ?? null;

        if (! is_array($webauthn)) {
            return null;
        }

        $credentialId = trim((string) ($webauthn['credential_id'] ?? ''));
        $publicKey = trim((string) ($webauthn['public_key'] ?? ''));

        if ($credentialId === '' || $publicKey === '') {
            return null;
        }

        $transports = $webauthn['transports'] ?? [];

        return new self(
            factorId: (string) ($boundPayload['factor_id'] ?? ''),
            credentialId: $credentialId,
            publicKey: $publicKey,
            signCount: (int) ($webauthn['sign_count'] ?? 0),
            aaguid: isset($webauthn['aaguid']) ? (string) $webauthn['aaguid'] : null,
            transports: is_array($transports) ? array_values(array_map('strval', $transports)) : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'factor_id' => $this->factorId,
            'credential_id' => $this->credentialId,
            'public_key' => $this->publicKey,
            'sign_count' => $this->signCount,
            'aaguid' => $this->aaguid,
            'transports' => $this->transports,
        ];
    }

    /**
     * @return array{type: string, id: string, transports?: list<string>}
     */
    public function toAllowCredentialDescriptor(): array
    {
        $descriptor = [
            'type' => 'public-key',
            'id' => $this->credentialId,
        ];

        if ($this->transports !== []) {
            $descriptor['transports'] = $this->transports;
        }

        return $descriptor;
    }
}
