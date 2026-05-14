<?php

namespace App\Casts;

use App\Services\VaultTransitService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * VaultEncryptedJson
 *
 * Encrypts a JSON-serializable field (array/object) as a Vault ciphertext.
 *
 * Storage format in DB: vault:local:eyJ... (or vault:v1:... in Transit mode)
 * Runtime type: array (transparent to app code, same as 'array' cast)
 *
 * Usage in model:
 *   'client_info' => VaultEncryptedJson::class,
 */
class VaultEncryptedJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return [];
        }

        // Already a plain JSON string (legacy unencrypted data)
        if (!str_starts_with((string)$value, 'vault:')) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        $decrypted = app(VaultTransitService::class)->decrypt($value);

        if ($decrypted === '*** ENCRYPTED PII ***' || empty($decrypted)) {
            return [];
        }

        $decoded = json_decode($decrypted, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (empty($value)) {
            return [$key => null];
        }

        // Normalize to array if it arrives as a JSON string
        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $encrypted = app(VaultTransitService::class)->encrypt($json);

        return [$key => $encrypted];
    }
}
