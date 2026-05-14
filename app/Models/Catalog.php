<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Catalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'shop_id',
    ];

    /**
     * Get the shop that owns the catalog.
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the products in this catalog.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if this is the global catalog.
     */
    public function isGlobal(): bool
    {
        return $this->type === 'global';
    }

    /**
     * Check if this is a shop's catalog.
     */
    public function isShop(): bool
    {
        return $this->type === 'shop';
    }
}
