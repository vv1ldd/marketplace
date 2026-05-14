<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Procurement extends Model
{
    protected $fillable = [
        'shop_id',
        'product_id',
        'warehouse_id',
        'count',
        'price_per_item',
        'total_price',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'price_per_item' => 'integer',
        'total_price' => 'integer',
        'count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($procurement) {
            $procurement->total_price = $procurement->count * $procurement->price_per_item;

            // Если статус меняется на completed впервые
            if ($procurement->status === 'completed' && ! $procurement->completed_at) {
                $procurement->completed_at = now();
            }
        });

        static::updated(function ($procurement) {
            // Если закупка завершена
            if ($procurement->status === 'completed' && $procurement->wasChanged('status')) {
                // 1. Reserve funds (Move from balance to reserved_balance)
                $legalEntity = $procurement->shop?->legalEntity;
                if ($legalEntity) {
                    \DB::transaction(function () use ($legalEntity, $procurement) {
                        $legalEntity->decrement('balance', $procurement->total_price);
                        $legalEntity->increment('reserved_balance', $procurement->total_price);
                    });
                    
                    \Illuminate\Support\Facades\Log::info("Procurement Reserved: {$procurement->total_price} for Shop {$procurement->shop_id}");
                }

                // 2. Обновляем старые остатки (для совместимости)
                WarehouseStock::updateOrCreate(
                    [
                        'warehouse_id' => $procurement->warehouse_id,
                        'product_id' => $procurement->product_id,
                    ],
                    [
                        'count' => \Illuminate\Support\Facades\DB::raw("count + {$procurement->count}"),
                        'synced_at' => now(),
                    ]
                );

                // 3. TODO: Здесь должен быть вызов Job для закупки реальных кодов через API
                // И сохранение их в ProductInventory
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(LegalEntity::class, Shop::class, 'id', 'id', 'shop_id', 'legal_entity_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductInventory::class);
    }
}
