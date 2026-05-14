<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectChannel extends Model
{
    protected $fillable = [
        'name',
        'type',
        'is_active',
        'business_id',
        'campaign_id',
        'woo_api_url',
        'woo_consumer_key',
        'woo_consumer_secret',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function posts()
    {
        return $this->hasMany(TelegramPost::class, 'direct_channel_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['is_enabled', 'last_synced_at', 'last_error'])
            ->withTimestamps();
    }
}
