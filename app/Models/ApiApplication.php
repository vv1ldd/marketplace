<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiApplication extends Model
{
    const TYPE_SHOP = 'shop';
    const TYPE_PLATFORM = 'platform';

    protected $fillable = [
        'shop_id',
        'type',
        'name',
        'first_name',
        'last_name',
        'phone',
        'domain',
        'token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Generate a new unique token for the application.
     */
    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
