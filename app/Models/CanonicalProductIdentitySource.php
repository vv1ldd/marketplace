<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalProductIdentitySource extends Model
{
    public const SOURCE_PROVIDER_PRODUCT = 'provider_product';

    public const SOURCE_PRODUCT = 'product';

    protected $fillable = [
        'canonical_product_identity_id',
        'source_type',
        'source_id',
        'source_sku',
        'confidence',
        'signals',
        'last_seen_at',
    ];

    protected $casts = [
        'signals' => 'array',
        'last_seen_at' => 'datetime',
    ];

    public function canonicalProductIdentity(): BelongsTo
    {
        return $this->belongsTo(CanonicalProductIdentity::class);
    }
}
