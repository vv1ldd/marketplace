<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CanonicalProductIdentity extends Model
{
    protected $fillable = [
        'fingerprint',
        'identity_slug',
        'canonical_category',
        'brand',
        'product_family',
        'face_value',
        'face_value_currency',
        'region',
        'platform',
        'confidence',
        'signals',
        'provider_candidates_count',
        'seller_offers_count',
        'best_offer_product_id',
        'last_seen_at',
    ];

    protected $casts = [
        'face_value' => 'decimal:4',
        'signals' => 'array',
        'provider_candidates_count' => 'integer',
        'seller_offers_count' => 'integer',
        'last_seen_at' => 'datetime',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(CanonicalProductIdentitySource::class);
    }

    public function bestOfferProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'best_offer_product_id');
    }

    public function override(): HasOne
    {
        return $this->hasOne(CanonicalProductIdentityOverride::class, 'fingerprint', 'fingerprint');
    }
}
