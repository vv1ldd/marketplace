<?php

namespace App\Services;

use App\Models\User;
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
        $entityAddress = $meta['entity_l1_address'] ?? $meta['l1_address'] ?? $this->newEntityAddress();

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

        $user->meta = $meta;
        $user->save();

        return [
            'entity_l1_address' => strtolower($entityAddress),
            'key_l1_address' => strtolower($keyAddress),
        ];
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
