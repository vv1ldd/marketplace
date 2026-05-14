<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Order\Order;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'shop_id',
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'email',
        'password',
        'ym_user_id',
        'meta',
        'source_site',
        'source_user_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'id' => 'integer',
        'meta' => \App\Casts\VaultEncryptedJson::class,
        'password' => 'hashed',
        'email' => \App\Casts\VaultEncrypted::class . ':email_bidx',
        'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
        'first_name' => \App\Casts\VaultEncrypted::class . ':first_name_bidx',
        'last_name' => \App\Casts\VaultEncrypted::class . ':last_name_bidx',
        'middle_name' => \App\Casts\VaultEncrypted::class . ':middle_name_bidx',
    ];

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
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

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Find customer by email using Blind Index (works even when email is encrypted)
     */
    public static function findByEmail(string $email): ?self
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($email)), $salt);
        return static::where('email_bidx', $bidx)->first();
    }

    /**
     * Find customer by phone using Blind Index
     */
    public static function findByPhone(string $phone): ?self
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($phone)), $salt);
        return static::where('phone_bidx', $bidx)->first();
    }

    public function legalEntities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    public function getFullName(): string
    {
        $full_name = $this->first_name;

        if ($this->last_name) {
            $full_name .= ' ' . $this->last_name;
        }

        if ($this->middle_name) {
            $full_name .= ' ' . $this->middle_name;
        }

        return $full_name;
    }
}
