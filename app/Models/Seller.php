<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class Seller extends Authenticatable implements HasPasskeys
{
    use HasFactory, Notifiable, HasRoles, InteractsWithPasskeys;

    public function getPassKeyDisplayName(): string
    {
        return $this->profileDisplayName();
    }

    public function getPassKeyId(): string
    {
        return (string) $this->id;
    }

    public function getPassKeyName(): string
    {
        return $this->profileDisplayName();
    }

    public function profileDisplayName(): string
    {
        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        if ($name !== '') {
            return $name;
        }

        return "Meanly Seller ".strtoupper(substr(hash('crc32b', (string) $this->id), -4));
    }

    public function getEmailAttribute(): ?string
    {
        return null;
    }

    public function setEmailAttribute(mixed $value): void
    {
        // Business contact email belongs to legal_entities, not seller auth identity.
    }

    public function setPasswordAttribute(mixed $value): void
    {
        // Password login has been retired for seller guard identities.
    }

    public function setEmailVerifiedAtAttribute(mixed $value): void
    {
        // Kept as a no-op for legacy factories and tests.
    }

    public function setPasswordLoginEnabledAttribute(mixed $value): void
    {
        // Kept as a no-op for legacy factories and tests.
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
        'first_name' => \App\Casts\VaultEncrypted::class . ':first_name_bidx',
        'last_name' => \App\Casts\VaultEncrypted::class . ':last_name_bidx',
        'middle_name' => \App\Casts\VaultEncrypted::class . ':middle_name_bidx',
    ];

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

    public function managedLegalEntities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(LegalEntity::class, 'legal_entity_managers', 'seller_id', 'legal_entity_id')
            ->withPivot('role', 'user_id')
            ->withTimestamps();
    }

    public function canManageTenant(Model $tenant): bool
    {
        if ($tenant instanceof LegalEntity) {
            return $this->managedLegalEntities()->where('legal_entities.id', $tenant->id)->exists();
        }

        return false;
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
