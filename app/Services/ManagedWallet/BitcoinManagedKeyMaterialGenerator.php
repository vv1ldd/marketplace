<?php

namespace App\Services\ManagedWallet;

use App\Contracts\ManagedKeyMaterialGenerator;
use App\Support\BitcoinAddressCodec;
use Elliptic\EC;
use Illuminate\Support\Str;

class BitcoinManagedKeyMaterialGenerator implements ManagedKeyMaterialGenerator
{
    public function __construct(
        private readonly BitcoinAddressCodec $addresses,
    ) {}

    public function protocol(): string
    {
        return 'utxo';
    }

    public function generate(): array
    {
        $ec = new EC('secp256k1');
        $keyPair = $ec->genKeyPair();
        $privateKeyHex = $keyPair->getPrivate('hex');
        $publicKeyHex = $keyPair->getPublic(true, 'hex');

        if (! is_string($publicKeyHex)) {
            throw new \RuntimeException('Bitcoin managed wallet key generation failed.');
        }

        $address = $this->addresses->p2wpkhAddressFromPublicKey($publicKeyHex);
        if (! is_string($address)) {
            throw new \RuntimeException('Bitcoin managed wallet address derivation failed.');
        }

        return [
            'address' => $address,
            'secret' => $privateKeyHex,
            'secret_format' => 'bitcoin_private_key_hex',
            'key_reference' => (string) Str::uuid(),
            'public_key_hex' => $publicKeyHex,
        ];
    }
}
