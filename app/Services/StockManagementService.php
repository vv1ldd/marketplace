<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Shop;
use App\Jobs\SendTelegramJob;
use App\Jobs\AddCatalogItemToShop;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class StockManagementService
{
    /**
     * Process stock rules for a given product after a change (usually a sale).
     */
    public function processStockChange(Product $product): void
    {
        $shop = $product->shop;
        if (! $shop) {
            return;
        }

        $currentStock = (int) $product->stocks()->sum('count');

        // 1. Check for Low Stock Notifications
        $this->handleNotifications($product, $shop, $currentStock);

        // 2. Check for Auto-Replenishment
        $this->handleAutoReplenish($product, $shop, $currentStock);
    }

    protected function handleNotifications(Product $product, Shop $shop, int $currentStock): void
    {
        $threshold = $product->low_stock_notification_threshold ?? 10;

        if ($currentStock < $threshold) {
            // Rate limit: send notification only once every 24 hours
            if (! $product->last_low_stock_notification_at || $product->last_low_stock_notification_at->addHours(24)->isPast()) {
                $message = "⚠️ Внимание! Остаток товара «{$product->name}» (SKU: {$product->sku}) в магазине {$shop->name} снизился до {$currentStock} шт.";

                // A. Send Telegram
                if ($shop->telegram_bot_token && $shop->telegram_chat_id) {
                    try {
                        SendTelegramJob::dispatchSync(message: $message, shop: $shop);
                    } catch (\Exception $e) {
                        Log::error('Stock Notify: TG failed', ['error' => $e->getMessage()]);
                    }
                }

                // B. Send Email notification to sellers
                $sellers = $shop->sellers;
                foreach ($sellers as $seller) {
                    // Email
                    if ($seller->email) {
                        try {
                            Mail::raw($message, function ($mail) use ($seller) {
                                $mail->to($seller->email)
                                    ->subject('Уведомление о низком остатке товара');
                            });
                        } catch (\Exception $e) {
                            Log::error('Stock Notify: Email failed', ['error' => $e->getMessage()]);
                        }
                    }
                }

                $product->update(['last_low_stock_notification_at' => now()]);
            }
        }
    }

    protected function handleAutoReplenish(Product $product, Shop $shop, int $currentStock): void
    {
        if (! $product->auto_replenish_enabled) {
            return;
        }

        $threshold = $product->auto_replenish_threshold ?? 2;
        $quantity = $product->auto_replenish_quantity ?? 1;

        if ($currentStock <= $threshold) {
            Log::info('Auto-Replenish: Triggering for product', [
                'product_id' => $product->id,
                'stock' => $currentStock,
                'threshold' => $threshold,
                'quantity' => $quantity
            ]);

            try {
                $catalogItem = \App\Models\WildflowCatalog::where('sku', $product->wildflow_catalog_sku ?? $product->sku)->first();
                $sellerId = $shop->sellers()->first()?->id ?? 1;

                if ($catalogItem) {
                    AddCatalogItemToShop::dispatch(
                        catalogItemId: $catalogItem->id,
                        shopId: $shop->id,
                        sellerId: $sellerId,
                        count: $quantity
                    );
                }
            } catch (\Exception $e) {
                Log::error('Auto-Replenish: Failed to dispatch job', ['error' => $e->getMessage()]);
            }
        }
    }
}
