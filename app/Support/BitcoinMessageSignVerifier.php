<?php

namespace App\Support;

use Elliptic\EC;
use Symfony\Component\Process\Process;

class BitcoinMessageSignVerifier
{
    public function __construct(
        private readonly BitcoinAddressCodec $addresses,
    ) {}

    public function verifyMessage(string $message, string $signature, string $expectedAddress): bool
    {
        $signature = trim($signature);
        if ($signature === '') {
            return false;
        }

        if ($this->verifyLegacyMessage($message, $signature, $expectedAddress)) {
            return true;
        }

        return $this->verifyBip322Message($message, $signature, $expectedAddress);
    }

    public function bip322RuntimeAvailable(): bool
    {
        $script = base_path('scripts/verify-bitcoin-message.cjs');
        $verifierModule = base_path('scripts/node_modules/bip322-js/dist/Verifier.js');
        $nodeBinary = $this->nodeBinary();

        if ($nodeBinary === '' || ! is_file($script) || ! is_file($verifierModule)) {
            return false;
        }

        $process = new Process([$nodeBinary, '-v']);
        $process->setTimeout(3);

        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        return $process->isSuccessful();
    }

    public function signMessage(string $privateKeyHex, string $message, bool $compressed = true): string
    {
        $privateKeyHex = ltrim(strtolower(trim($privateKeyHex)), '0x');
        $hash = $this->hashMessage($message);
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKeyHex, 'hex');
        $publicKeyHex = $key->getPublic(true, 'hex');

        for ($recoveryId = 0; $recoveryId < 4; $recoveryId++) {
            $signature = $key->sign($hash, 'hex', ['canonical' => true]);
            $header = ($compressed ? 31 : 27) + $recoveryId;
            $payload = chr($header)
                .$this->pad32($signature->r->toString(16))
                .$this->pad32($signature->s->toString(16));

            try {
                $recovered = $ec->recoverPubKey($hash, [
                    'r' => str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT),
                    's' => str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT),
                ], $recoveryId);
                if ($recovered->encode('hex', $compressed) === $publicKeyHex) {
                    return base64_encode($payload);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw new \RuntimeException('Could not produce a recoverable Bitcoin message signature.');
    }

    public function hashMessage(string $message): string
    {
        $prefix = "\x18Bitcoin Signed Message:\n";
        $payload = $prefix.$this->encodeVarInt(strlen($message)).$message;

        return bin2hex(hash('sha256', hash('sha256', $payload, true), true));
    }

    private function verifyLegacyMessage(string $message, string $signature, string $expectedAddress): bool
    {
        $signatureBytes = $this->decodeSignature($signature);
        if ($signatureBytes === null || strlen($signatureBytes) !== 65) {
            return false;
        }

        $header = ord($signatureBytes[0]);
        if ($header < 27 || $header > 34) {
            return false;
        }

        $compressed = $header >= 31;
        $recoveryId = $compressed ? ($header - 31) : ($header - 27);
        $hash = $this->hashMessage($message);
        $r = bin2hex(substr($signatureBytes, 1, 32));
        $s = bin2hex(substr($signatureBytes, 33, 32));

        try {
            $ec = new EC('secp256k1');
            $publicKey = $ec->recoverPubKey($hash, ['r' => $r, 's' => $s], $recoveryId);
            $publicKeyHex = $publicKey->encode('hex', $compressed);
        } catch (\Throwable) {
            return false;
        }

        return $this->addresses->matchesAddress($publicKeyHex, $expectedAddress);
    }

    private function verifyBip322Message(string $message, string $signature, string $expectedAddress): bool
    {
        $signature = trim($signature);
        if ($signature === '') {
            return false;
        }

        $script = base_path('scripts/verify-bitcoin-message.cjs');
        $verifierModule = base_path('scripts/node_modules/bip322-js/dist/Verifier.js');
        if (! is_file($script) || ! is_file($verifierModule)) {
            return false;
        }

        $nodeBinary = $this->nodeBinary();
        if ($nodeBinary === '') {
            return false;
        }

        $process = new Process([$nodeBinary, $script]);
        $process->setWorkingDirectory(base_path('scripts'));
        $process->setInput(json_encode([
            'address' => $expectedAddress,
            'message' => $message,
            'signature' => $signature,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $process->setTimeout(5);

        try {
            $process->run();
        } catch (\Throwable) {
            return false;
        }

        if (trim($process->getOutput()) === '1') {
            return true;
        }

        return false;
    }

    private function nodeBinary(): string
    {
        return trim((string) config('services.node.binary', 'node'));
    }

    private function decodeSignature(string $signature): ?string
    {
        $signature = trim($signature);
        if ($signature === '') {
            return null;
        }

        if (str_starts_with($signature, '0x')) {
            $binary = hex2bin(substr($signature, 2));

            return $binary === false ? null : $binary;
        }

        $decoded = base64_decode($signature, true);

        return $decoded === false ? null : $decoded;
    }

    private function encodeVarInt(int $length): string
    {
        if ($length < 253) {
            return chr($length);
        }

        if ($length <= 0xFFFF) {
            return chr(253).pack('v', $length);
        }

        if ($length <= 0xFFFFFFFF) {
            return chr(254).pack('V', $length);
        }

        return chr(255).pack('P', $length);
    }

    private function pad32(string $hex): string
    {
        return hex2bin(str_pad($hex, 64, '0', STR_PAD_LEFT)) ?: '';
    }
}
