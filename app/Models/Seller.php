<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class Seller extends Authenticatable implements FilamentUser, HasName, HasTenants, HasPasskeys
{
    use HasFactory, Notifiable, HasRoles, InteractsWithPasskeys;

    public function getPassKeyDisplayName(): string
    {
        return $this->first_name ? "@{$this->first_name}" : ($this->email ?? "Seller #{$this->id}");
    }

    public function getPassKeyId(): string
    {
        return (string) $this->id;
    }

    public function getPassKeyName(): string
    {
        return $this->first_name ? "@{$this->first_name}" : ($this->email ?? "seller-{$this->id}");
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone',
        'email',
        'password',
        'is_active',
        'password_login_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'password_login_enabled' => 'boolean',
        'email' => \App\Casts\VaultEncrypted::class . ':email_bidx',
        'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
        'first_name' => \App\Casts\VaultEncrypted::class . ':first_name_bidx',
        'last_name' => \App\Casts\VaultEncrypted::class . ':last_name_bidx',
        'middle_name' => \App\Casts\VaultEncrypted::class . ':middle_name_bidx',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Sellers can only access the partner panel
        return $panel->getId() === 'partner';
    }

    public function getFilamentName(): string
    {
        return $this->first_name ?? 'Продавец';
    }

    public static function findByEmail(string $email): ?self
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($email)), $salt);
        return static::where('email_bidx', $bidx)->first();
    }

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

    public function getTenants(Panel $panel): Collection
    {
        return $this->managedLegalEntities;
    }

    public function canAccessTenant(Model $tenant): bool
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
