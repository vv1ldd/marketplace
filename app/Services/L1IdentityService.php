<?php

namespace App\Services;

use App\Models\User;
use App\Services\Identity\Governance\IdentityGovernanceVaultStreamProducer;
use App\Services\Identity\Governance\IdentityGovernanceWebAuthnPayload;
use InvalidArgumentException;
use Spatie\LaravelPasskeys\Models\Passkey;

class L1IdentityService
{
    public const ADDRESS_PREFIX = 'sl1_';
    public const ENTITY_ADDRESS_PREFIX = 'sl1e_';
    public const ADDRESS_VERSION_PASSKEY_V1 = 'simple-l1:v1:passkey';
    public const ADDRESS_VERSION_ENTITY_V1 = 'simple-l1:v1:entity';

    private const PASSKEY_V1_NAMESPACE = 'simple-l1:v1:passkey:';
    private const ENTITY_V1_NAMESPACE = 'simple-l1:v1:entity:';

    public function addressFromPublicKey(?string $publicKey): string
    {
        $publicKey = (string) $publicKey;

        if ($publicKey === '') {
            throw new InvalidArgumentException('Passkey public key is required to derive an L1 address.');
        }

        return self::ADDRESS_PREFIX.substr(hash('sha256', $publicKey), 0, 40);
    }

    public function keyAddressFromPublicKey(?string $publicKey): string
    {
        $publicKey = $this->canonicalPublicKey($publicKey);

        if ($publicKey === '') {
            throw new InvalidArgumentException('Passkey public key is required to derive an L1 key address.');
        }

        return self::ADDRESS_PREFIX.substr(hash('sha256', self::PASSKEY_V1_NAMESPACE.$publicKey), 0, 40);
    }

    public function newEntityAddress(): string
    {
        return self::ENTITY_ADDRESS_PREFIX.substr(
            hash('sha256', self::ENTITY_V1_NAMESPACE.(string) \Illuminate\Support\Str::uuid().':'.bin2hex(random_bytes(16))),
            0,
            39,
        );
    }

    public function addressFromPasskey(Passkey $passkey): string
    {
        return $this->addressFromPublicKey($passkey->data->credentialPublicKey ?? null);
    }

    public function bindUserToPasskey(User $user, Passkey $passkey): string
    {
        $address = $this->addressFromPasskey($passkey);

        $user->meta = array_merge($user->meta ?? [], ['l1_address' => $address]);
        $user->save();

        return $address;
    }

    public function bindUserToEntityIdentity(User $user, Passkey $passkey): array
    {
        $publicKey = $passkey->data->credentialPublicKey ?? null;
        $keyAddress = $this->keyAddressFromPublicKey($publicKey);
        $meta = $user->meta ?? [];
        $hadEntityBeforeBind = $this->userHadEntity($user, $meta);
        $entityAddress = $user->entity_l1_address
            ?? $meta['entity_l1_address']
            ?? $meta['l1_address']
            ?? $this->newEntityAddress();

        if (! is_string($entityAddress) || ! preg_match('/^sl1e_[a-f0-9]{39}$/i', $entityAddress)) {
            $entityAddress = $this->newEntityAddress();
        }

        $meta = array_merge($meta, [
            'l1_address' => strtolower($entityAddress),
            'entity_l1_address' => strtolower($entityAddress),
            'key_l1_address' => strtolower($keyAddress),
            'simple_l1' => [
                'protocol' => 'simple-l1',
                'address_version' => self::ADDRESS_VERSION_ENTITY_V1,
                'key_address_version' => self::ADDRESS_VERSION_PASSKEY_V1,
                'primary_passkey_id' => $passkey->id,
                'identity_rule' => 'entity_independent_of_keys',
                'anchored_at' => now()->toIso8601String(),
            ],
        ]);

        $user->forceFill([
            'entity_l1_address' => strtolower($entityAddress),
            'key_l1_address' => strtolower($keyAddress),
            'identity_provider' => $user->identity_provider ?: 'local_passkey',
            'meta' => $meta,
        ])->save();

        $this->ensureSl1eUsername($user->refresh(), strtolower($entityAddress));

        if (config('identity_governance.stream_enabled') && ! $hadEntityBeforeBind) {
            $factorId = IdentityGovernanceVaultStreamProducer::deterministicFactorId('passkey', (string) $passkey->id);
            $webauthn = IdentityGovernanceWebAuthnPayload::fromPasskey($passkey);

            app(IdentityGovernanceVaultStreamProducer::class)->recordVaultCreation(
                streamId: strtolower($entityAddress),
                creationId: 'vault-create:user:'.$user->id,
                username: (string) ($user->username ?? ''),
                credentialPayload: IdentityGovernanceWebAuthnPayload::boundPayload(
                    factorId: $factorId,
                    webauthn: $webauthn,
                    extra: [
                        'metadata' => [
                            'public_reference' => strtolower($keyAddress),
                            'passkey_id' => $passkey->id,
                            'label' => $user->profileDisplayName(),
                        ],
                    ],
                ),
            );
        }

        return [
            'entity_l1_address' => strtolower($entityAddress),
            'key_l1_address' => strtolower($keyAddress),
        ];
    }

    /**
     * @param  array<string, mixed>  $metaBeforeBind
     */
    private function userHadEntity(User $user, array $metaBeforeBind): bool
    {
        foreach ([$user->entity_l1_address, $metaBeforeBind['entity_l1_address'] ?? null, $metaBeforeBind['l1_address'] ?? null] as $candidate) {
            if (is_string($candidate) && preg_match('/^sl1e_[a-f0-9]{39}$/i', $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    private function ensureSl1eUsername(User $user, string $entityAddress): void
    {
        if ($user->username) {
            return;
        }

        $username = User::makeUniqueUsername(
            User::usernameCandidateFromEntityAddress($entityAddress),
            $user->id,
        );

        if ($username === null) {
            return;
        }

        $meta = $user->meta ?? [];
        $meta['username'] = $username;
        $meta['display_name'] = $username;
        $meta['simple_l1'] = array_merge($meta['simple_l1'] ?? [], [
            'username' => $username,
            'display_name' => $username,
        ]);

        $user->forceFill([
            'username' => $username,
            'username_key' => $username,
            'first_name' => $user->first_name && ! in_array($user->first_name, ['SL1E', 'Wallet'], true)
                ? $user->first_name
                : $username,
            'meta' => $meta,
        ])->save();
    }

    private function canonicalPublicKey(?string $publicKey): string
    {
        $publicKey = (string) $publicKey;

        if ($publicKey === '') {
            return '';
        }

        if (preg_match('/[^\P{C}\t\r\n]/u', $publicKey) === 1 || ! mb_check_encoding($publicKey, 'UTF-8')) {
            return 'base64url:'.rtrim(strtr(base64_encode($publicKey), '+/', '-_'), '=');
        }

        return trim($publicKey);
    }
}
