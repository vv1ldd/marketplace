<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'count',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'count' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function booted()
    {
        static::saved(function ($stock) {
            $warehouse = $stock->warehouse;
            if ($warehouse && $warehouse->shop_id) {
                \App\Jobs\SyncProductStockToChannels::dispatch(
                    $stock->product_id,
                    $warehouse->shop_id
                )->afterResponse();

                // 🛡️ Sovereign Ledger: Record stock change
                $changes = $stock->getChanges();
                if (isset($changes['count'])) {
                    app(\App\Services\LedgerService::class)->record($warehouse->shop, 'STOCK_ADJUSTMENT', $stock->product, [
                        'warehouse' => $warehouse->name,
                        'old_count' => $stock->getOriginal('count'),
                        'new_count' => $stock->count,
                    ]);
                }
            }
        });
    }
}
