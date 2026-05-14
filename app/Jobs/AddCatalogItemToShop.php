<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Models\WildflowSkuAlias;
use App\Services\CardImageService;
use App\Services\FinanceService;
use App\Services\StandardizationService;
use App\Support\SalesChannels;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AddCatalogItemToShop implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $catalogItemId,
        public readonly int $shopId,
        public readonly int $sellerId,
        /** @var array<int, string> */
        public readonly array $salesChannels = ['yandex_market'],
        public readonly int $count = 0,
        public readonly ?float $amount = null,
    ) {}

    public function handle(): void
    {
        try {
            $shop = Shop::find($this->shopId);
            $providerProduct = ProviderProduct::find($this->catalogItemId);

            if (! $shop || ! $providerProduct) {
                Log::error('AddCatalogItemToShop: shop or provider product not found', [
                    'shopId' => $this->shopId,
                    'providerProductId' => $this->catalogItemId,
                ]);

                return;
            }

            $provider = $providerProduct->provider;
            $financeService = app(FinanceService::class);
            $standardizer = app(StandardizationService::class);

            // Если это Wildflow, подтягиваем оригинальный айтем для специфичной логики (Title и т.д.)
            $catalogItem = null;
            if ($provider?->type === 'wildflow') {
                $searchSku = !empty($providerProduct->market_sku) ? $providerProduct->market_sku : $providerProduct->sku;
                $catalogItem = WildflowCatalog::where('sku', $searchSku)->first();
            }

            $isVariable = $catalogItem?->is_variable_price ?? false;

            // --- 1. Get pricing ---
            $rate = $financeService->getRate($providerProduct->currency);

            $retailPrice = $isVariable && $this->amount !== null 
                ? $this->amount 
                : ($providerProduct->retail_price ?? $providerProduct->purchase_price);

            $percentageAdjustment = (float) (data_get($catalogItem?->data, 'data.percentage_of_buying_price', data_get($catalogItem?->data, 'percentage_of_buying_price', -2)));
            $basePurchasePrice = $isVariable && $this->amount !== null
                ? (float) ($this->amount * (1 + ($percentageAdjustment / 100)))
                : (float) $providerProduct->purchase_price;
            
            // 🔑 ТАРИФ: Сколько ЭТОТ селлер платит НАМ за этот товар (в RUB)
            $purchasePriceRub = $standardizer->getPurchasePriceForShop(
                (float)($basePurchasePrice * $rate), 
                (float)($retailPrice * $rate),
                $shop
            );
            $priceRub = (int) round($purchasePriceRub * 100);

            // --- Override dynamic amount for Variable products ---
            $productData = $providerProduct->data ?? [];
            if ($catalogItem) {
                $productData = array_merge($catalogItem->data ?? [], $productData);
            }
            
            // Explicitly set the currency to ensure FinanceService gets the correct rate
            data_set($productData, 'currency', $providerProduct->currency);
            data_set($productData, 'product.currency.code', $providerProduct->currency);
            
            if ($isVariable && $this->amount !== null) {
                if ($catalogItem) {
                    $catalogItem->retail_price = $this->amount;
                    $catData = $catalogItem->data ?? [];
                    data_set($catData, 'price', $this->amount);
                    data_set($catData, 'product.price', $this->amount);
                    data_set($catData, 'data.price', $this->amount);
                    $catalogItem->data = $catData;
                }

                // Ensure the Product's JSON data reflects the selected amount
                data_set($productData, 'price', $this->amount);
                data_set($productData, 'product.price', $this->amount);
                data_set($productData, 'face_value', $this->amount);
                data_set($productData, 'data.price', $this->amount);
                data_set($productData, 'buying_price', $basePurchasePrice);
                data_set($productData, 'data.buying_price', $basePurchasePrice);
            }

            // --- 2. Build localized title ---
            $name = $catalogItem ? $catalogItem->getTitleForShop($shop) : $providerProduct->name;
            if ($isVariable && $this->amount !== null && !$catalogItem) {
                // Fallback if no catalog item
                $name .= ' (' . number_format($this->amount, 2) . ' ' . $providerProduct->currency . ')';
            }

            // --- 3. Upsert Product (короткий offer SKU + wildflow_catalog_sku для провайдера и старых заказов) ---
            $defaultCategoryId = (int) ($shop->ym_category_id ?? \App\Models\Settings::get('YM_CATEGORY_ID', 70301474));

            $catalogSku = !empty($providerProduct->market_sku) ? $providerProduct->market_sku : $providerProduct->sku;
            $existing = Product::query()
                ->where('shop_id', $shop->id)
                ->where('provider_id', $provider?->id)
                ->where(function ($q) use ($catalogSku) {
                    $q->where('wildflow_catalog_sku', $catalogSku)
                        ->orWhere(function ($q2) use ($catalogSku) {
                            $q2->whereNull('wildflow_catalog_sku')->where('sku', $catalogSku);
                        });
                })
                ->when($isVariable && $this->amount !== null, function ($q) use ($retailPrice) {
                    $q->where('purchase_price', (int) ($retailPrice * 100));
                })
                ->first();

            $offerSku = $existing?->sku;
            if (! $offerSku) {
                // Если есть оригинальный айтем, используем его логику генерации SKU, иначе простую
                $offerSku = $catalogItem ? $catalogItem->suggestYmOfferSku() : 'P-'.Str::upper(Str::slug($providerProduct->name)).'-'.Str::random(4);
                while (Product::query()->where('sku', $offerSku)->exists()) {
                    $offerSku .= '-'.Str::upper(Str::random(4));
                }
            }

            $payload = [
                'wildflow_catalog_sku' => $catalogSku,
                'sku' => $offerSku,
                'catalog_id' => 1,
                'brand_id' => $providerProduct->brand_id,
                'category_id' => 3629,
                'market_category_id' => $catalogItem->market_category_id ?? $defaultCategoryId,
                'name' => $name,
                'type' => 'giftcard',
                'category' => 'Подарочные карты',
                'purchase_price' => (int) ($retailPrice * 100),
                'purchase_currency' => $providerProduct->currency,
                'base_price' => (int) ($basePurchasePrice * 100),
                'price_rub' => $priceRub,
                'barcode' => $catalogItem ? $catalogItem->getUpcForShop($shop) : '1'.str_pad($providerProduct->id, 11, '0', STR_PAD_LEFT),
                'is_active' => true,
                'data' => $productData,
                'shop_id' => $shop->id,
            ];

            if ($existing) {
                $existing->fill($payload);
                if ($existing->isDirty()) {
                    $existing->save();
                }
                $product = $existing->refresh();
            } else {
                $product = Product::create(array_merge($payload, [
                    'provider_id' => $provider?->id,
                ]));
            }

            WildflowSkuAlias::syncForProduct($product);

            // --- 4. Link to sales channels ---
            $selectedChannels = SalesChannels::normalizeSelection($this->salesChannels);
            $this->syncSalesChannels($shop, $product, $selectedChannels);

            // --- 5. Generate full product kit (Images, Title, Description) ---
            $cardService = app(CardImageService::class);
            $videoService = app(\App\Services\VideoInstructionService::class);

            $kit = $catalogItem ? $cardService->generateForCatalogItem($catalogItem, $shop) : [];

            $mainImage = $kit['images']['main'] ?? $providerProduct->image;

            $product->update([
                'image' => $mainImage,
                'pictures' => $kit['images'] ?? [],
                'name' => isset($kit['title']) && !$isVariable ? $kit['title'] : $name,
                'description' => $kit['description'] ?? null,
            ]);

            // Generate Video
            try {
                $videoUrl = $videoService->generateForProduct($product);
                if ($videoUrl) {
                    $product->update(['videos' => [$videoUrl]]);
                }
            } catch (\Exception $e) {
                Log::warning('AddCatalogItemToShop: video generation failed', ['error' => $e->getMessage()]);
            }

            // --- 5.5 🛡️ Pre-Flight Stock Check (SafeGuard) ---
            if ($this->count > 0 && $catalogItem) {
                 try {
                     $vault = app(\App\Services\VaultTransitService::class);
                     $serviceSku = $vault->decrypt($catalogItem->service_sku);
                     $wfService = new \App\Services\WildflowService();
                     
                     $availability = $wfService->checkAvailability(
                         service_sku: (string)$serviceSku,
                         quantity: $this->count,
                         price: $isVariable ? (float)$retailPrice : null
                     );

                     if (!$availability['available']) {
                         // 📊 INTELLIGENCE HARVESTING: Track demand deprivation
                         try {
                             app(\App\Services\LedgerService::class)->record($shop, 'PROVIDER_STOCK_DEFICIT', $catalogItem, [
                                 'sku' => $catalogItem?->sku,
                                 'requested_quantity' => $this->count,
                                 'trigger' => 'bulk_replenish_check'
                             ]);
                         } catch (\Exception $ledgerEx) {
                             \Log::warning('Failed to record stock deficit into ledger', ['err' => $ledgerEx->getMessage()]);
                         }

                         throw new \Exception("Пополнение отменено: Товара временно нет в наличии у поставщика или запрошенное количество ({$this->count}) недоступно.");
                     }
                 } catch (\Exception $e) {
                     if (str_contains($e->getMessage(), 'Пополнение отменено')) {
                         throw $e; // Re-throw expected logic error
                     }
                     Log::warning('Availability check failed due to communication error', ['msg' => $e->getMessage()]);
                     // We allow communication error fallback or strict? Let's be strict for partner finance safety.
                     throw new \Exception("Не удалось проверить наличие товара у поставщика: " . $e->getMessage());
                 }
            }

            // --- 6. 💰 Balance Safeguard (Hold/Capture Model) ---
            if ($this->count > 0) {
                $legalEntity = $shop->legalEntity;

                if ($legalEntity) {
                    // Используем уже вычисленную цену селлера (priceRub / 100)
                    $totalCostRub = ($priceRub / 100) * $this->count;

                    if ($legalEntity->available_balance < $totalCostRub) {
                        throw new \Exception('Недостаточно средств на балансе для резервирования стока (Требуется: '.number_format($totalCostRub, 2).' RUB)');
                    }

                    // Move money from Available to Reserved (The HOLD)
                    $legalEntity->decrement('available_balance', $totalCostRub);
                    $legalEntity->increment('reserved_balance', $totalCostRub);
                    
                    // Keep 'balance' field in sync (legacy total)
                    $legalEntity->decrement('balance', $totalCostRub);

                    // ⛓️ Sovereign Ledger: Record the FINANCE_HOLD
                    app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_HOLD', $product, [
                        'amount_rub' => $totalCostRub,
                        'available_before' => $legalEntity->available_balance + $totalCostRub,
                        'available_after' => $legalEntity->available_balance,
                        'count' => $this->count,
                        'context' => 'stock_replenish'
                    ]);

                    \Log::info('Finance: Funds held for stock purchase', [
                        'shop_id' => $shop->id,
                        'sku' => $product->sku,
                        'amount_rub' => $totalCostRub,
                        'count' => $this->count,
                    ]);
                }
            }

            // --- 7. 🎟 Generate Vouchers ---
            if ($this->count > 0) {
                $masterWarehouse = \App\Models\Warehouse::where('shop_id', $shop->id)
                    ->where('is_main', true)
                    ->first();

                if ($masterWarehouse) {
                    // 📝 Create Procurement Record for History
                    $procurement = \App\Models\Procurement::create([
                        'shop_id' => $shop->id,
                        'product_id' => $product->id,
                        'warehouse_id' => $masterWarehouse->id,
                        'count' => $this->count,
                        'price_per_item' => $priceRub, // Уже с учетом тарифа селлера
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    // ⛓️ Sovereign Ledger: Record the REPLENISH
                    app(\App\Services\LedgerService::class)->record($shop, 'STOCK_REPLENISH', $procurement, [
                        'count' => $this->count,
                        'sku' => $product->sku,
                    ]);

                    for ($i = 0; $i < $this->count; $i++) {
                        \App\Models\ProductInventory::create([
                            'shop_id' => $shop->id,
                            'warehouse_id' => $masterWarehouse->id,
                            'sku' => $product->sku,
                            'voucher' => \App\Helpers\GenerateSecureCode::generate($shop->voucher_prefix),
                            'is_used' => false,
                            'status' => 'available',
                            'procurement_id' => $procurement->id,
                        ]);
                    }

                    // Update WarehouseStock count
                    \App\Models\WarehouseStock::updateOrCreate(
                        ['warehouse_id' => $masterWarehouse->id, 'product_id' => $product->id],
                        ['count' => \App\Models\ProductInventory::where('warehouse_id', $masterWarehouse->id)
                            ->where('sku_bidx', app(\App\Services\VaultTransitService::class)->computeBlindIndex($product->sku))
                            ->where('is_used', false)
                            ->count()
                        ]
                    );

                    // Trigger distribution to channels
                    \App\Jobs\DistributeStockToChannels::dispatch($shop);
                }
            }

            // --- 7. Push to Yandex Market ---
            $ymResult = null;
            if (
                in_array('yandex_market', $selectedChannels, true)
                && $shop->campaign_id
                && $shop->api_key
                && $shop->business_id
            ) {
                $ymResult = $this->pushToYandexMarket($shop, $product);
            }

            // --- 8. Notify seller in Filament ---
            $seller = \App\Models\Seller::find($this->sellerId);
            if ($seller) {
                $body = "«{$product->name}» добавлен в ваш каталог";
                if ($this->count > 0) {
                    $body .= " и сгенерировано {$this->count} ваучеров.";
                }
                if ($ymResult === true) {
                    $body .= ' Карточка отправлена на Яндекс Маркет.';
                } elseif ($ymResult === false) {
                    $body .= ', но при отправке на ЯМ возникла ошибка — проверьте кабинет.';
                }

                Notification::make()
                    ->title('Товар добавлен')
                    ->body($body)
                    ->success()
                    ->sendToDatabase($seller);
            }
        } catch (\Throwable $e) {
            Log::error('AddCatalogItemToShop failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            $seller = \App\Models\Seller::find($this->sellerId);
            if ($seller) {
                Notification::make()
                    ->title('Ошибка публикации товара')
                    ->body($e->getMessage())
                    ->danger()
                    ->sendToDatabase($seller);
            }
            
            throw $e;
        }
    }

    private function pushToYandexMarket(Shop $shop, Product $product): bool
    {
        try {
            $service = new YmService($shop);
            $categoryId = (int) ($shop->ym_category_id ?? \App\Models\Settings::get('YM_CATEGORY_ID', 70301474));

            $offer = $product->toYmOffer($categoryId, $shop->id);

            $service->offerMappingsUpdate([['offer' => $offer]]);

            $product->update(['send_to_ym_at' => now()]);

            return true;
        } catch (\Throwable $e) {
            Log::error('AddCatalogItemToShop: YM push failed', [
                'sku' => $product->sku,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param  array<int, string>  $selectedChannels
     */
    private function syncSalesChannels(Shop $shop, Product $product, array $selectedChannels): void
    {
        $knownChannels = array_keys(SalesChannels::optionsForUi());

        foreach ($knownChannels as $channel) {
            ProductSalesChannel::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'shop_id' => $shop->id,
                    'channel' => $channel,
                ],
                [
                    'is_enabled' => in_array($channel, $selectedChannels, true),
                    'last_error' => null,
                ]
            );
        }
    }
}
