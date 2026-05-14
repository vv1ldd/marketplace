<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiApplication extends Model
{
    const TYPE_SHOP = 'shop';
    const TYPE_PLATFORM = 'platform';

    protected $fillable = [
        'shop_id',
        'type',
        'name',
        'first_name',
        'last_name',
        'phone',
        'domain',
        'token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token' => \App\Casts\VaultEncrypted::class . ':token_bidx',
        'first_name' => \App\Casts\VaultEncrypted::class,
        'last_name' => \App\Casts\VaultEncrypted::class,
        'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
    ];

    /**
     * Generate a new unique token for the application.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            LegalEntity::class,
            Shop::class,
            'id',
            'id',
            'shop_id',
            'legal_entity_id'
        );
    }
}
