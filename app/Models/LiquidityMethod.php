<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LiquidityMethod extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'icon',
        'description',
        'is_global',
        'is_active',
    ];

    protected $casts = [
        'is_global' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function currencies(): BelongsToMany
    {
        return $this->belongsToMany(Currency::class)
            ->withPivot(['direction', 'fee_percent', 'is_active'])
            ->withTimestamps();
    }

    protected static function booted()
    {
        static::created(function ($method) {
            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_METHOD_CREATED', $method, $method->toArray());
        });

        static::updated(function ($method) {
            $changes = $method->getChanges();
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_METHOD_UPDATED', $method, [
                'changes' => $changes,
                'original' => array_intersect_key($method->getOriginal(), $changes)
            ]);
        });

        static::deleted(function ($method) {
            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_METHOD_DELETED', $method, ['slug' => $method->slug]);
        });
    }
}
