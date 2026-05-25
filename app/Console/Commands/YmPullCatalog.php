<?php

namespace App\Console\Commands;

use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\Shop;
use App\Models\WildflowCatalog;
use App\Models\WildflowSkuAlias;
use App\Services\CanonicalCategoryResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YmPullCatalog extends Command
{
    protected $signature = 'ym:pull-catalog {--shop= : Process only specific shop ID}';
    protected $description = 'Import and refresh products from Yandex Market to ensure parity';

    public function handle()
    {
        $this->info('Starting Yandex Market Catalog Pull (Sync from Yandex)...');

        $shopsQuery = Shop::where('is_active', true)->whereNotNull('api_key');
        if ($this->option('shop')) {
            $shopsQuery->where('id', $this->option('shop'));
        }

        $shops = $shopsQuery->get();

        if ($shops->isEmpty()) {
            $this->error('No active shops with API credentials found.');
            return 1;
        }

        foreach ($shops as $shop) {
            $this->info("Processing shop: {$shop->name} (ID: {$shop->id})");
            $service = new YmService($shop);
            
            $businessId = (int)($shop->business_id ?? $shop->api_application?->client_id);
            if (!$businessId) {
                $this->error("Shop {$shop->name} is missing businessId.");
                continue;
            }

            $pageToken = null;
            $totalImported = 0;
            $totalLinked = 0;

            do {
                try {
                    $response = $service->getOffers($pageToken);
                    $offers = $response['offerMappings'] ?? [];
                    $pageToken = $response['paging']['nextPageToken'] ?? null;

                    foreach ($offers as $item) {
                        $offer = $item['offer'] ?? [];
                        if (empty($offer['offerId'])) continue;

                        $mapping = $item['mapping'] ?? [];
                        
                        // Resolve Yandex offerId back to our provider catalog before upserting.
                        $catalogItem = $this->resolveCatalogItem((string) $offer['offerId'], (string) ($offer['name'] ?? ''));
                        $existingProduct = Product::queryByOfferSku($offer['offerId'])
                            ->where('shop_id', $shop->id)
                            ->first();

                        $payload = [
                            'name' => $offer['name'] ?? 'No Name',
                            'vendor' => $offer['vendor'] ?? $existingProduct?->vendor,
                            'description' => $offer['description'] ?? $existingProduct?->description,
                            'category' => $mapping['marketCategoryName'] ?? $existingProduct?->category,
                            'canonical_category' => $existingProduct?->canonical_category
                                ?: app(CanonicalCategoryResolver::class)->fromPayload($item, [
                                    $offer['name'] ?? null,
                                    $mapping['marketCategoryName'] ?? null,
                                ]),
                            'market_category_id' => $mapping['marketCategoryId'] ?? $existingProduct?->market_category_id,
                            'market_category_name' => $mapping['marketCategoryName'] ?? $existingProduct?->market_category_name,
                            'price_rub' => $this->extractPriceRubKopeks($offer, $item, $existingProduct),
                            'ym_errors' => array_merge(
                                $mapping['errors'] ?? [],
                                $offer['contentErrors'] ?? []
                            ),
                            'data' => array_merge($existingProduct?->data ?? [], ['ym_raw' => $item]),
                            'shop_id' => $shop->id,
                        ];

                        if ($catalogItem) {
                            $payload = array_merge($payload, [
                                'wildflow_catalog_sku' => $catalogItem->sku,
                                'provider_id' => $existingProduct?->provider_id ?? $catalogItem->provider_id,
                                'brand_id' => $existingProduct?->brand_id ?? $catalogItem->brand_id,
                            ]);
                            $totalLinked++;
                        } elseif ($existingProduct?->wildflow_catalog_sku) {
                            $payload['wildflow_catalog_sku'] = $existingProduct->wildflow_catalog_sku;
                            $payload['provider_id'] = $existingProduct->provider_id;
                            $payload['brand_id'] = $existingProduct->brand_id;
                        }

                        $product = Product::updateOrCreate(
                            ['sku' => $offer['offerId']],
                            $payload
                        );

                        WildflowSkuAlias::syncForProduct($product);
                        $totalImported++;
                    }
                    
                    $this->info("Imported {$totalImported} products, linked {$totalLinked} to provider catalog...");

                } catch (\Exception $e) {
                    $this->error("Error pulling batch: " . $e->getMessage());
                    break;
                }
            } while ($pageToken);

            $this->info("Finalized shop {$shop->name}. Total: {$totalImported} products, linked: {$totalLinked}.");
        }

        $this->info('Full catalog pull completed!');
        return 0;
    }

    private function resolveCatalogItem(string $offerId, string $name): ?WildflowCatalog
    {
        $catalogItem = WildflowCatalog::findForOrderOfferSku($offerId);
        if ($catalogItem) {
            return $catalogItem;
        }

        $providerProduct = $this->resolveProviderProductByCommercialShape($offerId, $name);
        if (! $providerProduct) {
            return null;
        }

        $catalogSku = $providerProduct->market_sku ?: $providerProduct->sku;

        return $catalogSku ? WildflowCatalog::query()->where('sku', $catalogSku)->first() : null;
    }

    private function extractPriceRubKopeks(array $offer, array $item, ?Product $existingProduct = null): int
    {
        $price = data_get($offer, 'basicPrice.value')
            ?? data_get($offer, 'basic_price.value')
            ?? data_get($offer, 'price.value')
            ?? data_get($offer, 'price')
            ?? data_get($item, 'price.value')
            ?? data_get($item, 'price')
            ?? data_get($item, 'basicPrice.value');

        if (is_numeric($price) && (float) $price > 0) {
            return (int) round(((float) $price) * 100);
        }

        return (int) ($existingProduct?->price_rub ?? 0);
    }

    private function resolveProviderProductByCommercialShape(string $offerId, string $name): ?ProviderProduct
    {
        $text = Str::upper($offerId.' '.$name);
        $brand = $this->inferBrand($text);
        $currency = $this->inferCurrency($text);
        $amount = $this->inferAmount($text, $currency);
        $region = $this->inferRegion($text, $currency);

        if (! $brand || ! $currency || $amount === null) {
            return null;
        }

        return ProviderProduct::query()
            ->with('provider')
            ->where('is_active', true)
            ->where('currency', $currency)
            ->where(function ($query) use ($amount) {
                $query->whereBetween('retail_price', [$amount - 0.01, $amount + 0.01])
                    ->orWhere(function ($rangeQuery) use ($amount) {
                        $rangeQuery->where('min_price', '<=', $amount)
                            ->where('max_price', '>=', $amount);
                    });
            })
            ->where(function ($query) use ($brand) {
                $query->where('name', 'like', "%{$brand}%")
                    ->orWhere('category', 'like', "%{$brand}%")
                    ->orWhereHas('brand', fn ($brandQuery) => $brandQuery->where('name', 'like', "%{$brand}%"));
            })
            ->when($region, function ($query) use ($region) {
                $query->where(function ($regionQuery) use ($region) {
                    $regionQuery->where('name', 'like', "%{$region}%")
                        ->orWhere('category', 'like', "%{$region}%")
                        ->orWhereHas('region', fn ($countryQuery) => $countryQuery
                            ->where('code', $region)
                            ->orWhere('name_en', 'like', "%{$region}%")
                            ->orWhere('name_ru', 'like', "%{$region}%"));
                });
            })
            ->whereHas('provider', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->first();
    }

    private function inferBrand(string $text): ?string
    {
        return match (true) {
            str_contains($text, 'PLAYSTATION') || str_contains($text, 'PSN') => 'PlayStation',
            str_contains($text, 'APPLE') || str_contains($text, 'APP STORE') || str_contains($text, 'ITUNES') => 'Apple',
            str_contains($text, 'STEAM') => 'Steam',
            str_contains($text, 'XBOX') => 'Xbox',
            str_contains($text, 'GOOGLE') => 'Google',
            default => null,
        };
    }

    private function inferCurrency(string $text): ?string
    {
        foreach (['USD', 'GBP', 'EUR', 'TRY', 'AED', 'RUB'] as $currency) {
            if (str_contains($text, $currency)) {
                return $currency;
            }
        }

        return match (true) {
            str_contains($text, '£') => 'GBP',
            str_contains($text, '$') => 'USD',
            str_contains($text, '€') => 'EUR',
            default => null,
        };
    }

    private function inferAmount(string $text, ?string $currency): ?float
    {
        if ($currency && preg_match('/(\d+(?:[.,]\d+)?)\s*'.preg_quote($currency, '/').'/u', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        if (preg_match('/(?:[$£€])\s*(\d+(?:[.,]\d+)?)/u', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }

    private function inferRegion(string $text, ?string $currency): ?string
    {
        return match (true) {
            str_contains($text, ' UK ') || str_contains($text, '-UK-') || $currency === 'GBP' => 'UK',
            str_contains($text, ' US ') || str_contains($text, '-US-') || $currency === 'USD' => 'US',
            str_contains($text, ' UAE ') || str_contains($text, '-UAE-') || $currency === 'AED' => 'UAE',
            default => null,
        };
    }
}
