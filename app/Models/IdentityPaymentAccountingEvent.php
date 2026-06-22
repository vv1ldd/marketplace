<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IdentityPaymentAccountingEvent extends Model
{
    protected $fillable = [
        'identity_payment_intent_id',
        'sender_identity_id',
        'receiver_identity_id',
        'sender_binding_id',
        'receiver_binding_id',
        'asset',
        'amount',
        'network',
        'narrative',
        'settlement_reference',
        'metadata',
        'recorded_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(IdentityPaymentIntent::class, 'identity_payment_intent_id');
    }

    public function reconciliationRecord(): HasOne
    {
        return $this->hasOne(ReconciliationRecord::class, 'identity_payment_accounting_event_id');
    }
}
