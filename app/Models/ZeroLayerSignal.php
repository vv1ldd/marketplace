<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZeroLayerSignal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'signal_date' => 'date',
        'position' => 'decimal:4',
        'impressions' => 'decimal:4',
        'clicks' => 'decimal:4',
        'link_clicks' => 'decimal:4',
        'cost' => 'decimal:4',
        'conversions' => 'decimal:4',
        'revenue' => 'decimal:4',
        'roas' => 'decimal:4',
        'video_views' => 'decimal:4',
        'video_watched_6s' => 'decimal:4',
        'payload' => 'array',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(ZeroLayerIntegration::class, 'zero_layer_integration_id');
    }
}
