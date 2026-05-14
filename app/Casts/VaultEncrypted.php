<?php

namespace App\Casts;

use App\Services\VaultTransitService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class VaultEncrypted implements CastsAttributes
{
    /**
     * @param string|null $bidxColumn The name of the column to store the blind index (e.g., 'email_bidx')
     */
    public function __construct(protected ?string $bidxColumn = null)
    {
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return $value;
        }

        return app(VaultTransitService::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $service = app(VaultTransitService::class);
        
        // 1. Encrypt the actual value
        $encrypted = empty($value) ? $value : $service->encrypt($value);
        
        $data = [$key => $encrypted];
        
        // 2. Generate Blind Index if configured
        if ($this->bidxColumn) {
            $data[$this->bidxColumn] = $service->computeBlindIndex($value);
        }

        return $data;
    }
}
