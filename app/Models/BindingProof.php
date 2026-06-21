<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BindingProof extends Model
{
    public const TYPE_USDC_TRANSFER = 'usdc_transfer';

    public const STATE_VERIFIED = 'verified';
    public const STATE_FAILED = 'failed';

    protected $fillable = [
        'vault_id',
        'identity_binding_id',
        'proof_type',
        'binding_key',
        'proof_reference',
        'verification_state',
        'proof_payload',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'proof_payload' => 'array',
        'metadata' => 'array',
        'verified_at' => 'datetime',
    ];

    public function vault(): BelongsTo
    {
        return $this->belongsTo(VaultIdentity::class, 'vault_id');
    }

    public function identityBinding(): BelongsTo
    {
        return $this->belongsTo(IdentityBinding::class, 'identity_binding_id');
    }

    public static function referenceFor(string $proofType, string $reference): string
    {
        return trim($proofType).':'.Str::lower(trim($reference));
    }

    public function payloadValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->proof_payload, $key, $default);
    }
}
