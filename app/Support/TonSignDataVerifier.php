<?php

namespace App\Support;

class TonSignDataVerifier
{
    private const SIGN_DATA_AUTH_MAX_AGE_SECONDS = 900;

    public function runtimeAvailable(): bool
    {
        return function_exists('sodium_crypto_sign_verify_detached')
            && function_exists('hash');
    }

    /**
     * Verify TonConnect signData (text payload) per ton-connect/sign-data spec.
     *
     * @param array{type: string, text?: string} $payload
     */
    public function verifyTextSignData(
        string $signatureBase64,
        string $addressRaw,
        int $timestamp,
        string $domain,
        array $payload,
        string $walletPublicKey,
        string $expectedMessage,
        int $maxAgeSeconds = self::SIGN_DATA_AUTH_MAX_AGE_SECONDS,
    ): bool {
        if (! $this->runtimeAvailable()) {
            return false;
        }

        if (($payload['type'] ?? '') !== 'text') {
            return false;
        }

        $payloadText = (string) ($payload['text'] ?? '');
        if ($payloadText !== $expectedMessage) {
            return false;
        }

        $now = time();
        if ($timestamp > $now + 60 || ($now - $maxAgeSeconds) > $timestamp) {
            return false;
        }

        if (! $this->isAllowedDomain($domain)) {
            return false;
        }

        $parsedAddress = $this->parseRawAddress($addressRaw);
        $publicKey = $this->decodePublicKey($walletPublicKey);
        $signature = base64_decode($signatureBase64, true);

        if ($parsedAddress === null || $publicKey === null || $signature === false) {
            return false;
        }

        $messageHash = hash('sha256', $this->buildTextSigningMessage(
            workchain: $parsedAddress['workchain'],
            addressHash: $parsedAddress['hash'],
            domain: $domain,
            timestamp: $timestamp,
            payloadText: $payloadText,
        ), true);

        return sodium_crypto_sign_verify_detached($signature, $messageHash, $publicKey);
    }

    public function addressMatchesBinding(string $addressRaw, string $bindingOriginal, string $bindingNormalized): bool
    {
        $candidates = array_values(array_unique(array_filter([
            strtolower(trim($bindingOriginal)),
            strtolower(trim($bindingNormalized)),
        ])));

        $raw = strtolower(trim($addressRaw));
        if (in_array($raw, $candidates, true)) {
            return true;
        }

        $codec = app(TonAddressCodec::class);
        $normalizedRaw = $codec->normalizeAddress($addressRaw);
        if ($normalizedRaw !== null && in_array(strtolower($normalizedRaw), $candidates, true)) {
            return true;
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = $codec->normalizeAddress($candidate);
            if ($normalizedCandidate !== null && $normalizedRaw !== null
                && strtolower($normalizedCandidate) === strtolower($normalizedRaw)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedDomain(string $domain): bool
    {
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }

        $allowed = array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) config('blockchain_networks.ton_connect.allowed_domains', '')),
        ));

        if ($allowed !== []) {
            return in_array($domain, $allowed, true);
        }

        $hosts = array_filter([
            $this->hostFromUrl((string) config('storefront.frontend_url', '')),
            $this->hostFromUrl((string) config('app.url', '')),
        ]);

        foreach (explode(',', (string) config('services.trusted_hosts', '')) as $trusted) {
            $trusted = strtolower(trim($trusted));
            if ($trusted !== '') {
                $hosts[] = $trusted;
            }
        }

        $hosts = array_values(array_unique($hosts));

        return in_array($domain, $hosts, true);
    }

    private function hostFromUrl(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    /**
     * @return array{workchain: int, hash: string}|null
     */
    private function parseRawAddress(string $address): ?array
    {
        $address = trim($address);
        if (! preg_match('/^(-?\d+):([a-fA-F0-9]{64})$/', $address, $matches)) {
            return null;
        }

        $hash = hex2bin(strtolower($matches[2]));
        if ($hash === false || strlen($hash) !== 32) {
            return null;
        }

        return [
            'workchain' => (int) $matches[1],
            'hash' => $hash,
        ];
    }

    private function buildTextSigningMessage(
        int $workchain,
        string $addressHash,
        string $domain,
        int $timestamp,
        string $payloadText,
    ): string {
        $domainBytes = $domain;
        $payloadBytes = $payloadText;

        return "\xFF\xFF"
            .'ton-connect/sign-data/'
            .pack('N', $workchain & 0xFFFFFFFF)
            . $addressHash
            . pack('N', strlen($domainBytes))
            . $domainBytes
            . pack('J', $timestamp)
            .'txt'
            . pack('N', strlen($payloadBytes))
            . $payloadBytes;
    }

    private function decodePublicKey(string $publicKey): ?string
    {
        $publicKey = trim($publicKey);
        if ($publicKey === '') {
            return null;
        }

        if (preg_match('/^[a-fA-F0-9]{64}$/', $publicKey)) {
            $decoded = hex2bin($publicKey);

            return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
                ? $decoded
                : null;
        }

        $decoded = base64_decode($publicKey, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return $decoded;
        }

        return null;
    }
}
