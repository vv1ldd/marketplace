<?php

namespace App\Support;

class TonMessageSignVerifier
{
    public function runtimeAvailable(): bool
    {
        return function_exists('sodium_crypto_sign_verify_detached');
    }

    public function verifyMessage(
        string $message,
        string $signature,
        string $walletPublicKey,
        ?string $signedMessage = null,
    ): bool {
        if (! $this->runtimeAvailable()) {
            return false;
        }

        $publicKey = $this->decodePublicKey($walletPublicKey);
        $signatureBytes = $this->decodeSignature($signature);

        if ($publicKey === null || $signatureBytes === null) {
            return false;
        }

        foreach ($this->messageCandidates($message, $signedMessage) as $candidate) {
            if (sodium_crypto_sign_verify_detached($signatureBytes, $candidate, $publicKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function messageCandidates(string $message, ?string $signedMessage): array
    {
        $candidates = [$message];

        if ($signedMessage !== null && trim($signedMessage) !== '') {
            $decoded = base64_decode(trim($signedMessage), true);
            if ($decoded !== false && $decoded !== '') {
                $candidates[] = $decoded;
            }
        }

        return array_values(array_unique($candidates, SORT_STRING));
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

    private function decodeSignature(string $signature): ?string
    {
        $signature = trim($signature);
        if ($signature === '') {
            return null;
        }

        if (preg_match('/^[a-fA-F0-9]{128}$/', $signature)) {
            $decoded = hex2bin($signature);

            return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_BYTES
                ? $decoded
                : null;
        }

        $decoded = base64_decode($signature, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_BYTES) {
            return $decoded;
        }

        $urlDecoded = base64_decode(strtr($signature, '-_', '+/'), true);
        if ($urlDecoded !== false && strlen($urlDecoded) === SODIUM_CRYPTO_SIGN_BYTES) {
            return $urlDecoded;
        }

        return null;
    }
}
