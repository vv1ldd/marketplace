<?php

namespace App\Support;

use Elliptic\EC;
use kornrunner\Keccak;

class EvmPersonalSignVerifier
{
    public function recoverAddress(string $message, string $signature): ?string
    {
        $signature = trim($signature);
        if (! str_starts_with($signature, '0x') || strlen($signature) !== 132) {
            return null;
        }

        $hash = $this->hashPersonalMessage($message);
        $signatureBytes = hex2bin(substr($signature, 2));
        if ($signatureBytes === false || strlen($signatureBytes) !== 65) {
            return null;
        }

        $r = bin2hex(substr($signatureBytes, 0, 32));
        $s = bin2hex(substr($signatureBytes, 32, 32));
        $recoveryId = ord($signatureBytes[64]);
        if ($recoveryId >= 27) {
            $recoveryId -= 27;
        }

        if (! in_array($recoveryId, [0, 1], true)) {
            return null;
        }

        try {
            $ec = new EC('secp256k1');
            $publicKey = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $recoveryId);
            $publicKeyHex = $publicKey->encode('hex', false);
            if (! str_starts_with($publicKeyHex, '04')) {
                return null;
            }

            $publicKeyBody = hex2bin(substr($publicKeyHex, 2));
            if ($publicKeyBody === false) {
                return null;
            }

            $address = '0x'.substr(Keccak::hash($publicKeyBody, 256), -40);

            return strtolower($address);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hashPersonalMessage(string $message): string
    {
        $prefix = "\x19Ethereum Signed Message:\n".strlen($message);

        return Keccak::hash($prefix.$message, 256);
    }
}
