<?php

namespace App\Support;

use Olifanton\Interop\Bytes;
use Olifanton\Interop\KeyPair;
use Olifanton\Ton\Contracts\Wallets\V4\WalletV4Options;
use Olifanton\Ton\Contracts\Wallets\V4\WalletV4R2;
use Olifanton\TypedArrays\Uint8Array;

class TonWalletAddressDeriver
{
    public function addressFromPublicKey(Uint8Array $publicKey): string
    {
        $wallet = new WalletV4R2(new WalletV4Options(publicKey: $publicKey));

        return $wallet->getAddress()->asWallet();
    }

    public function addressFromSecretKey(string $secretKey): string
    {
        $keyPair = KeyPair::fromSecretKey(Bytes::bytesToArray($secretKey));

        return $this->addressFromPublicKey($keyPair->publicKey);
    }
}
