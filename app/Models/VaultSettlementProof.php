<?php

namespace App\Models;

use App\Support\SettlementProofLifecycle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VaultSettlementProof extends Model
{
    public const KIND_USDC_TRANSFER = 'usdc_transfer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_OBSERVED = 'observed';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CREDITED = 'credited';

    protected $fillable = [
        'vault_id',
        'identity_binding_id',
        'rail',
        'external_reference',
        'proof_kind',
        'asset',
        'amount',
        'recipient',
        'status',
        'observed_at',
        'verified_at',
        'failed_at',
        'evidence',
        'metadata',
    ];

    protected $casts = [
        'evidence' => 'array',
        'metadata' => 'array',
        'observed_at' => 'datetime',
        'verified_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function identityBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'identity_binding_id');
    }

    public static function externalReferenceFor(string $proofKind, string $reference): string
    {
        return trim($proofKind).':'.Str::lower(trim($reference));
    }

    public function transitionTo(string $status): self
    {
        $status = Str::lower(trim($status));

        if (! SettlementProofLifecycle::canTransition((string) $this->status, $status)) {
            throw ValidationException::withMessages([
                'status' => "Settlement proof cannot transition from [{$this->status}] to [{$status}].",
            ]);
        }

        $now = now();
        $attributes = ['status' => $status];

        if ($status === self::STATUS_OBSERVED && $this->observed_at === null) {
            $attributes['observed_at'] = $now;
        }

        if ($status === self::STATUS_VERIFIED && $this->verified_at === null) {
            $attributes['verified_at'] = $now;
        }

        if ($status === self::STATUS_FAILED && $this->failed_at === null) {
            $attributes['failed_at'] = $now;
        }

        $this->forceFill($attributes)->save();

        return $this->refresh();
    }

    public function evidenceValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->evidence, $key, $default);
    }
}
