<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TokenMeteringEvent extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'shop_id',
        'sovereign_ledger_id',
        'event_type',
        'layer',
        'tariff_key',
        'tariff_version',
        'idempotency_key',
        'source_type',
        'source_id',
        'quantity',
        'unit',
        'sl1_amount',
        'rub_equivalent',
        'estimated_value_rub',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'sl1_amount' => 'decimal:4',
        'rub_equivalent' => 'decimal:2',
        'estimated_value_rub' => 'decimal:2',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function sovereignLedger(): BelongsTo
    {
        return $this->belongsTo(SovereignLedger::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
