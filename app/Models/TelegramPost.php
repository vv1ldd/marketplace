<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramPost extends Model
{
    protected $fillable = [
        'direct_channel_id',
        'wildflow_catalog_id',
        'product_id',
        'message_id',
        'clicks',
        'purchases',
        'posted_price',
    ];

    public function channel()
    {
        return $this->belongsTo(DirectChannel::class, 'direct_channel_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function wildflowCatalog()
    {
        return $this->belongsTo(WildflowCatalog::class, 'wildflow_catalog_id');
    }
}
