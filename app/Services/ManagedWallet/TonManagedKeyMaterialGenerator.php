<?php

namespace App\Services\ManagedWallet;

use App\Contracts\ManagedKeyMaterialGenerator;
use App\Support\TonWalletAddressDeriver;
use Olifanton\Interop\Bytes;
use Olifanton\Interop\Crypto;
use Illuminate\Support\Str;

class TonManagedKeyMaterialGenerator implements ManagedKeyMaterialGenerator
{
    public function __construct(
        private readonly TonWalletAddressDeriver $addresses,
    ) {}

    public function protocol(): string
    {
        return 'ton';
    }

    public function generate(): array
    {
        if (! function_exists('sodium_crypto_sign_keypair')) {
            throw new \RuntimeException('TON managed wallet provisioning requires the sodium extension.');
        }

        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyPair = Crypto::keyPairFromSeed(Bytes::bytesToArray($seed));
        $secretKey = Bytes::arrayToBytes($keyPair->secretKey);

        return [
            'address' => $this->addresses->addressFromPublicKey($keyPair->publicKey),
            'secret' => base64_encode($secretKey),
            'secret_format' => 'ton_secret_key_base64',
            'key_reference' => (string) Str::uuid(),
            'public_key_hex' => bin2hex(Bytes::arrayToBytes($keyPair->publicKey)),
        ];
    }
}
