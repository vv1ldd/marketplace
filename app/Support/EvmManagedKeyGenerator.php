<?php

namespace App\Support;

use Elliptic\EC;
use Illuminate\Support\Str;
use kornrunner\Keccak;

class EvmManagedKeyGenerator
{
    /**
     * @return array{
     *     address: string,
     *     private_key_hex: string,
     *     public_key_hex: string,
     *     key_reference: string
     * }
     */
    public function generate(): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $privateKeyHex = $keyPair->getPrivate('hex');
        $publicKeyHex = $keyPair->getPublic(false, 'hex');

        if (! is_string($publicKeyHex) || ! str_starts_with($publicKeyHex, '04')) {
            throw new \RuntimeException('Managed EVM key generation failed.');
        }

        $publicKeyBody = hex2bin(substr($publicKeyHex, 2));
        if ($publicKeyBody === false) {
            throw new \RuntimeException('Managed EVM key generation failed.');
        }

        $address = strtolower('0x'.substr(Keccak::hash($publicKeyBody, 256), -40));

        return [
            'address' => $address,
            'private_key_hex' => $privateKeyHex,
            'public_key_hex' => $publicKeyHex,
            'key_reference' => (string) Str::uuid(),
        ];
    }
}
