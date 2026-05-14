<?php

namespace App\Services;

/**
 * Sovereign Voucher Credential Engine (SVC Engine)
 *
 * Generates structured, traceable, tamper-evident voucher codes.
 *
 * TWO FORMATS:
 *
 * 1. BRANDED (default — marketplace, seller-facing vouchers):
 *    {SHOP_PREFIX}-{EPOCH36}-{SKU_TAG}-{ENTROPY}-{CHECKSUM}
 *    Example: WLD-1Q2W3E-A7F2-X9KM4P-C3
 *    — No SVC emitter. Starts with the shop's own prefix.
 *
 * 2. DIRECT CHANNEL (SVC-prefixed — platform-issued, traceable):
 *    SVC-{ISSUER}-{EPOCH36}-{SKU_TAG}-{ENTROPY}-{CHECKSUM}
 *    Example: SVC-MEAN-1Q2W3E-A7F2-X9KM4P-C3
 *    — Used for Telegram Bot, VK Store, WhatsApp Business etc.
 *
 * Segments:
 *   SVC        — Sovereign Voucher Credential (direct channel emitter namespace)
 *   ISSUER     — Shop prefix (max 4 chars)
 *   EPOCH36    — Epoch timestamp in Base36, resolution: 1 minute
 *   SKU_TAG    — 4-char HMAC-SHA256 tag derived from SKU
 *   ENTROPY    — 6-char CSPRNG entropy (cryptographically secure random)
 *   CHECKSUM   — 2-char CRC8 checksum of all prior segments
 */
class VoucherEngine
{
    /**
     * Alphabet: Base32-like, ambiguous characters removed (0/O, 1/I/L removed)
     * Ensures codes are readable and typable without confusion.
     */
    const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    const EMITTER = 'SVC';

    /**
     * Generate a BRANDED voucher (no SVC prefix).
     * For marketplace sellers — code starts with their own shop prefix.
     *
     * Format: {PREFIX}-{EPOCH36}-{SKU_TAG}-{ENTROPY}-{CHECKSUM}
     * Example: WLD-1Q2W3E-A7F2-X9KM4P-C3
     *
     * @param string|null $issuerPrefix  Shop prefix (max 4 chars), e.g. 'WLD'
     * @param string|null $sku           Product SKU for tagging (optional)
     * @param string|null $secret        HMAC secret for SKU tag (defaults to APP_KEY)
     */
    public static function issue(
        ?string $issuerPrefix = null,
        ?string $sku = null,
        ?string $secret = null
    ): string {
        return self::generate($issuerPrefix, $sku, $secret, branded: true);
    }

    /**
     * Generate a DIRECT CHANNEL voucher (with SVC prefix).
     * For platform-level channels: Telegram Bot, VK Store, WhatsApp, etc.
     *
     * Format: SVC-{PREFIX}-{EPOCH36}-{SKU_TAG}-{ENTROPY}-{CHECKSUM}
     * Example: SVC-MEAN-1Q2W3E-A7F2-X9KM4P-C3
     */
    public static function issueForDirectChannel(
        ?string $issuerPrefix = null,
        ?string $sku = null,
        ?string $secret = null
    ): string {
        return self::generate($issuerPrefix, $sku, $secret, branded: false);
    }

