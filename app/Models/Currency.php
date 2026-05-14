<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'rate_to_rub',      // RUB as Numéraire (Reference only)
        'base_asset',       // USDT (Universal Base)
        'quote_asset',      // Local Fiat (e.g. TRY)
        'price_last',       // 45.63 for USDT/TRY
        'official_rate',
        'official_rate_usdt',
        'spread_percent',
        'p2p_bybit',
        'p2p_binance',
        'p2p_source',
        'p2p_rate_usdt',
        'spot_rate_usdt',
        'exchange_coverage',
        'liquidity_stress_index',
        'is_shadow',
        'shadow_source',
        'manual_rate',
        'shadow_buy_rate',
        'shadow_sell_rate',
        'is_auto_update',
        'has_spot_liquidity',
        'inbound_methods',
        'outbound_methods',
        
        // Decomposed Observability
        'observability_score', 
        'obs_agreement',      // Consensus cross-source agreement
        'obs_freshness',      // Weighted latency/age score
        'obs_stability',      // Historical spread stability
        
        // Market Intelligence
        'market_regime',      // STABLE_PEG, DIVERGENT, THIN, DARK, VOLATILE
        'execution_ready',    // Boolean: can we trade this right now?
        'corridors',          // JSON: Liquidity topology (Directional paths)
        
        // Volatility Dimensions
        'volatility_1h',
        'volatility_24h',
        
        'telemetry_signals',
        'trust_tier',
        'confidence_score',   // Probabilistic: P(execution)
        'max_executable_size',
        'estimated_slippage',
        'settlement_time_hours',
        'cashout_probability',
        'telemetry_count_48h',
        'tradfi_rate',
        'tradfi_source',
    ];

    protected $casts = [
        'rate_to_rub' => 'decimal:10',
        'price_last' => 'decimal:10',
        'official_rate' => 'decimal:10',
        'tradfi_rate' => 'decimal:10',
        'spread_percent' => 'decimal:4',
        'volatility_1h' => 'decimal:4',
        'volatility_24h' => 'decimal:4',
        'exchange_coverage' => 'array',
        'is_shadow'         => 'boolean',
        'is_auto_update'    => 'boolean',
        'has_spot_liquidity' => 'boolean',
        'execution_ready'    => 'boolean',
        'inbound_methods'   => 'array',
        'outbound_methods'  => 'array',
        'telemetry_signals' => 'array',
        'corridors'         => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_auto_update', true);
    }

    public function histories()
    {
        return $this->hasMany(CurrencyHistory::class);
    }

    public function getEffectiveRateAttribute(): float
    {
        // For shadow currencies, manual_rate is the USD quote, so we use rate_to_rub for the final cross-rate
        if ($this->is_shadow) {
            return (float)($this->rate_to_rub ?? 1.0);
        }

        return (float)($this->manual_rate ?? $this->rate_to_rub ?? 1.0);
    }

    public function liquidityMethods(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(LiquidityMethod::class)
            ->withPivot(['direction', 'fee_percent', 'is_active'])
            ->withTimestamps();
    }
}
