<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportProductsFromYM implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes

    protected $shop;

    public $importToken;

    public function __construct($shop, ?string $importToken = null)
    {
        $this->shop = $shop;
        $this->importToken = $importToken;
    }

    public function handle(): void
    {
        $shop = $this->shop;
        // Refresh shop from DB to get the latest token
        $shop->refresh();

        \Log::info('--- Начинаю импорт ---', [
            'shop_id' => $shop->id,
            'job_token' => $this->importToken ?: 'NULL',
            'shop_token' => $shop->import_token ?: 'NULL',
        ]);
        if ($this->importToken && $shop->import_token !== $this->importToken) {
            \Log::info('Задача импорта остановлена: токен устарел', ['shop' => $shop->id]);

            return;
        }

        $service = new \App\Http\Services\YmService($shop);

        $shop->update([
            'import_status' => 'Разведка каталога Яндекса...',
            'import_progress' => 1,
        ]);

        try {
            // ⚖️ Phase 1: Detailed Import from Mapping (THE PRIMARY SOURCE OF TRUTH)
            $shop->update(['import_status' => 'Синхронизация каталога...']);
            $pageToken = null;
            $pageNumber = 1;
            $mappingStoppedEarly = false;
            $allMappedSkus = [];
            $mappingData = [];

            do {
                if ($this->importToken && $shop->import_token !== $this->importToken) {
                    return;
                }
                $result = $service->getOffers($pageToken);
                $offers = $result['offerMappings'] ?? [];
                $nextPageToken = $result['paging']['nextPageToken'] ?? null;

                if ($nextPageToken && $nextPageToken === $pageToken) {
                    \Log::error('Infinite loop detected in Phase 1!');
                    $mappingStoppedEarly = true;
                    break;
                }
                $pageToken = $nextPageToken;

                foreach ($offers as $item) {
                    $sku = $item['offer']['offerId'] ?? null;
                    if ($sku) {
                        $allMappedSkus[] = $sku;
                        $mappingData[$sku] = $item;
                    }
                }
                $processedCount = count($allMappedSkus);
                $shop->update([
                    'import_status' => "Загрузка каталога: {$processedCount} товаров...",
                    'import_progress' => 10,
                ]);
            } while ($pageToken);

            \Log::info('Всего товаров в каталоге Яндекса: '.count($allMappedSkus));

            // 💰 Phase 2: Fetch Prices and Status from Campaign (ENRICHMENT)
            $shop->update(['import_status' => 'Синхронизация цен и остатков...']);
            $skuToCampaignData = [];
            $campaignToken = null;
            do {
                if ($this->importToken && $shop->import_token !== $this->importToken) {
                    return;
                }
                $campaignResult = $service->getCampaignOffers($campaignToken);
                foreach ($campaignResult['offers'] ?? [] as $cOffer) {
                    if ($sku = $cOffer['offerId'] ?? null) {
                        $skuToCampaignData[$sku] = [
                            'price' => $cOffer['basicPrice']['value'] ?? 0,
                            'available' => $cOffer['available'] ?? true,
                        ];
                    }
                }
                $campaignToken = $campaignResult['paging']['nextPageToken'] ?? null;
            } while ($campaignToken);

            \Log::info('Найдено актуальных предложений в кампании: '.count($skuToCampaignData));

            // 🚀 Phase 3: Process and Save Products
            $shop->update(['import_status' => 'Сохранение данных в базу...']);
            $processedProductIds = [];
            $knownYmOfferIds = [];
            $saveCount = 0;

            // Get or create the shop's catalog
            $shopCatalog = \App\Models\Catalog::firstOrCreate(
                ['shop_id' => $shop->id, 'type' => 'shop'],
                ['name' => "Каталог магазина {$shop->name}"]
            );

            foreach ($mappingData as $sku => $item) {
                $knownYmOfferIds[$sku] = true;
                $extraData = $skuToCampaignData[$sku] ?? null;
                
                $name = $item['offer']['name'] ?? $item['mapping']['marketModelName'] ?? 'Yandex Product '.$sku;
                $price = $extraData['price'] ?? 0;
                $slug = \Illuminate\Support\Str::slug($sku) . '-' . substr(md5($sku), 0, 4);

                $globalProduct = \App\Models\Product::queryByOfferSku($sku)
                    ->where('provider_id', '>', 0)
                    ->first();

                if ($globalProduct) {
                    $product = \App\Models\Product::updateOrCreate(['sku' => $sku, 'catalog_id' => $shopCatalog->id], [
                        'name' => $name,
                        'slug' => $slug,
                        'brand_id' => $globalProduct->brand_id,
                        'category_id' => $globalProduct->category_id,
                        'type' => $globalProduct->type,
                        'provider_id' => $globalProduct->provider_id,
                        'price_rub' => (int) ($price * 100),
                        'purchase_price_rub' => $globalProduct->price_rub,
                        'is_active' => $extraData['available'] ?? true,
                        'data' => $globalProduct->data,
                        'image' => $product->image ?? (! empty($item['offer']['pictures']) ? $item['offer']['pictures'][0] : null),
                        'pictures' => $product->pictures ?? ($item['offer']['pictures'] ?? []),
                        'params' => $item['offer']['params'] ?? null,
                    ]);
                } else {
                    $product = \App\Models\Product::updateOrCreate(['sku' => $sku, 'catalog_id' => $shopCatalog->id], [
                        'name' => $name,
                        'slug' => $slug,
                        'type' => 'vouchers',
                        'price_rub' => (int) ($price * 100),
                        'is_active' => $extraData['available'] ?? true,
                        'image' => ! empty($item['offer']['pictures']) ? $item['offer']['pictures'][0] : null,
                        'pictures' => $item['offer']['pictures'] ?? [],
                        'params' => $item['offer']['params'] ?? null,
                    ]);
                }

                $product->update(['shop_id' => $shop->id]);

                \App\Models\ProductSalesChannel::updateOrCreate(
                    ['product_id' => $product->id, 'shop_id' => $shop->id, 'channel' => 'yandex_market'],
                    ['is_enabled' => true, 'last_synced_at' => now()]
                );

                $processedProductIds[] = $product->id;
                $saveCount++;

                if ($saveCount % 50 === 0) {
                    $progress = floor(($saveCount / count($mappingData)) * 100);
                    $shop->update([
                        'import_status' => "Сохранение: {$saveCount} из ".count($mappingData)." ({$progress}%)",
                        'import_progress' => 20 + (int)($progress * 0.7),
                    ]);
                }
            }

            // 🔥 Сверяем Центр Дистрибуции
            if (!$mappingStoppedEarly) {
                // Все, кого НЕТ в ответе Яндекса — перемещаем в "Только витрина"
                \App\Models\ProductSalesChannel::where('shop_id', $shop->id)
                    ->where('channel', 'yandex_market')
                    ->whereNotIn('product_id', $processedProductIds)
                    ->update(['is_enabled' => false]);
                
                \Log::info('Синхронизация каналов: отсутствующие на Яндексе товары переведены в "Только витрина".');
            }

            // 📦 Phase 4: Sync Stocks from Yandex
            if ($this->importToken && $shop->import_token !== $this->importToken) {
                return;
            }

            $shop->update(['import_status' => 'Синхронизация складских остатков...']);
            try {
                $ymStocks = $service->getStocks();
                $stockCount = 0;

                foreach ($ymStocks['warehouses'] ?? [] as $ymW) {
                    $warehouseId = (int) $ymW['warehouseId'];
                    
                    // Find or create a local warehouse record for this YM warehouse
                    $localWarehouse = \App\Models\Warehouse::updateOrCreate(
                        ['shop_id' => $shop->id, 'ym_id' => $warehouseId],
                        ['name' => "YM Warehouse {$warehouseId}", 'channel' => 'yandex_market']
                    );

                    foreach ($ymW['offers'] ?? [] as $offer) {
                        $sku = $offer['offerId'] ?? null;
                        if (!$sku) continue;

                        $product = \App\Models\Product::queryByOfferSku($sku)
                            ->where('shop_id', $shop->id)
                            ->first();

                        if ($product) {
                            $fitStock = collect($offer['stocks'])->firstWhere('type', 'FIT');
                            $count = $fitStock['count'] ?? 0;

                            \App\Models\WarehouseStock::updateOrCreate(
                                ['warehouse_id' => $localWarehouse->id, 'product_id' => $product->id],
                                ['count' => (int) $count, 'synced_at' => now()]
                            );
                            $stockCount++;
                        }
                    }
                }
                \Log::info("Синхронизировано остатков: {$stockCount}");
            } catch (\Exception $e) {
                \Log::warning('Could not sync stocks: '.$e->getMessage());
            }

            $shop->update([
                'import_status' => "Готово! Синхронизировано {$processedCount} товаров и {$stockCount} остатков. Хэш: ".substr($shop->import_hash, 0, 16).'...',
                'import_progress' => 100,
            ]);

            // 🔔 Send Persistent Database Notifications to all shop sellers
            $this->notifySellers(
                title: 'Синхронизация Яндекс Маркет завершена',
                body: "Магазин {$shop->name}: загружено {$processedCount} товаров и {$stockCount} остатков.",
                type: 'success'
            );

        } catch (\Exception $e) {
            \Log::error('Yandex Import Failed: '.$e->getMessage(), ['exception' => $e]);

            $errorMessage = \Illuminate\Support\Str::limit($e->getMessage(), 1000);
            $shop->update([
                'import_status' => 'Ошибка: '.$errorMessage,
                'import_progress' => 0,
            ]);

            $this->notifySellers(
                title: 'Ошибка синхронизации Яндекс Маркет',
                body: "Магазин {$shop->name}: {$errorMessage}",
                type: 'danger'
            );
        }
    }

    /**
     * Отправляет уведомления в базу данных всем менеджерам магазина
     */
    private function notifySellers(string $title, string $body, string $type = 'success'): void
    {
        try {
            $sellers = $this->shop->sellers;
            
            if ($sellers->isEmpty()) return;

            $notification = \Filament\Notifications\Notification::make()
                ->title($title)
                ->body($body);

            if ($type === 'success') $notification->success();
            if ($type === 'danger') $notification->danger();
            if ($type === 'warning') $notification->warning();

            foreach ($sellers as $seller) {
                $notification->sendToDatabase($seller);
            }
        } catch (\Exception $e) {
            \Log::warning('Could not send database notifications: ' . $e->getMessage());
        }
    }

    /**
     * Если селлер удалил оффер в кабинете Маркета, или он по какой-то причине пропал из фида — 
     * мы помечаем такие товары как неактивные (в архив).
     *
     * @param  array<string, true>  $knownYmOfferSkuKeys
     */
    /**
     * Больше не архивируем товары автоматически.
     * Если товара нет на Маркете, он просто остается "Только на витрине".
     */
    private function reconcileProductsRemovedFromYandex(Shop $shop, array $knownYmOfferSkuKeys): void
    {
        // Логика архивации отключена по просьбе пользователя
    }
}
