<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SovereignLedger extends Model
{
    protected $table = 'sovereign_ledger';

    public $timestamps = false; // We use created_at only

    protected $fillable = [
        'shop_id',
        'legal_entity_id',
        'event_type',
        'entity_type',
        'entity_id',
        'payload',
        'trigger_source',
        'input_data',
        'output_state',
        'currency',
        'amount_base',
        'base_currency',
        'exchange_rate',
        'fingerprint',
        'previous_fingerprint',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'input_data' => 'array',
        'output_state' => 'array',
        'created_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
