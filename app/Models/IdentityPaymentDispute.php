<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityPaymentDispute extends Model
{
    public const STATUS_OPENED = 'opened';

    public const STATUS_EVIDENCE_REQUESTED = 'evidence_requested';

    public const STATUS_EVIDENCE_COLLECTED = 'evidence_collected';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_RESOLVED = 'resolved';

    public const EVENT_OPENED = 'DisputeOpened';

    public const EVENT_EVIDENCE_REQUESTED = 'EvidenceRequested';

    public const EVENT_EVIDENCE_COLLECTED = 'EvidenceCollected';

    public const EVENT_REVIEWED = 'Reviewed';

    public const EVENT_RESOLVED = 'Resolved';

    public const OUTCOME_REFUND_APPROVED = 'refund_approved';

    public const OUTCOME_REFUND_DENIED = 'refund_denied';

    public const OUTCOME_NO_ACTION = 'no_action';

    public const DECISION_APPROVED = 'approved';

    public const DECISION_REJECTED = 'rejected';

    public const DECISION_NO_ACTION = 'no_action';

    protected $fillable = [
        'dispute_uuid',
        'identity_payment_intent_id',
        'opened_by_identity_id',
        'opened_by_alias',
        'reason',
        'status',
        'evidence_required',
        'evidence_snapshot',
        'lifecycle_log',
        'resolution',
        'compensation_intent_id',
        'opened_at',
        'resolved_at',
    ];

    protected $casts = [
        'evidence_required' => 'boolean',
        'evidence_snapshot' => 'array',
        'lifecycle_log' => 'array',
        'resolution' => 'array',
        'opened_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(IdentityPaymentIntent::class, 'identity_payment_intent_id');
    }

    public function compensationIntent(): BelongsTo
    {
        return $this->belongsTo(IdentityPaymentIntent::class, 'compensation_intent_id');
    }
}
