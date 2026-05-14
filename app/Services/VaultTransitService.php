<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VaultTransitService
{
    protected string $driver;
    protected string $baseUrl;
    protected string $token;
    protected string $keyName;

    public function __construct()
    {
        $this->driver = config('vault.driver', 'local');
        $this->baseUrl = rtrim((string)config('vault.transit.base_url'), '/');
        $this->token = (string)config('vault.transit.token');
        $this->keyName = (string)config('vault.transit.key_name');
    }

    public function encrypt(?string $plaintext): ?string
    {
        if (blank($plaintext)) {
            return $plaintext;
        }

        if ($this->driver === 'local') {
            return 'vault:local:' . \Illuminate\Support\Facades\Crypt::encryptString($plaintext);
        }

        // Vault expects base64 encoded plaintext
        $base64Plaintext = base64_encode($plaintext);

        try {
            $response = Http::withHeaders([
                'X-Vault-Token' => $this->token,
            ])->post("{$this->baseUrl}/v1/transit/encrypt/{$this->keyName}", [
                'plaintext' => $base64Plaintext,
            ]);

            if ($response->successful()) {
                return $response->json('data.ciphertext');
            }

            Log::error('Vault Encryption Failed', [
                'status' => $response->status(), 
                'body' => $response->body()
            ]);
            
            throw new \Exception('Failed to encrypt PII data via Vault Transit API.');

        } catch (\Exception $e) {
            Log::error('Vault Transit Exception', ['message' => $e->getMessage()]);
            // Re-throw to prevent saving plaintext to database accidentally
            throw $e;
        }
    }

    public function decrypt(?string $ciphertext): ?string
    {
        if (blank($ciphertext)) {
            return $ciphertext;
        }

        if (str_starts_with($ciphertext, 'vault:local:')) {
            try {
                return \Illuminate\Support\Facades\Crypt::decryptString(str_replace('vault:local:', '', $ciphertext));
            } catch (\Exception $e) {
                Log::error('Local Vault Decryption Failed', ['message' => $e->getMessage()]);
                return '*** ENCRYPTED PII ***';
            }
        }

        // If it doesn't look like a vault ciphertext (e.g. vault:v1:...), return as is (legacy support during migration)
        if (!str_starts_with($ciphertext, 'vault:v1:')) {
            return $ciphertext;
        }

        try {
            $response = Http::withHeaders([
                'X-Vault-Token' => $this->token,
            ])->post("{$this->baseUrl}/v1/transit/decrypt/{$this->keyName}", [
                'ciphertext' => $ciphertext,
            ]);

            if ($response->successful()) {
                $base64Plaintext = $response->json('data.plaintext');
                return base64_decode($base64Plaintext);
            }

            Log::error('Vault Decryption Failed', [
                'status' => $response->status(), 
                'body' => $response->body()
            ]);
            
            return '*** ENCRYPTED PII ***'; // Graceful degradation in case Vault is down

        } catch (\Exception $e) {
            Log::error('Vault Decryption Exception', ['message' => $e->getMessage()]);
            return '*** ENCRYPTED PII ***';
        }
    }

    public function computeBlindIndex(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $salt = config('vault.blind_index.salt', 'default-salt');
        // We standardize the input (lowercase, trim) to ensure search matches exactly
        $normalizedValue = strtolower(trim($value));

        return hash_hmac('sha256', $normalizedValue, $salt);
    }
}
