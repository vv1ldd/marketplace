<?php

namespace App\Services\ManagedWallet;

use App\Contracts\ManagedKeyMaterialGenerator;
use App\Support\SolanaAddressCodec;
use Illuminate\Support\Str;

class SolanaManagedKeyMaterialGenerator implements ManagedKeyMaterialGenerator
{
    public function __construct(
        private readonly SolanaAddressCodec $addresses,
    ) {}

    public function protocol(): string
    {
        return 'solana';
    }

    public function generate(): array
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            throw new \RuntimeException('Solana managed wallet provisioning requires the sodium extension.');
        }

        $keyPair = sodium_crypto_sign_keypair();
        $secretKey = sodium_crypto_sign_secretkey($keyPair);
        $publicKey = sodium_crypto_sign_publickey($keyPair);

        return [
            'address' => $this->addresses->encodeAddress($publicKey),
            'secret' => base64_encode($secretKey),
            'secret_format' => 'solana_secret_key_base64',
            'key_reference' => (string) Str::uuid(),
            'public_key_hex' => bin2hex($publicKey),
        ];
    }
}
