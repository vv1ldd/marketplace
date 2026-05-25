<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoCollection extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'search_query',
        'meta_title',
        'meta_description',
        'h1',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
