<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommerceEntityLink extends Model
{
    public const TYPE_CANONICAL_IDENTITY = 'canonical_identity';
    public const TYPE_PROVIDER_PRODUCT = 'provider_product';
    public const TYPE_PRODUCT = 'product';

    protected $fillable = [
        'commerce_entity_id',
        'link_type',
        'link_id',
        'confidence',
        'signals',
    ];

    protected $casts = [
        'confidence' => 'float',
        'signals' => 'array',
    ];

    public function commerceEntity(): BelongsTo
    {
        return $this->belongsTo(CommerceEntity::class);
    }
}
