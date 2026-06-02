<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketplaceTransitionOutbox extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_RETRY_WAIT = 'retry_wait';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD_LETTERED = 'dead_lettered';
    public const STATUS_SUPERSEDED = 'superseded';

    public const ANCHOR_PENDING = 'pending';
    public const ANCHOR_VERIFIED = 'verified';
    public const ANCHOR_FAILED = 'failed';

    protected $table = 'marketplace_transition_outbox';

    protected $fillable = [
        'event_uuid',
        'scope',
        'aggregate_type',
        'aggregate_id',
        'transition_type',
        'transition_id',
        'transition_hash',
        'authority_decision_id',
        'authority_decision_hash',
        'idempotency_key',
        'payload',
        'payload_hash',
        'anchor_status',
        'anchor_hash',
        'status',
        'attempts',
        'available_at',
        'processed_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => \App\Casts\VaultEncryptedJson::class,
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
