<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;
use Spatie\LaravelPasskeys\Models\Passkey;

class L1IdentityService
{
    public const ADDRESS_PREFIX = 'sl1_';

    public function addressFromPublicKey(?string $publicKey): string
    {
        $publicKey = (string) $publicKey;

        if ($publicKey === '') {
            throw new InvalidArgumentException('Passkey public key is required to derive an L1 address.');
        }

        return self::ADDRESS_PREFIX.substr(hash('sha256', $publicKey), 0, 40);
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
}
