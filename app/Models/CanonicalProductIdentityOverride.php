<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonicalProductIdentityOverride extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IGNORED = 'ignored';

    /**
     * @var array<int, string>
     */
    public const REVIEW_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_IGNORED,
    ];

    protected $fillable = [
        'canonical_product_identity_id',
        'fingerprint',
        'brand',
        'product_family',
        'face_value',
        'face_value_currency',
        'region',
        'platform',
        'canonical_category',
        'confidence',
        'review_status',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'signals',
        'metadata',
    ];

    protected $casts = [
        'face_value' => 'decimal:4',
        'reviewed_at' => 'datetime',
        'signals' => 'array',
        'metadata' => 'array',
    ];

    public function canonicalProductIdentity(): BelongsTo
    {
        return $this->belongsTo(CanonicalProductIdentity::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
