<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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

class User extends Authenticatable implements FilamentUser, HasName, HasTenants
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
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
            'meta' => 'array',
        ];
    }

    const SYSTEM_ROLES = [
        'super_admin',
        'manager',
        'executor',
        'support',
        'b2b_partner',
    ];

    public function isB2BPartner(): bool
    {
        return $this->hasRole('b2b_partner');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSystemUser();
    }

    public function isSystemUser(): bool
    {
        return $this->hasAnyRole(static::SYSTEM_ROLES);
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

    public function getFilamentName(): string
    {
        return $this->first_name;
    }

    /**
     * @param string $phone
     * @return bool
     */
    public static function existByPhone(string $phone): bool
    {


        return static::where('phone', $phone)->exists();
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

    public function getTenants(Panel $panel): Collection
    {
        return $this->managedShops;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->managedShops->contains($tenant);
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
