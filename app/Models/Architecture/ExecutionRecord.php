<?php

namespace App\Models\Architecture;

use App\Models\CanonicalProductIdentity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionRecord extends Model
{
    public const STATE_RESERVED = 'reserved';

    public const STATE_FULFILLING = 'fulfilling';

    public const STATE_ISSUED = 'issued';

    public const STATE_FAILED = 'failed';

    public const STATE_MANUAL = 'manual';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'intent_id',
        'canonical_product_identity_id',
        'offer_snapshot_id',
        'order_id',
        'order_item_id',
        'provider_id',
        'idempotency_key',
        'provider_order_id',
        'state',
        'error_class',
        'vault_reference_id',
        'audit_payload',
    ];

    protected $casts = [
        'audit_payload' => 'array',
    ];

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(CanonicalProductIdentity::class, 'canonical_product_identity_id');
    }

    public function offerSnapshot(): BelongsTo
    {
        return $this->belongsTo(OfferSnapshot::class, 'offer_snapshot_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItems::class, 'order_item_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->state, [self::STATE_ISSUED, self::STATE_FAILED, self::STATE_MANUAL], true);
    }
}
