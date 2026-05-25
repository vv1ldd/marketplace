<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SovereignBalanceRequest extends Model
{
    protected $fillable = [
        'legal_entity_id',
        'type', // 'top_up', 'grant_credit'
        'amount',
        'currency',
        'status', // 'pending', 'approved', 'rejected'
        'l1_address',
        'passkey_id',
        'signature_assertion',
        'comment',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'signature_assertion' => 'array',
        'approved_at' => 'datetime',
    ];

    public function legalEntity()
    {
        return $this->belongsTo(LegalEntity::class);
    }

    public function passkey()
    {
        return $this->belongsTo(\Spatie\LaravelPasskeys\Models\Passkey::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
