<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrencyPair extends Model
{
    protected $fillable = [
        'base_currency_id',
        'target_currency_id',
        'official_rate',
        'tradfi_rate',
        'spot_rate',
        'p2p_rate',
        'p2p_buy_rate',
        'p2p_sell_rate',
        'spread_percent',
        'liquidity_score',
        'is_active',
    ];

    protected $casts = [
        'official_rate' => 'decimal:10',
        'tradfi_rate' => 'decimal:10',
        'spot_rate' => 'decimal:10',
        'p2p_rate' => 'decimal:10',
        'p2p_buy_rate' => 'decimal:10',
        'p2p_sell_rate' => 'decimal:10',
        'spread_percent' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function targetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency_id');
    }

    /**
     * Get the display name of the pair (e.g. RUB/TRY)
     */
    public function getNameAttribute(): string
    {
        return "{$this->baseCurrency->code}/{$this->targetCurrency->code}";
    }
}
