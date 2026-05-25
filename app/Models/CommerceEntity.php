<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommerceEntity extends Model
{
    protected $fillable = [
        'slug',
        'entity_type',
        'attributes',
        'canonical_query',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(CommerceEntityLink::class);
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(CommerceEntityMetric::class);
    }
}
