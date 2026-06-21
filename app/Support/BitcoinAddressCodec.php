<?php

namespace App\Support;

class BitcoinAddressCodec
{
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    public function hash160(string $data): string
    {
        return hash('ripemd160', hash('sha256', $data, true), true);
    }

    public function p2pkhAddressFromPublicKey(string $publicKey): ?string
    {
        if (! str_starts_with($publicKey, '04') && ! str_starts_with($publicKey, '02') && ! str_starts_with($publicKey, '03')) {
            return null;
        }

        $publicKeyBin = hex2bin($publicKey);
        if ($publicKeyBin === false) {
            return null;
        }

        $payload = "\x00".$this->hash160($publicKeyBin);

        return $this->base58CheckEncode($payload);
    }

    public function p2wpkhAddressFromPublicKey(string $publicKey): ?string
    {
        if (! str_starts_with($publicKey, '02') && ! str_starts_with($publicKey, '03')) {
            return null;
        }

        $program = $this->hash160(hex2bin($publicKey) ?: '');

        return $this->bech32Encode('bc', 0, $program);
    }

    public function matchesAddress(string $publicKey, string $expectedAddress): bool
    {
        $expectedAddress = trim($expectedAddress);
        if ($expectedAddress === '') {
            return false;
        }

        if (str_starts_with(strtolower($expectedAddress), 'bc1')) {
            $derived = $this->p2wpkhAddressFromPublicKey($publicKey);

            return is_string($derived) && strcasecmp($derived, $expectedAddress) === 0;
        }

        if (str_starts_with($expectedAddress, '1')) {
            $derived = $this->p2pkhAddressFromPublicKey($publicKey);

            return is_string($derived) && $derived === $expectedAddress;
        }

        return false;
    }

    public function base58CheckEncode(string $payload): string
    {
        $checksum = substr(hash('sha256', hash('sha256', $payload, true), true), 0, 4);

        return $this->base58Encode($payload.$checksum);
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

    private function bech32Encode(string $hrp, int $version, string $program): string
    {
        $data = array_merge([$version], $this->convertBits(array_values(unpack('C*', $program)), 8, 5, true));
        $checksum = $this->bech32CreateChecksum($hrp, $data);

        return strtolower($hrp.'1'.implode('', array_map(fn (int $value): string => $this->bech32Charset()[$value], array_merge($data, $checksum))));
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private function bech32CreateChecksum(string $hrp, array $data): array
    {
        $values = array_merge($this->bech32HrpExpand($hrp), $data, [0, 0, 0, 0, 0, 0]);
        $polymod = $this->bech32Polymod($values) ^ 1;

        $checksum = [];
        for ($index = 0; $index < 6; $index++) {
            $checksum[] = ($polymod >> (5 * (5 - $index))) & 31;
        }

        return $checksum;
    }

    /**
     * @return list<int>
     */
    private function bech32HrpExpand(string $hrp): array
    {
        $expanded = [];
        $length = strlen($hrp);
        for ($index = 0; $index < $length; $index++) {
            $expanded[] = ord($hrp[$index]) >> 5;
        }
        $expanded[] = 0;
        for ($index = 0; $index < $length; $index++) {
            $expanded[] = ord($hrp[$index]) & 31;
        }

        return $expanded;
    }

    /**
     * @param list<int> $values
     */
    private function bech32Polymod(array $values): int
    {
        $generator = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        $checksum = 1;

        foreach ($values as $value) {
            $top = $checksum >> 25;
            $checksum = (($checksum & 0x1ffffff) << 5) ^ $value;
            for ($index = 0; $index < 5; $index++) {
                if ((($top >> $index) & 1) === 1) {
                    $checksum ^= $generator[$index];
                }
            }
        }

        return $checksum;
    }

    /**
     * @return list<string>
     */
    private function bech32Charset(): array
    {
        return str_split('qpzry9x8gf2tvdw0s3jn54khce6mua7l');
    }

    /**
     * @param list<int> $data
     * @return list<int>
     */
    private function convertBits(array $data, int $fromBits, int $toBits, bool $pad): array
    {
        $acc = 0;
        $bits = 0;
        $result = [];
        $maxv = (1 << $toBits) - 1;

        foreach ($data as $value) {
            $acc = ($acc << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $result[] = ($acc >> $bits) & $maxv;
            }
        }

        if ($pad) {
            if ($bits > 0) {
                $result[] = ($acc << ($toBits - $bits)) & $maxv;
            }
        } elseif ($bits >= $fromBits || (($acc << ($toBits - $bits)) & $maxv) !== 0) {
            return [];
        }

        return $result;
    }
}
