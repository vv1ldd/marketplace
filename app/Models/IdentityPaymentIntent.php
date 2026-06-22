<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IdentityPaymentIntent extends Model
{
    public const STATUS_ROUTED = 'routed';

    public const STATUS_EXECUTING = 'executing';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'intent_uuid',
        'status',
        'sender_vault_id',
        'sender_identity_id',
        'sender_alias',
        'receiver_identity_id',
        'receiver_alias',
        'asset',
        'amount',
        'amount_wei',
        'sender_binding_id',
        'receiver_binding_id',
        'network',
        'routing_policy',
        'routing_metadata',
        'recipient_resolution_snapshot',
        'settlement_reference',
        'idempotency_key',
        'reversal_of_intent_id',
        'reversal_reason',
        'metadata',
        'routed_at',
        'executed_at',
        'failed_at',
    ];

    protected $casts = [
        'routing_metadata' => 'array',
        'recipient_resolution_snapshot' => 'array',
        'metadata' => 'array',
        'routed_at' => 'datetime',
        'executed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function senderBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'sender_binding_id');
    }

    public function receiverBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'receiver_binding_id');
    }

    public function accountingEvent(): HasOne
    {
        return $this->hasOne(IdentityPaymentAccountingEvent::class, 'identity_payment_intent_id');
    }

    public function settlementAttempts(): HasMany
    {
        return $this->hasMany(SettlementAttempt::class, 'identity_payment_intent_id')
            ->orderBy('attempt_no');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_intent_id');
    }

    public function reversalIntent(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_intent_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(IdentityPaymentDispute::class, 'identity_payment_intent_id');
    }
}
