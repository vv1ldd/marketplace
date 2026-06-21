<?php

namespace App\Support;

class SolanaAddressCodec
{
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function isValidAddress(string $address): bool
    {
        return $this->decodeAddress($address) !== null;
    }

    public function decodeAddress(string $address): ?string
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        $decoded = $this->base58Decode($address);
        if ($decoded === null || strlen($decoded) !== 32) {
            return null;
        }

        return $decoded;
    }

    public function encodeAddress(string $publicKey): string
    {
        return $this->base58Encode($publicKey);
    }

    private function base58Encode(string $data): string
    {
        $hex = bin2hex($data);
        $num = gmp_init($hex, 16);
        $encoded = '';

        while (gmp_cmp($num, gmp_init(0)) > 0) {
            [$num, $remainder] = gmp_div_qr($num, gmp_init(58));
            $encoded = self::BASE58_ALPHABET[gmp_intval($remainder)].$encoded;
        }

        for ($index = 0; $index < strlen($data) && $data[$index] === "\x00"; $index++) {
            $encoded = '1'.$encoded;
        }

        return $encoded;
    }

    public function decodeBase58(string $encoded): ?string
    {
        return $this->base58Decode($encoded);
    }

    private function base58Decode(string $encoded): ?string
    {
        $num = gmp_init(0);
        foreach (str_split($encoded) as $character) {
            $index = strpos(self::BASE58_ALPHABET, $character);
            if ($index === false) {
                return null;
            }

            $num = gmp_add(gmp_mul($num, 58), $index);
        }

        $hex = gmp_strval($num, 16);
        if ($hex === '0') {
            $hex = '';
        } elseif (strlen($hex) % 2 !== 0) {
            $hex = '0'.$hex;
        }

        $leadingZeros = 0;
        foreach (str_split($encoded) as $character) {
            if ($character === '1') {
                $leadingZeros++;
                continue;
            }

            break;
        }

        $decoded = str_repeat("\x00", $leadingZeros).hex2bin($hex);
        if ($decoded === false) {
            return null;
        }

        return $decoded;
    }
}
