<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalEntity extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'short_name',
        'inn',
        'kpp',
        'ogrn',
        'legal_address',
        'postal_address',
        'bank_name',
        'bank_bic',
        'bank_account',
        'bank_correspondent_account',
        'director_name',
        'phone',
        'email',
        'is_active',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }
}
