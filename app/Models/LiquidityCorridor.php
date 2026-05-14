<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidityCorridor extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    protected static function booted()
    {
        static::created(function ($corridor) {
            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_CORRIDOR_CREATED', $corridor, $corridor->toArray());
        });

        static::updated(function ($corridor) {
            $changes = $corridor->getChanges();
            // Don't record if only timestamps changed
            unset($changes['updated_at']);
            if (empty($changes)) return;

            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_CORRIDOR_UPDATED', $corridor, [
                'changes' => $changes,
                'original' => array_intersect_key($corridor->getOriginal(), $changes)
            ]);
        });

        static::deleted(function ($corridor) {
            app(\App\Services\LedgerService::class)->recordGlobal('LIQUIDITY_CORRIDOR_DELETED', $corridor, [
                'node' => $corridor->provider_node,
                'currency' => $corridor->currency_code
            ]);
        });
    }
}
