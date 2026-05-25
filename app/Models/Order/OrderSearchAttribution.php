<?php

namespace App\Models\Order;

use App\Models\CatalogSearchLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderSearchAttribution extends Model
{
    protected $table = 'order_search_attributions';

    protected $fillable = [
        'order_id',
        'search_log_id',
        'touch_type',
        'attribution_weight',
        'attributed_gmv',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'search_log_id' => 'integer',
        'attribution_weight' => 'float',
        'attributed_gmv' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function searchLog(): BelongsTo
    {
        return $this->belongsTo(CatalogSearchLog::class, 'search_log_id');
    }
}
