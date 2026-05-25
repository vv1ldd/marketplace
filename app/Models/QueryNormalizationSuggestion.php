<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueryNormalizationSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'source',
        'target',
        'confidence',
        'reason',
        'status',
    ];

    protected $casts = [
        'confidence' => 'float',
    ];
}