    /**
     * Core generator — used by both issue() and issueForDirectChannel().
     */
    private static function generate(
        ?string $issuerPrefix,
        ?string $sku,
        ?string $secret,
        bool $branded = true
    ): string {
        $issuer   = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $issuerPrefix ?? 'WLD'), 0, 4));
        $epoch36  = self::epochSegment();
        $skuTag   = self::skuTag($sku, $secret);
        $entropy  = self::entropySegment(6);

        $segments = $branded
            ? [$issuer, $epoch36, $skuTag, $entropy]
            : [self::EMITTER, $issuer, $epoch36, $skuTag, $entropy];

        $body     = implode('-', $segments);
        $checksum = self::checksum($body);

        return "{$body}-{$checksum}";
    }

    /**
     * Validate a SVC code checksum.
     * Returns true if the code is structurally valid (not necessarily active).
     */
    public static function validate(string $code): bool
    {
        $parts = explode('-', $code);

        // SVC format: SVC-ISSUER-EPOCH-SKU-ENTROPY-CHECKSUM (6 segments)
        // Branded format: PREFIX-EPOCH-SKU-ENTROPY-CHECKSUM (5 segments)
        if (count($parts) < 5) {
            return false;
        }

        // Extract checksum (last segment) and rebuild body
        $checksum = array_pop($parts);
        $body = implode('-', $parts);

        return self::checksum($body) === $checksum;
    }

    /**
     * Decode readable metadata from a SVC code without a database lookup.
     * Returns approximate issuance time, issuer prefix, and SKU tag.
     */
    public static function inspect(string $code): array
    {
        $parts = explode('-', $code);

        return [
            'valid'     => self::validate($code),
            'emitter'   => $parts[0] ?? null,
            'issuer'    => $parts[1] ?? null,
            'issued_at' => isset($parts[2]) ? self::decodeEpoch($parts[2]) : null,
            'sku_tag'   => $parts[3] ?? null,
            'entropy'   => $parts[4] ?? null,
            'checksum'  => $parts[5] ?? null,
        ];
    }

    /**
     * Restore canonical dashes for a given raw string, regardless of how the user inputted dashes.
     * This ensures the blind index hash matches the database value.
     */
    public static function formatCanonical(string $rawCode): ?string
    {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawCode));
        
        // Minimum length: Epoch (5) + SKU (4) + Entropy (6) + Checksum (2) = 17 chars.
        if (strlen($clean) < 17) {
            return null;
        }

        $checksum = substr($clean, -2);
        $entropy  = substr($clean, -8, 6);
        $skuTag   = substr($clean, -12, 4);
        $epoch36  = substr($clean, -17, 5);
        $issuer   = substr($clean, 0, -17);

        if (str_starts_with($issuer, self::EMITTER)) {
             $realIssuer = substr($issuer, 3);
             return self::EMITTER . '-' . $realIssuer . '-' . $epoch36 . '-' . $skuTag . '-' . $entropy . '-' . $checksum;
        }

        return $issuer . '-' . $epoch36 . '-' . $skuTag . '-' . $entropy . '-' . $checksum;
    }

    // ─── Private Segment Generators ──────────────────────────────────────────

    /**
     * Encode current time (minute-level precision) in Base36 uppercase.
     * Keeps temporal traceability without exposing exact seconds.
     */
    private static function epochSegment(): string
    {
        $minuteEpoch = (int) floor(time() / 60);
        return strtoupper(base_convert((string)$minuteEpoch, 10, 36));
    }

    private static function decodeEpoch(string $epoch36): string
    {
        $minuteEpoch = (int) base_convert(strtolower($epoch36), 36, 10);
        return date('Y-m-d H:i', $minuteEpoch * 60) . ' UTC';
    }

    /**
     * Derive a 4-char deterministic tag from SKU using HMAC-SHA256.
     * Allows product class identification without embedding raw SKU.
     * Falls back to 'XXXX' if no SKU provided.
     */
    private static function skuTag(?string $sku, ?string $secret): string
    {
        if (!$sku) {
            return 'XXXX';
        }

        $secret = $secret ?? config('app.key', 'sovereign-fallback');
        $hmac = hash_hmac('sha256', strtoupper(trim($sku)), $secret);

        // Map hex digest to our unambiguous alphabet
        return self::hexToAlphabet($hmac, 4);
    }

    /**
     * Generate N characters of cryptographically secure random entropy
     * using our unambiguous alphabet.
     */
    private static function entropySegment(int $length): string
    {
        $result = '';
        $alphabetSize = strlen(self::ALPHABET);

        while (strlen($result) < $length) {
            $byte = random_bytes(1);
            $idx = ord($byte) % $alphabetSize;
            // Rejection sampling to avoid modulo bias
            if (ord($byte) < ($alphabetSize * floor(256 / $alphabetSize))) {
                $result .= self::ALPHABET[$idx];
            }
        }

        return $result;
    }

    /**
     * Compute a 2-char CRC8 checksum over the code body.
     * Allows rapid client-side validation before any API call.
     */
    private static function checksum(string $body): string
    {
        $crc = 0;
        foreach (str_split($body) as $char) {
            $crc ^= ord($char);
            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x80) ? (($crc << 1) ^ 0x07) : ($crc << 1);
            }
            $crc &= 0xFF;
        }

        return self::hexToAlphabet(str_pad(dechex($crc), 2, '0', STR_PAD_LEFT), 2);
    }

    private static function hexToAlphabet(string $hex, int $length): string
    {
        $result = '';
        $alphabetSize = strlen(self::ALPHABET);
        $hexLen = strlen($hex);

        for ($i = 0; $i < $hexLen && strlen($result) < $length; $i += 2) {
            $byte = hexdec(substr($hex, $i, 2));
            if ($byte < ($alphabetSize * floor(256 / $alphabetSize))) {
                $result .= self::ALPHABET[$byte % $alphabetSize];
            }
        }

        // If hex exhausted before reaching length, pad with alphabet chars from hash of hex
        while (strlen($result) < $length) {
            $extra = hash('sha256', $hex . $result);
            foreach (str_split($extra, 2) as $pair) {
                $byte = hexdec($pair);
                if ($byte < ($alphabetSize * floor(256 / $alphabetSize)) && strlen($result) < $length) {
                    $result .= self::ALPHABET[$byte % $alphabetSize];
                }
            }
        }

        return strtoupper(substr($result, 0, $length));
    }
}
