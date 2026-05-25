<?php

namespace App\Services;

use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use App\Models\ProviderProduct;
use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SellerOfferRankingService
{
    public function __construct(
        private readonly MeanlyFirstPartyStorefrontService $storefront,
        private readonly VaultTransitService $vault,
    ) {}

    /**
     * @return EloquentCollection<int, Product>
     */
    public function offersForProviderProduct(ProviderProduct $providerProduct): EloquentCollection
    {
        $providerProduct->loadMissing('brand');

        $bidx = collect([$providerProduct->market_sku, $providerProduct->sku])
            ->filter()
            ->map(fn (string $sku) => $this->vault->computeBlindIndex($sku))
            ->unique()
            ->values();

        if ($bidx->isEmpty()) {
            return new EloquentCollection();
        }

        return Product::query()
            ->with(['brand', 'shop.legalEntity', 'salesChannels'])
            ->whereIn('wildflow_catalog_sku_bidx', $bidx->all())
            ->where('is_active', true)
            ->whereHas('shop', fn ($query) => $query->where('is_active', true))
            ->whereHas('salesChannels', function ($query) {
                $query->where('channel', $this->storefront->storefrontChannel())
                    ->whereColumn('product_sales_channels.shop_id', 'products.shop_id')
                    ->where('is_enabled', true);
            })
            ->get()
            ->filter(fn (Product $product) => $this->isCompatibleOffer($providerProduct, $product))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rankedOffersForProviderProduct(ProviderProduct $providerProduct): Collection
    {
        $offers = $this->offersForProviderProduct($providerProduct);

        return $this->rankedOffersForProducts($offers);
    }

    /**
     * @param  Collection<int, Product>  $offers
     * @return Collection<int, array<string, mixed>>
     */
    public function rankedOffersForProducts(Collection $offers): Collection
    {
        $offers = $offers instanceof EloquentCollection
            ? $offers
            : new EloquentCollection($offers->all());
        $signals = $this->signals($offers);

        return $offers
            ->map(fn (Product $product) => $this->offerFacts($product, $signals))
            ->sortByDesc(fn (array $offer) => $offer['ranking']['score'])
            ->values()
            ->map(function (array $offer, int $index) {
                $offer['ranking']['position'] = $index + 1;

                return $offer;
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rankedOffers
     * @return array<string, mixed>|null
     */
    public function bestOffer(Collection $rankedOffers): ?array
    {
        return $rankedOffers->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function offerFacts(Product $product, array $signals): array
    {
        $priceRub = round(((float) ($product->price_rub ?? 0)) / 100, 2);
        $score = 0;
        $badges = [];
        $metrics = [];

        $minPrice = (float) ($signals['min_price'] ?? 0);
        $maxPrice = (float) ($signals['max_price'] ?? 0);
        if ($priceRub > 0 && $minPrice > 0 && $maxPrice > 0) {
            $range = max(1, $maxPrice - $minPrice);
            $priceScore = $maxPrice === $minPrice ? 30 : (int) round(35 * (1 - (($priceRub - $minPrice) / $range)));
            $score += max(0, min(35, $priceScore));
            $badges[] = $priceRub <= $minPrice ? 'best_price' : 'competitive_price';
        }

        $stockCount = (int) ($signals['stock'][$product->id] ?? 0);
        if ($stockCount > 0) {
            $score += min(25, 12 + $stockCount);
            $badges[] = 'in_stock';
        } elseif ($product->shop?->auto_purchase_enabled || $product->auto_replenish_enabled) {
            $score += 10;
            $badges[] = 'auto_purchase';
        }

        $soldCount = (int) ($signals['sales'][$product->shop_id.'|'.$product->sku] ?? 0);
        if ($soldCount > 0) {
            $score += min(15, (int) round(15 * ($soldCount / max(1, (int) $signals['max_sales']))));
            $badges[] = 'selling_recently';
        }

        $sellerOrders = $signals['seller_orders'][$product->shop_id] ?? null;
        $totalOrders = (int) ($sellerOrders?->total_orders ?? 0);
        $completedOrders = (int) ($sellerOrders?->completed_orders ?? 0);
        if ($totalOrders > 0) {
            $completionRate = $completedOrders / max(1, $totalOrders);
            $score += (int) round(($completionRate * 15) + (min($totalOrders, 20) / 20 * 10));
            if ($completionRate >= 0.9 && $totalOrders >= 3) {
                $badges[] = 'reliable_seller';
            }
        } else {
            $score += 6;
            $badges[] = 'new_seller';
        }

        $metrics = [
            'price_rub' => $priceRub,
            'stock_count' => $stockCount,
            'sold_30_days' => $soldCount,
            'seller_orders_90_days' => $totalOrders,
            'seller_completed_90_days' => $completedOrders,
        ];

        return [
            'type' => 'SellerOffer',
            'product_id' => $product->id,
            'sku' => $product->sku,
            'url' => route('meanly.storefront.products.show', $product->slug),
            'name' => $product->name,
            'seller' => [
                'shop_id' => $product->shop_id,
                'name' => $product->shop?->name,
                'legal_entity' => $product->shop?->legalEntity?->short_name ?: $product->shop?->legalEntity?->name,
            ],
            'price' => [
                'amount' => $priceRub,
                'currency' => 'RUB',
            ],
            'availability' => $stockCount > 0 ? 'in_stock' : (($product->shop?->auto_purchase_enabled || $product->auto_replenish_enabled) ? 'auto_purchase' : 'available_to_order'),
            'ranking' => [
                'score' => $score,
                'position' => null,
                'badges' => array_values(array_unique($badges)),
                'metrics' => $metrics,
                'method' => 'price_stock_sales_seller_reliability_v1',
            ],
            'indexing' => [
                'indexable' => $score >= 35 || $stockCount > 0,
                'reason' => $score >= 35 || $stockCount > 0 ? 'ranked_offer' : 'thin_offer',
            ],
        ];
    }

    /**
     * @param  EloquentCollection<int, Product>  $offers
     * @return array<string, mixed>
     */
    private function signals(EloquentCollection $offers): array
    {
        $productIds = $offers->pluck('id')->all();
        $shopIds = $offers->pluck('shop_id')->filter()->unique()->values()->all();
        $prices = $offers
            ->map(fn (Product $product) => ((float) ($product->price_rub ?? 0)) / 100)
            ->filter(fn (float $price) => $price > 0)
            ->values();

        $stock = $productIds === []
            ? collect()
            : WarehouseStock::query()
                ->selectRaw('product_id, SUM(count) as stock_count')
                ->whereIn('product_id', $productIds)
                ->groupBy('product_id')
                ->pluck('stock_count', 'product_id');

        $sales = $shopIds === []
            ? collect()
            : OrderItems::query()
                ->selectRaw('orders.shop_id, order_items.sku, SUM(order_items.count) as sold_count')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereIn('orders.shop_id', $shopIds)
                ->where('orders.sales_channel', $this->storefront->storefrontChannel())
                ->where('orders.created_at', '>=', now()->subDays(30))
                ->groupBy('orders.shop_id', 'order_items.sku')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->shop_id.'|'.$row->sku => (int) $row->sold_count]);

        $sellerOrders = $shopIds === []
            ? collect()
            : Order::query()
                ->selectRaw("shop_id, COUNT(*) as total_orders, SUM(CASE WHEN status IN ('COMPLETED', 'DELIVERED', 'PAID') OR progress_id = 4 THEN 1 ELSE 0 END) as completed_orders")
                ->whereIn('shop_id', $shopIds)
                ->where('sales_channel', $this->storefront->storefrontChannel())
                ->where('created_at', '>=', now()->subDays(90))
                ->groupBy('shop_id')
                ->get()
                ->keyBy('shop_id');

        return [
            'min_price' => $prices->min(),
            'max_price' => $prices->max(),
            'max_sales' => max(1, (int) $sales->max()),
            'stock' => $stock,
            'sales' => $sales,
            'seller_orders' => $sellerOrders,
        ];
    }

    private function isCompatibleOffer(ProviderProduct $providerProduct, Product $product): bool
    {
        if ($providerProduct->canonical_category && $product->canonical_category && $providerProduct->canonical_category !== $product->canonical_category) {
            return false;
        }

        $providerBrand = $this->normalizeLabel($providerProduct->brand?->name);
        $productBrand = $this->normalizeLabel($product->brand?->name);

        if ($providerBrand !== null && $productBrand !== null && $providerBrand !== $productBrand) {
            return false;
        }

        $providerTokens = $this->nameTokens($providerProduct->name);
        $productTokens = $this->nameTokens($product->name);

        if ($providerTokens->intersect($productTokens)->isNotEmpty()) {
            return true;
        }

        if ($providerBrand !== null && $productBrand !== null) {
            return $this->normalizedNameContains($providerProduct->name, $providerBrand)
                && $this->normalizedNameContains($product->name, $productBrand);
        }

        return false;
    }

    private function normalizeLabel(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return (string) Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '')->trim();
    }

    private function normalizedNameContains(?string $name, string $needle): bool
    {
        $haystack = (string) Str::of((string) $name)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', '');

        return $haystack !== '' && str_contains($haystack, $needle);
    }

    /**
     * @return Collection<int, string>
     */
    private function nameTokens(?string $name): Collection
    {
        $stopWords = [
            'gift', 'card', 'voucher', 'code', 'digital', 'instant', 'delivery', 'usd', 'eur', 'rub', 'gbp',
            'global', 'region', 'подарочная', 'карта', 'ваучер', 'мгновенная', 'доставка', 'глобальный',
        ];

        return Str::of((string) $name)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->explode(' ')
            ->map(fn (string $token) => trim($token))
            ->filter(fn (string $token) => strlen($token) > 2 && ! is_numeric($token) && ! in_array($token, $stopWords, true))
            ->unique()
            ->values();
    }
}
