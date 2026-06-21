<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
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
        $username = $this->publicUsername();
        if ($username !== null) {
            return $username;
        }

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

        $suffix = strtoupper((string) data_get(
            $this->meta,
            'profile_suffix',
            substr((string) $this->sovereignIdentityAddress(), -6) ?: substr(hash('crc32b', (string) $this->id), -4),
        ));

        return "Meanly Profile {$suffix}";
    }

    public function publicUsername(): ?string
    {
        $username = trim((string) $this->username);

        return $username !== '' ? '@'.$username : null;
    }

    public function sovereignIdentityAddress(): ?string
    {
        $address = (string) (
            $this->entity_l1_address
            ?: data_get($this->meta, 'entity_l1_address')
            ?: data_get($this->meta, 'l1_address')
            ?: ''
        );

        return preg_match('/^sl1e_[a-f0-9]{39}$/i', $address) ? $address : null;
    }

    public function sovereignKeyAddress(): ?string
    {
        $address = (string) (
            $this->key_l1_address
            ?: data_get($this->meta, 'key_l1_address')
            ?: ''
        );

        return preg_match('/^sl1_[a-f0-9]{40}$/i', $address) ? $address : null;
    }

    public function hasSovereignIdentity(): bool
    {
        return $this->sovereignIdentityAddress() !== null;
    }

    public function getEmailAttribute(): ?string
    {
        return null;
    }

    public function setEmailAttribute(mixed $value): void
    {
        // Users are wallet principals; business contact email belongs to legal_entities.
    }

    public function setPasswordAttribute(mixed $value): void
    {
        // Password login has been retired for wallet principals.
    }

    public function setEmailVerifiedAtAttribute(mixed $value): void
    {
        // Kept as a no-op for legacy factories and tests.
    }

    public function setPasswordLoginEnabledAttribute(mixed $value): void
    {
        // Kept as a no-op for legacy factories and tests.
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

        return null;
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
        'ym_user_id',
        'username',
        'username_key',
        'meta',
        'source_site',
        'source_user_id',
        'theme',
        'entity_l1_address',
        'key_l1_address',
        'identity_provider',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
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
                $user->assignRole(static::ROLE_WALLET_HOLDER);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'meta' => \App\Casts\VaultEncryptedJson::class,
            'phone' => \App\Casts\VaultEncrypted::class . ':phone_bidx',
            'first_name' => \App\Casts\VaultEncrypted::class . ':first_name_bidx',
            'last_name' => \App\Casts\VaultEncrypted::class . ':last_name_bidx',
            'middle_name' => \App\Casts\VaultEncrypted::class . ':middle_name_bidx',
            'entity_l1_address' => \App\Casts\VaultEncrypted::class . ':entity_l1_address_bidx',
            'key_l1_address' => \App\Casts\VaultEncrypted::class . ':key_l1_address_bidx',
        ];
    }

    public const ROLE_SOVEREIGN_VALIDATOR = 'sovereign_validator';
    public const ROLE_PROTOCOL_OPERATOR = 'protocol_operator';
    public const ROLE_EXECUTION_RELAYER = 'execution_relayer';
    public const ROLE_SUPPORT_GUARDIAN = 'support_guardian';
    public const ROLE_LEDGER_AUDITOR = 'ledger_auditor';
    public const ROLE_SIGNAL_WATCHER = 'signal_watcher';
    public const ROLE_LIQUIDITY_STEWARD = 'liquidity_steward';
    public const ROLE_KERNEL_MAINTAINER = 'kernel_maintainer';
    public const ROLE_MERCHANT_NODE = 'merchant_node';
    public const ROLE_WALLET_HOLDER = 'wallet_holder';

    public const LEGACY_ROLE_RENAMES = [
        'super_admin' => self::ROLE_SOVEREIGN_VALIDATOR,
        'manager' => self::ROLE_PROTOCOL_OPERATOR,
        'executor' => self::ROLE_EXECUTION_RELAYER,
        'support' => self::ROLE_SUPPORT_GUARDIAN,
        'auditor' => self::ROLE_LEDGER_AUDITOR,
        'telemetry_monitor' => self::ROLE_SIGNAL_WATCHER,
        'treasurer' => self::ROLE_LIQUIDITY_STEWARD,
        'system_engineer' => self::ROLE_KERNEL_MAINTAINER,
        'b2b_partner' => self::ROLE_MERCHANT_NODE,
        'customer' => self::ROLE_WALLET_HOLDER,
    ];

    public const SYSTEM_ROLES = [
        self::ROLE_SOVEREIGN_VALIDATOR,
        self::ROLE_PROTOCOL_OPERATOR,
        self::ROLE_EXECUTION_RELAYER,
        self::ROLE_SUPPORT_GUARDIAN,
        self::ROLE_LEDGER_AUDITOR,
        self::ROLE_SIGNAL_WATCHER,
        self::ROLE_LIQUIDITY_STEWARD,
        self::ROLE_KERNEL_MAINTAINER,
    ];

    public const PARTNER_ROLES = [
        self::ROLE_MERCHANT_NODE,
    ];

    public function isMerchantNode(): bool
    {
        return $this->hasAnyRole(static::PARTNER_ROLES);
    }

    public function isWalletHolder(): bool
    {
        return $this->hasRole(static::ROLE_WALLET_HOLDER);
    }

    public function isSystemUser(): bool
    {
        return $this->hasAnyRole(static::SYSTEM_ROLES);
    }

    public function hasOpsSovereignAccess(): bool
    {
        return $this->hasRole(static::ROLE_SOVEREIGN_VALIDATOR) && $this->hasSovereignIdentity();
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

    public static function findByEntityL1Address(?string $address): ?self
    {
        $address = Str::lower(trim((string) $address));
        if (! preg_match('/^sl1e_[a-f0-9]{39}$/', $address)) {
            return null;
        }

        $salt = config('vault.blind_index.salt', 'default-salt');
        $bidx = hash_hmac('sha256', $address, $salt);

        return static::where('entity_l1_address_bidx', $bidx)->first();
    }

    public static function usernameCandidateFromEntityAddress(?string $entityAddress): ?string
    {
        $address = Str::lower(trim((string) $entityAddress));
        if (! preg_match('/^sl1e_[a-f0-9]{39}$/', $address)) {
            return null;
        }

        return static::normalizeUsername('sl1e_'.substr($address, -6));
    }

    public static function normalizeUsername(mixed $value): ?string
    {
        $username = Str::lower(trim((string) $value));
        if ($username === '') {
            return null;
        }

        $username = preg_replace('/^@+/', '', $username) ?: $username;
        $username = preg_replace('/\.sl1\.one$/i', '', $username) ?: $username;
        $username = preg_replace('/@(simplelayer\.one|sl1)$/i', '', $username) ?: $username;
        if (str_contains($username, '@')) {
            $username = explode('@', $username, 2)[0];
        }

        $username = Str::ascii($username);
        $username = preg_replace('/[^a-z0-9._]+/', '_', $username) ?: '';
        $username = preg_replace('/[._]{2,}/', '_', $username) ?: '';
        $username = trim($username, '._');
        $username = substr($username, 0, 32);

        if (! preg_match('/^[a-z0-9][a-z0-9._]{2,31}$/', $username)) {
            return null;
        }

        return $username;
    }

    public static function makeUniqueUsername(mixed $candidate, ?int $ignoreUserId = null): ?string
    {
        $base = static::normalizeUsername($candidate);
        if ($base === null) {
            return null;
        }

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $username = $attempt === 0
                ? $base
                : substr($base, 0, max(3, 32 - strlen((string) ($attempt + 1)) - 1)).'_'.($attempt + 1);

            $query = static::where('username_key', $username);
            if ($ignoreUserId !== null) {
                $query->whereKeyNot($ignoreUserId);
            }

            if (! $query->exists()) {
                return $username;
            }
        }

        return null;
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

    public function vaultIdentities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaultIdentity::class, 'owner_user_id');
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
        if (isset($this->meta['nickname']) && !request()->is('merchant*') && !request()->is('partner*') && !request()->is('admin*')) {
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
