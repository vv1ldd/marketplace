<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'shop_id',
        'ym_id',
        'name',
        'type',
        'is_active',
        'is_main',
        'channel',
        'channel_quota',
        'data',
    ];

    protected $casts = [
        'data'          => 'array',
        'is_active'     => 'boolean',
        'is_main'       => 'boolean',
        'ym_id'         => 'integer',
        'channel_quota' => 'integer',
    ];

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeMaster(Builder $query): Builder
    {
        return $query->where('is_main', true)->whereNull('channel');
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopeChannelWarehouses(Builder $query): Builder
    {
        return $query->whereNotNull('channel');
    }

    // ─── Relations ───────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            LegalEntity::class,
            Shop::class,
            'id', // Foreign key on shops table...
            'id', // Foreign key on legal_entities table...
            'shop_id', // Local key on warehouses table...
            'legal_entity_id' // Local key on shops table...
        );
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Человекочитаемый лейбл канала.
     */
    public function getChannelLabelAttribute(): string
    {
        if ($this->is_main) return 'Мастер-склад';

        return match ($this->channel) {
            'yandex_market' => '🟡 Яндекс Маркет',
            'ozon'          => '🔵 Ozon',
            'wildberries'   => '🟣 Wildberries',
            'avito'         => '🟢 Авито',
            default         => $this->channel ?? '—',
        };
    }
}
