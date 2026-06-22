<?php

namespace App\Support;

class TonAddressCodec
{
    public function isValidAddress(string $address): bool
    {
        return $this->parse($address) !== null;
    }

    public function normalizeAddress(string $address): ?string
    {
        $parsed = $this->parse($address);
        if ($parsed === null) {
            return null;
        }

        return $this->encode(
            workchain: $parsed['workchain'],
            accountId: $parsed['account_id'],
            bounceable: false,
            testOnly: $parsed['test_only'],
            urlSafe: true,
        );
    }

    /**
     * @return array{workchain: int, account_id: string, bounceable: bool, test_only: bool, url_safe: bool}|null
     */
    public function parse(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        if (str_contains($address, ':')) {
            return $this->parseRaw($address);
        }

        return $this->parseFriendly($address);
    }

    /**
     * @return array{workchain: int, account_id: string, bounceable: bool, test_only: bool, url_safe: bool}|null
     */
    private function parseRaw(string $address): ?array
    {
        if (! preg_match('/^(-?\d+):([a-fA-F0-9]{64})$/', $address, $matches)) {
            return null;
        }

        $accountId = hex2bin(strtolower($matches[2]));
        if ($accountId === false || strlen($accountId) !== 32) {
            return null;
        }

        return [
            'workchain' => (int) $matches[1],
            'account_id' => $accountId,
            'bounceable' => true,
            'test_only' => false,
            'url_safe' => false,
        ];
    }

    /**
     * @return array{workchain: int, account_id: string, bounceable: bool, test_only: bool, url_safe: bool}|null
     */
    private function parseFriendly(string $address): ?array
    {
        if (! preg_match('#^[A-Za-z0-9+/_-]{48}$#', $address)) {
            return null;
        }

        $urlSafe = str_contains($address, '-') || str_contains($address, '_');
        $normalized = $urlSafe
            ? strtr($address, '-_', '+/')
            : $address;

        $padding = (4 - (strlen($normalized) % 4)) % 4;
        $decoded = base64_decode($normalized.str_repeat('=', $padding), true);
        if ($decoded === false || strlen($decoded) !== 36) {
            return null;
        }

        $checksum = unpack('n', substr($decoded, 34, 2))[1] ?? null;
        $expected = $this->crc16(substr($decoded, 0, 34));
        if ($checksum !== $expected) {
            return null;
        }

        $tag = ord($decoded[0]);
        $bounceable = ($tag & 0x11) === 0x11;
        $testOnly = ($tag & 0x80) === 0x80;
        $workchain = unpack('c', $decoded[1])[1];

        return [
            'workchain' => $workchain,
            'account_id' => substr($decoded, 2, 32),
            'bounceable' => $bounceable,
            'test_only' => $testOnly,
            'url_safe' => $urlSafe,
        ];
    }

    private function encode(
        int $workchain,
        string $accountId,
        bool $bounceable,
        bool $testOnly,
        bool $urlSafe,
    ): string {
        $tag = ($bounceable ? 0x11 : 0x51) | ($testOnly ? 0x80 : 0x00);
        $payload = chr($tag).pack('c', $workchain).$accountId;
        $payload .= pack('n', $this->crc16($payload));

        $encoded = base64_encode($payload);

        return $urlSafe
            ? rtrim(strtr($encoded, '+/', '-_'), '=')
            : $encoded;
    }

    private function crc16(string $data): int
    {
        $crc = 0;

        for ($offset = 0; $offset < strlen($data); $offset++) {
            $crc ^= (ord($data[$offset]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ 0x1021) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return $crc & 0xFFFF;
    }
}
