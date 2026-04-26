<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_active',
        'credentials',
        'settings',
        'last_sync_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'array',
        'settings' => 'array',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Get products associated with this provider
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'type', 'type');
    }
}
