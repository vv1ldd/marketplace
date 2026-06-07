<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontFavorite extends Model
{
    protected $fillable = [
        'entity_l1_address',
        'product_slug',
        'product_name',
        'category_slug',
        'category_label',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
