<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        $metadataName = trim((string) data_get($this->meta, 'display_name', ''));
        if ($metadataName !== '') {
            return $metadataName;
        }

        $name = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        if ($name !== '') {
            return $name;
        }

        if ($this->email) {
            return (string) $this->email;
        }

        $suffix = strtoupper((string) data_get($this->meta, 'profile_suffix', substr(hash('crc32b', (string) $this->id), -4)));

        return "Meanly Profile {$suffix}";
    }

    public function sovereignIdentityAddress(): ?string
    {
        $address = (string) data_get($this->meta, 'entity_l1_address', data_get($this->meta, 'l1_address', ''));

        return preg_match('/^sl1e_[a-f0-9]{39}$/i', $address) ? $address : null;
    }

    public function hasSovereignIdentity(): bool
    {
        return $this->sovereignIdentityAddress() !== null;
    }

    public function primarySellerAccount(): ?Seller
    {
        $entity = $this->managedLegalEntities()
            ->whereNotNull('legal_entities.seller_id')
            ->latest('legal_entities.created_at')
            ->first();

        if ($entity?->seller_id) {
            return Seller::find($entity->seller_id);
        }

        return $this->email ? Seller::findByEmail($this->email) : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
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
        'password_login_enabled',
        'theme',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            if ($user->roles()->count() === 0) {
                $user->assignRole('customer');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_login_enabled' => 'boolean',
            'meta' => \App\Casts\VaultEncryptedJson::class,
            'email' => \App\Casts\VaultEncrypted::class . ':email_bidx',
            'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
            'first_name' => \App\Casts\VaultEncrypted::class . ':first_name_bidx',
            'last_name' => \App\Casts\VaultEncrypted::class . ':last_name_bidx',
            'middle_name' => \App\Casts\VaultEncrypted::class . ':middle_name_bidx',
        ];
    }

    const SYSTEM_ROLES = [
        'super_admin',
        'manager',
        'executor',
        'support',
        'auditor',
        'telemetry_monitor',
        'treasurer',
        'system_engineer',
    ];

    const PARTNER_ROLES = [
        'b2b_partner',
    ];

    public function isB2BPartner(): bool
    {
        return $this->hasAnyRole(static::PARTNER_ROLES);
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function isSystemUser(): bool
    {
        return $this->hasAnyRole(static::SYSTEM_ROLES);
    }

    public function hasOpsSovereignAccess(): bool
    {
        return $this->hasRole('super_admin') && $this->hasSovereignIdentity();
    }

    public function isClient(): bool
    {
        return ! $this->isSystemUser();
    }

    public function scopeSystem($query)
    {
        return $query->role(static::SYSTEM_ROLES);
    }

    public function scopeClients($query)
    {
        return $query->whereDoesntHave('roles', function ($q) {
            $q->whereIn('name', static::SYSTEM_ROLES);
        });
    }

    /**
     * @param string $phone
     * @return bool
     */
    public static function existByPhone(string $phone): bool
    {
        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($phone)), $salt);

        return static::where('phone_bidx', $bidx)->exists();
    }

    /**
     * Helper to find user by email using Blind Index
     */
    public static function findByEmail(?string $email): ?self
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', strtolower(trim($email)), $salt);

        return static::where('email_bidx', $bidx)->first();
    }

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function shops(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'orders', 'user_id', 'shop_id')->distinct();
    }

    public function managedShops(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user', 'user_id', 'shop_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function legalEntities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LegalEntity::class);
    }

    public function managedLegalEntities(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(LegalEntity::class, 'legal_entity_managers', 'user_id', 'legal_entity_id')
            ->withPivot('role', 'seller_id')
            ->withTimestamps();
    }

    public function canManageTenant(Model $tenant): bool
    {
        if ($tenant instanceof LegalEntity) {
            return $this->managedLegalEntities()->where('legal_entities.id', $tenant->id)->exists();
        }

        return $this->managedShops->contains($tenant);
    }

    public function getFullName(): string
    {
        // 🛍️ If we are in the B2C Customer context, prefer their original Nickname
        if (isset($this->meta['nickname']) && !request()->is('partner*') && !request()->is('admin*')) {
            return $this->meta['nickname'];
        }

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
