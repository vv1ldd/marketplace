<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected static function booted(): void
    {
        static::updated(function ($provider) {
            $changes = $provider->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            // 🛡️ Security: Mask sensitive credential changes (Keys only!)
            if (isset($changes['credentials'])) {
                $creds = $provider->credentials;
                $changes['credentials'] = is_array($creds) ? array_keys($creds) : 'PROTECTED_JSON';
            }

            app(\App\Services\LedgerService::class)->recordGlobal('PROVIDER_UPDATED', $provider, [
                'type' => $provider->type,
                'changes' => $changes,
            ]);
        });
    }

    protected $fillable = [
        'name',
        'type',
        'is_active',
        'sync_status',
        'credentials',
        'settings',
        'compliance_rules',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => \App\Casts\VaultEncryptedJson::class,
        'settings' => \App\Casts\VaultEncryptedJson::class,
        'compliance_rules' => 'array',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get marketplace products associated with this provider
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'provider_id');
    }

    /**
     * Get raw raw source products from this provider
     */
    public function providerProducts()
    {
        return $this->hasMany(ProviderProduct::class, 'provider_id');
    }
}
