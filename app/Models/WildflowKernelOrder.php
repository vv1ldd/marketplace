<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WildflowKernelOrder extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'provider',
        'marketplace_reference',
        'proxy_reference',
        'vendor_reference',
        'service_sku',
        'price',
        'currency',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'price' => 'decimal:4',
        'request_payload' => \App\Casts\VaultEncryptedJson::class,
        'response_payload' => \App\Casts\VaultEncryptedJson::class,
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
