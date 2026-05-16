<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntrySignature extends Model
{
    protected $fillable = [
        'user_id',
        'passkey_id',
        'l1_address',
        'assertion',
        'ip_address',
        'user_agent',
        'signed_at',
    ];

    protected $casts = [
        'assertion' => 'array',
        'signed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
