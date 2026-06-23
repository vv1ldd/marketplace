<?php

namespace Tests\Feature;

use Olifanton\Interop\Bytes;
use Olifanton\Interop\Crypto;
use Olifanton\Mnemonic\TonMnemonic;
use Olifanton\Ton\Contracts\Wallets\V4\WalletV4Options;
use Olifanton\Ton\Contracts\Wallets\V4\WalletV4R2;
use App\Support\TonWalletAddressDeriver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TonWalletAddressDeriverTest extends TestCase
{
    #[Test]
    public function it_derives_wallet_v4r2_address_from_known_mnemonic(): void
    {
        $words = [
            'bring', 'like', 'escape', 'health', 'chimney', 'pear',
            'whale', 'peasant', 'drum', 'beach', 'mass', 'garden',
            'riot', 'alien', 'possible', 'bus', 'shove', 'unable',
            'jar', 'anxiety', 'click', 'salon', 'canoe', 'lion',
        ];

        $keyPair = TonMnemonic::mnemonicToKeyPair($words);
        $deriver = app(TonWalletAddressDeriver::class);

        $this->assertSame(
            'UQDH6ELHpOUPfJfDg6ZxO89z7ZyRSI60MkP8CVWdQXMYYV-O',
            $deriver->addressFromPublicKey($keyPair->publicKey),
        );
        $this->assertSame(
            'UQDH6ELHpOUPfJfDg6ZxO89z7ZyRSI60MkP8CVWdQXMYYV-O',
            $deriver->addressFromSecretKey(Bytes::arrayToBytes($keyPair->secretKey)),
        );
    }

    #[Test]
    public function generated_managed_ton_key_material_matches_wallet_contract(): void
    {
        if (! function_exists('sodium_randombytes_buf')) {
            $this->markTestSkipped('TON managed wallet provisioning requires the sodium extension.');
        }

        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
        $keyPair = Crypto::keyPairFromSeed(Bytes::bytesToArray($seed));
        $wallet = new WalletV4R2(new WalletV4Options(publicKey: $keyPair->publicKey));
        $deriver = app(TonWalletAddressDeriver::class);

        $this->assertSame(
            $wallet->getAddress()->asWallet(),
            $deriver->addressFromPublicKey($keyPair->publicKey),
        );
    }
}
