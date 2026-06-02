<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WildflowCreditReservation extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'amount',
        'reference',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
        'expires_at' => 'datetime',
    ];

    public function legalEntity(): BelongsTo
    {
        return $this->belongsTo(LegalEntity::class);
    }
}
