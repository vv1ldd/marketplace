<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementAttempt extends Model
{
    public const STATUS_CREATED = 'created';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'identity_payment_intent_id',
        'attempt_no',
        'routing_snapshot_ref',
        'network',
        'binding_from',
        'binding_to',
        'status',
        'failure_reason',
        'tx_reference',
        'submitted_at',
        'confirmed_at',
        'failed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function intent(): BelongsTo
    {
        return $this->belongsTo(IdentityPaymentIntent::class, 'identity_payment_intent_id');
    }
}
