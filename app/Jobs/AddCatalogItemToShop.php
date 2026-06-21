<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Models\WildflowSkuAlias;
use App\Services\CanonicalCategoryResolver;
use App\Services\FinanceService;
use App\Services\StandardizationService;
use App\Support\SalesChannels;
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
        public readonly ?string $passkeyCredentialId = null,
        public readonly string $paymentMethod = 'rub',
        public readonly ?array $simpleLayerOneProof = null,
    ) {}

    public function handle(): void
    {
        try {
            $shop = Shop::find($this->shopId);
            $providerProduct = ProviderProduct::query()
                ->whereKey($this->catalogItemId)
                ->where('is_active', true)
                ->first();

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
                $catalogItem = WildflowCatalog::query()
                    ->where('sku', $searchSku)
                    ->where('is_active', true)
                    ->first();

                if (! $catalogItem) {
                    throw new \RuntimeException('Товар провайдера больше не доступен в активном каталоге.');
                }
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
            $categoryResolver = app(CanonicalCategoryResolver::class);
            $canonicalCategory = $categoryResolver->forProviderProduct($providerProduct, $catalogItem);
            $defaultCategoryId = (int) ($shop->ym_category_id ?: \App\Models\Settings::get('YM_CATEGORY_ID', 989939));
            $marketCategoryId = $this->resolveYandexMarketCategoryId($providerProduct, $catalogItem, $defaultCategoryId, $canonicalCategory);
            $globalCatalogId = \App\Models\Catalog::query()
                ->where('type', 'global')
                ->value('id');
            $localCategoryId = \App\Models\Category::query()
                ->where('ym_id', $marketCategoryId)
                ->value('id');

            $catalogSku = !empty($providerProduct->market_sku) ? $providerProduct->market_sku : $providerProduct->sku;
            $catalogSkuBidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($catalogSku);
            $existing = Product::query()
                ->where('shop_id', $shop->id)
                ->where('provider_id', $provider?->id)
                ->where(function ($q) use ($catalogSku, $catalogSkuBidx) {
                    $q->where('wildflow_catalog_sku_bidx', $catalogSkuBidx)
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
                'catalog_id' => $globalCatalogId,
                'provider_id' => $provider?->id,
                'brand_id' => $providerProduct->brand_id,
                'category_id' => $localCategoryId,
                'market_category_id' => $marketCategoryId,
                'name' => $name,
                'type' => 'giftcard',
                'category' => $categoryResolver->label($canonicalCategory),
                'canonical_category' => $canonicalCategory,
                'purchase_price' => (int) ($retailPrice * 100),
                'purchase_currency' => $providerProduct->currency,
                'base_price' => (int) ($basePurchasePrice * 100),
                'purchase_price_rub' => (int) round($purchasePriceRub * 100),
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
                $product = Product::create($payload);
            }

            WildflowSkuAlias::syncForProduct($product);

            // --- 4. Link to sales channels ---
            $selectedChannels = SalesChannels::filterSelectionForShop($this->salesChannels, $shop);
            $unavailableChannels = array_values(array_filter(
                $selectedChannels,
                fn (string $channel): bool => ! SalesChannels::isChannelConfigured($channel, $shop)
            ));
            if (! empty($unavailableChannels)) {
                throw new \Exception('Канал продаж еще не активирован: '.implode(', ', $unavailableChannels));
            }

            $this->syncSalesChannels($shop, $product, $selectedChannels);

            // Heavy card/video enrichment runs after the response; checkout should only block on ledger + stock.
            $product->update([
                'image' => $product->image ?: $providerProduct->image,
                'name' => $product->name ?: $name,
            ]);

            EnrichCatalogProductMedia::dispatch(
                $product->id,
                $providerProduct->id,
                $catalogItem?->id,
                $shop->id,
                $isVariable,
                $name,
                in_array('yandex_market', $selectedChannels, true),
            )->afterResponse();

            // --- 5.5 🛡️ Pre-Flight Stock Check (SafeGuard) ---
            if ($this->count > 0 && $catalogItem) {
                 try {
                     $availability = app(\App\Services\StorefrontStockAvailabilityService::class)->check(
                         $catalogItem,
                         $this->count,
                         $isVariable ? (float)$retailPrice : null,
                         (string)$this->sellerId
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

                         $reason = ($availability['source'] ?? null) === 'provider_auth_failed'
                            ? ($availability['error'] ?? 'Не удалось подтвердить сток у провайдера.')
                            : "Товара временно нет в наличии у поставщика или запрошенное количество ({$this->count}) недоступно.";

                         throw new \Exception("Пополнение отменено: {$reason}");
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
                    $paymentMethod = $this->normalizedPaymentMethod();
                    $sl1RubRate = (float) config('sl1_tokenomics.rub_rate', 100.0);
                    $sl1Amount = round($totalCostRub / max($sl1RubRate, 0.0001), 4);
                    $gasFeeSl1 = $paymentMethod === 'native_token' ? 0.0015 : 0.0;

                    if ($paymentMethod === 'native_token') {
                        $balances = app(\App\Services\L1StateService::class)->reconstructBalance($legalEntity);
                        $availableSl1 = (float) ($balances['sl1_available_balance'] ?? $balances['native_available_balance'] ?? 0);
                        $totalCostSl1 = $sl1Amount + $gasFeeSl1;

                        if ($availableSl1 < $totalCostSl1) {
                            throw new \Exception('Недостаточно SL1 для резервирования стока (Требуется: '.number_format($totalCostSl1, 4).' SL1)');
                        }

                        $legalEntity->decrement('native_token_balance', $totalCostSl1);
                        $legalEntity->increment('native_token_reserved', $sl1Amount);
                    } else {
                        $balances = app(\App\Services\L1StateService::class)->reconstructBalance($legalEntity);
                        $availableRub = (float) ($balances['rub_available_balance'] ?? $balances['rubt_available_balance'] ?? $balances['available_balance'] ?? 0);

                        if ($availableRub < $totalCostRub) {
                            throw new \Exception('Недостаточно RUB на балансе для резервирования стока (Требуется: '.number_format($totalCostRub, 2).' RUB)');
                        }

                        // Move tokenized RUB from Available to Reserved (The HOLD)
                        $legalEntity->decrement('available_balance', $totalCostRub);
                        $legalEntity->increment('reserved_balance', $totalCostRub);
                        
                        // Keep 'balance' field in sync for legacy total-spend projections.
                        $legalEntity->decrement('balance', $totalCostRub);
                    }

                    // ⛓️ Sovereign Ledger: Record the FINANCE_HOLD
                    app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_HOLD', $product, [
                        'asset' => $paymentMethod === 'native_token' ? 'SL1' : 'RUB',
                        'amount_rub' => $totalCostRub,
                        'token_amount' => $paymentMethod === 'native_token' ? $sl1Amount : $totalCostRub,
                        'sl1_amount' => $paymentMethod === 'native_token' ? $sl1Amount : 0.0,
                        'gas_fee' => $gasFeeSl1,
                        'payment_method' => $paymentMethod,
                        'available_before' => $paymentMethod === 'native_token'
                            ? ($balances['sl1_available_balance'] ?? $balances['native_available_balance'] ?? 0)
                            : ($legalEntity->available_balance + $totalCostRub),
                        'available_after' => $paymentMethod === 'native_token'
                            ? (($balances['sl1_available_balance'] ?? $balances['native_available_balance'] ?? 0) - $sl1Amount - $gasFeeSl1)
                            : $legalEntity->available_balance,
                        'count' => $this->count,
                        'context' => 'stock_replenish',
                        'signature_method' => $this->simpleLayerOneProof['signature_method'] ?? 'passkey',
                        'assertion_id' => $this->passkeyCredentialId,
                        'simple_layer_one' => $this->simpleLayerOneProof,
                        'tx_hash' => $this->simpleLayerOneProof['tx_hash'] ?? null,
                        'tx_nonce' => $this->simpleLayerOneProof['tx_nonce'] ?? null,
                    ]);

                    \Log::info('Finance: Funds held for stock purchase', [
                        'shop_id' => $shop->id,
                        'sku' => $product->sku,
                        'amount_rub' => $totalCostRub,
                        'payment_method' => $paymentMethod,
                        'count' => $this->count,
                    ]);
                }
            }

            // --- 7. 🎟 Generate Vouchers ---
            if ($this->count > 0) {
                $masterWarehouse = app(\App\Services\SellerDistributionCenterService::class)
                    ->masterWarehouseForShop($shop);

                if (
                    in_array('yandex_market', $selectedChannels, true)
                    && $shop->ym_warehouse_id
                ) {
                    \App\Models\Warehouse::query()->updateOrCreate(
                        [
                            'shop_id' => $shop->id,
                            'channel' => 'yandex_market',
                        ],
                        [
                            'ym_id' => (int) $shop->ym_warehouse_id,
                            'name' => 'Yandex Market',
                            'type' => 'channel',
                            'is_active' => true,
                            'is_main' => false,
                            'channel_quota' => 100,
                        ]
                    );
                }

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
                    $totalCostRub = ($priceRub / 100) * $this->count;
                    $paymentMethod = $this->normalizedPaymentMethod();
                    $sl1Amount = round($totalCostRub / max((float) config('sl1_tokenomics.rub_rate', 100.0), 0.0001), 4);
                    app(\App\Services\LedgerService::class)->record($shop, 'STOCK_REPLENISH', $procurement, [
                        'count' => $this->count,
                        'sku' => $product->sku,
                        'asset' => $paymentMethod === 'native_token' ? 'SL1' : 'RUB',
                        'amount_rub' => $totalCostRub,
                        'token_amount' => $paymentMethod === 'native_token' ? $sl1Amount : $totalCostRub,
                        'sl1_amount' => $paymentMethod === 'native_token' ? $sl1Amount : 0.0,
                        'payment_method' => $paymentMethod,
                        'signature_method' => $this->simpleLayerOneProof['signature_method'] ?? 'passkey',
                        'assertion_id' => $this->passkeyCredentialId,
                        'simple_layer_one' => $this->simpleLayerOneProof,
                        'tx_hash' => $this->simpleLayerOneProof['tx_hash'] ?? null,
                        'tx_nonce' => $this->simpleLayerOneProof['tx_nonce'] ?? null,
                    ]);

                    if ($shop->legalEntity) {
                        if ($paymentMethod === 'native_token') {
                            $shop->legalEntity->decrement('native_token_reserved', $sl1Amount);
                        } else {
                            $shop->legalEntity->decrement('reserved_balance', $totalCostRub);
                        }
                    }

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

                    // Push only the purchased SKU to channels asynchronously. The Simple Layer One
                    // transaction is already finalized; channel sync should not block checkout UX.
                    \App\Jobs\DistributeStockToChannels::dispatch($shop, productId: $product->id);
                }
            }

            Log::info('Catalog item added to shop', [
                'seller_id' => $this->sellerId,
                'shop_id' => $shop->id,
                'product_id' => $product->id,
                'generated_vouchers' => $this->count,
                'queued_channels' => $selectedChannels,
            ]);
        } catch (\Throwable $e) {
            Log::error('AddCatalogItemToShop failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            
            throw $e;
        }
    }

    private function normalizedPaymentMethod(): string
    {
        return $this->paymentMethod === 'native_token' ? 'native_token' : 'rub';
    }

    private function resolveYandexMarketCategoryId(ProviderProduct $providerProduct, ?WildflowCatalog $catalogItem, int $defaultCategoryId, ?string $canonicalCategory = null): int
    {
        $resolver = app(CanonicalCategoryResolver::class);
        $category = $canonicalCategory ?: $resolver->forProviderProduct($providerProduct, $catalogItem);

        return $resolver->yandexCategoryId($category, $catalogItem?->market_category_id ?: $defaultCategoryId);
    }

    /**
     * @param  array<int, string>  $selectedChannels
     */
    private function syncSalesChannels(Shop $shop, Product $product, array $selectedChannels): void
    {
        $knownChannels = array_keys(SalesChannels::optionsForUi($shop));
        $selectedChannels = array_values(array_intersect($selectedChannels, $knownChannels));

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
