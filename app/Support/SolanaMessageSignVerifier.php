<?php

namespace App\Support;

class SolanaMessageSignVerifier
{
    private const OFFCHAIN_PREFIX = "\xffSolana Off-chain Message";

    public function __construct(
        private readonly SolanaAddressCodec $addresses,
    ) {}

    public function runtimeAvailable(): bool
    {
        return function_exists('sodium_crypto_sign_verify_detached');
    }

    public function verifyMessage(
        string $message,
        string $signature,
        string $address,
        ?string $signedMessage = null,
    ): bool {
        if (! $this->runtimeAvailable()) {
            return false;
        }

        $publicKey = $this->addresses->decodeAddress($address);
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

    public function offchainMessage(string $message): string
    {
        return self::OFFCHAIN_PREFIX.pack('v', strlen($message)).$message;
    }

    /**
     * @return list<string>
     */
    private function messageCandidates(string $message, ?string $signedMessage): array
    {
        $candidates = [
            $message,
            $this->offchainMessage($message),
        ];

        if ($signedMessage !== null && trim($signedMessage) !== '') {
            $decoded = base64_decode(trim($signedMessage), true);
            if ($decoded !== false && $decoded !== '') {
                $candidates[] = $decoded;
            }
        }

        return array_values(array_unique($candidates, SORT_STRING));
    }

    private function decodeSignature(string $signature): ?string
    {
        $signature = trim($signature);
        if ($signature === '') {
            return null;
        }

        if (str_starts_with($signature, '0x')) {
            $decoded = hex2bin(substr($signature, 2));

            return $decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_BYTES
                ? $decoded
                : null;
        }

        $decoded = base64_decode($signature, true);
        if ($decoded !== false && strlen($decoded) === SODIUM_CRYPTO_SIGN_BYTES) {
            return $decoded;
        }

        $base58 = $this->addresses->decodeBase58($signature);

        return $base58 !== null && strlen($base58) === SODIUM_CRYPTO_SIGN_BYTES
            ? $base58
            : null;
    }
}
