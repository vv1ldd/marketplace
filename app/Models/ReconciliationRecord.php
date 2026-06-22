<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconciliationRecord extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'identity_payment_accounting_event_id',
        'settlement_attempt_id',
        'identity_from_match',
        'identity_to_match',
        'asset_match',
        'amount_match',
        'status',
        'evidence',
    ];

    protected $casts = [
        'identity_from_match' => 'boolean',
        'identity_to_match' => 'boolean',
        'asset_match' => 'boolean',
        'amount_match' => 'boolean',
        'evidence' => 'array',
    ];

    public function accountingEvent(): BelongsTo
    {
        return $this->belongsTo(IdentityPaymentAccountingEvent::class, 'identity_payment_accounting_event_id');
    }

    public function settlementAttempt(): BelongsTo
    {
        return $this->belongsTo(SettlementAttempt::class, 'settlement_attempt_id');
    }
}
