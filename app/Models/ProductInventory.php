<?php

namespace App\Models;

use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductInventory extends Model
{
    protected $table = 'product_inventory';

    protected $fillable = [
        'shop_id',
        'warehouse_id',
        'sku',
        'nominal_amount',
        'nominal_currency',
        'sku_bidx',
        'voucher',   // OUR internal redeem token (GenerateSecureCode). NOT the Wildflow gift-card code.
        'is_used',
        'order_item_id',
        'expires_at',
        'status',
        'liquidated_at',
        'liquidation_reason',
        'procurement_id',
    ];

    protected $casts = [
        'is_used'    => 'boolean',
        'expires_at' => 'timestamp',
        'sku'        => \App\Casts\VaultEncrypted::class . ':sku_bidx',
        'nominal_amount' => 'decimal:2',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function legalEntity(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            LegalEntity::class,
            Shop::class,
            'id',
            'id',
            'shop_id',
            'legal_entity_id'
        );
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItems::class, 'order_item_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function ledgerEntries(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(SovereignLedger::class, 'entity');
    }

    protected static function booted()
    {
        static::saved(function ($inventory) {
            // Если статус использования изменился или это новый ваучер — пересчитываем склад
            if ($inventory->isDirty('is_used') || $inventory->isDirty('status') || $inventory->wasRecentlyCreated) {
                $product = Product::queryByOfferSku($inventory->sku)->first();
                
                if ($product && $inventory->warehouse_id) {
                    $count = static::where('warehouse_id', $inventory->warehouse_id)
                        ->where('sku_bidx', $inventory->sku_bidx)
                        ->where('is_used', false)
                        ->where('status', 'available')
                        ->count();

                    WarehouseStock::updateOrCreate(
                        ['warehouse_id' => $inventory->warehouse_id, 'product_id' => $product->id],
                        ['count' => $count]
                    );

                    // 🔔 Уведомление о низком остатке и автозакуп
                    app(\App\Services\StockManagementService::class)->processStockChange($product);
                }
            }
        });
    }

    /**
     * Liquidate (cancel) this voucher and return money to the partner
     */
    public function liquidate(string $reason = 'Provider Error'): void
    {
        if ($this->status === 'liquidated') {
            return;
        }

        $orderItem = $this->orderItem;
        $order = $orderItem?->order;
        $legalEntity = $order?->shop?->legalEntity;

        // 1. Release the Hold (if money was reserved)
        if ($legalEntity) {
            $productModel = Product::queryByOfferSku($this->sku)->first();
            $catalogSku = $productModel?->wildflow_catalog_sku ?? $this->sku;
            $catalog = WildflowCatalog::where('sku', $catalogSku)->first();
            
            if ($catalog) {
                $financeService = app(\App\Services\FinanceService::class);
                $rate = $financeService->getRate($catalog->currency_code);
                $costRub = $catalog->retail_price * $rate * ($orderItem->count ?? 1);

                // Check if we already captured? If status is 'sold', we might need to refund available_balance.
                // If status is 'reserved', we just move back from reserved to available.
                if ($this->status === 'reserved') {
                    $legalEntity->increment('available_balance', $costRub);
                    $legalEntity->decrement('reserved_balance', $costRub);
                    
                    $order->comments()->create([
                        'comment' => "Финансы: Резерв {$costRub} RUB возвращен на баланс (Ликвидация ваучера: {$reason}).",
                    ]);

                    // ⛓️ Sovereign Ledger: Record the RELEASE
                    app(\App\Services\LedgerService::class)->record($order->shop, 'FINANCE_RELEASE', $this, [
                        'amount_rub' => $costRub,
                        'reason' => $reason,
                        'order_id' => $order->id,
                    ]);
                }
            }
        }

        // 2. Mark as liquidated
        $this->update([
            'status' => 'liquidated',
            'is_used' => true, // Don't allow using it anymore
            'liquidated_at' => now(),
            'liquidation_reason' => $reason,
        ]);

        // ⛓️ Sovereign Ledger: Record the LIQUIDATION
        app(\App\Services\LedgerService::class)->record($order?->shop ?? \App\Models\Shop::find($this->shop_id), 'STOCK_LIQUIDATE', $this, [
            'reason' => $reason,
        ]);
    }

    /**
     * Release (cancel) this voucher and return money + stock
     */
    public function release(string $reason = 'Order Cancelled'): void
    {
        if ($this->status !== 'reserved') {
            return;
        }

        $orderItem = $this->orderItem;
        $order = $orderItem?->order;
        $legalEntity = $order?->shop?->legalEntity;

        // 1. Release the Hold
        if ($legalEntity) {
            $productModel = Product::queryByOfferSku($this->sku)->first();
            $catalogSku = $productModel?->wildflow_catalog_sku ?? $this->sku;
            $catalog = WildflowCatalog::where('sku', $catalogSku)->first();
            
            if ($catalog) {
                $financeService = app(\App\Services\FinanceService::class);
                $rate = $financeService->getRate($catalog->currency_code);
                $costRub = $catalog->retail_price * $rate * ($orderItem->count ?? 1);

                $legalEntity->increment('available_balance', $costRub);
                $legalEntity->decrement('reserved_balance', $costRub);
                
                if ($order) {
                    $order->comments()->create([
                        'comment' => "Финансы: Резерв {$costRub} RUB возвращен на баланс (Отмена заказа: {$reason}).",
                    ]);
                }

                // ⛓️ Sovereign Ledger: Record the RELEASE
                app(\App\Services\LedgerService::class)->record($order?->shop, 'FINANCE_RELEASE', $this, [
                    'amount_rub' => $costRub,
                    'reason' => $reason,
                    'order_id' => $order?->id,
                ]);
            }
        }

        // 2. Return to stock
        $this->update([
            'status' => 'available',
            'is_used' => false,
            'order_item_id' => null,
        ]);

        // ⛓️ Sovereign Ledger: Record the STOCK RELEASE
        app(\App\Services\LedgerService::class)->record($order?->shop ?? \App\Models\Shop::find($this->shop_id), 'STOCK_RELEASE', $this, [
            'reason' => $reason,
            'sku' => $this->sku,
        ]);
    }
}
