<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalProductSearchProfile extends Model
{
    protected $fillable = [
        'canonical_product_identity_id',
        'search_text',
        'search_tokens',
        'search_aliases',
        'search_metadata',
        'profile_version',
        'last_rebuild_at',
        'last_error',
    ];

    protected $casts = [
        'search_tokens' => 'array',
        'search_aliases' => 'array',
        'search_metadata' => 'array',
        'profile_version' => 'integer',
        'last_rebuild_at' => 'datetime',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(CanonicalProductIdentity::class, 'canonical_product_identity_id');
    }
}
