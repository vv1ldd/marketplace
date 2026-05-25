<?php

namespace App\Services;

use App\Models\CanonicalProductIdentity;
use App\Models\DemandGap;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DiscoveryEntityGraphService
{
    private const MAX_SCAN = 5000;

    /**
     * @return array<string, mixed>|null
     */
    public function brand(string $slug): ?array
    {
        return $this->brands(self::MAX_SCAN)->firstWhere('slug', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function region(string $slug): ?array
    {
        return $this->regions(self::MAX_SCAN)->firstWhere('slug', $slug);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function brandRegion(string $brandSlug, string $regionSlug): ?array
    {
        $brand = $this->brand($brandSlug);
        $region = $this->region($regionSlug);

        if (! $brand || ! $region) {
            return null;
        }

        $match = $this->brandRegions(self::MAX_SCAN)
            ->first(fn (array $edge): bool => $edge['brand_slug'] === $brand['slug'] && $edge['region_slug'] === $region['slug']);

        return $match
            ? $match + ['brand_node' => $brand, 'region_node' => $region]
            : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function brands(int $limit = 200): Collection
    {
        $demand = $this->demandByEntity('brand_entity_key');

        return CanonicalProductIdentity::query()
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->select('brand')
            ->selectRaw('count(*) as product_count')
            ->selectRaw('sum(seller_offers_count) as seller_offer_count')
            ->selectRaw('sum(provider_candidates_count) as provider_count')
            ->groupBy('brand')
            ->orderByDesc('product_count')
            ->orderBy('brand')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => $this->brandNode(
                (string) $row->brand,
                (int) $row->getAttribute('product_count'),
                (int) $row->getAttribute('seller_offer_count'),
                (int) $row->getAttribute('provider_count'),
                $demand[Str::slug((string) $row->brand)] ?? [],
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function regions(int $limit = 200): Collection
    {
        $demand = $this->demandByEntity('region_entity_key');

        return CanonicalProductIdentity::query()
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->whereRaw('lower(region) <> ?', ['global'])
            ->select('region')
            ->selectRaw('count(*) as product_count')
            ->selectRaw('sum(seller_offers_count) as seller_offer_count')
            ->selectRaw('sum(provider_candidates_count) as provider_count')
            ->groupBy('region')
            ->orderByDesc('product_count')
            ->orderBy('region')
            ->limit($limit)
            ->get()
            ->map(fn (CanonicalProductIdentity $row): array => $this->regionNode(
                (string) $row->region,
                (int) $row->getAttribute('product_count'),
                (int) $row->getAttribute('seller_offer_count'),
                (int) $row->getAttribute('provider_count'),
                $demand[Str::slug((string) $row->region)] ?? [],
            ))
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function brandRegions(int $limit = 500): Collection
    {
        return CanonicalProductIdentity::query()
            ->whereNotNull('brand')
            ->where('brand', '<>', '')
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->whereRaw('lower(region) <> ?', ['global'])
            ->select('brand', 'region')
            ->selectRaw('count(*) as product_count')
            ->selectRaw('sum(seller_offers_count) as seller_offer_count')
            ->selectRaw('sum(provider_candidates_count) as provider_count')
            ->groupBy('brand', 'region')
            ->orderByDesc('product_count')
            ->orderBy('brand')
            ->orderBy('region')
            ->limit($limit)
            ->get()
            ->map(function (CanonicalProductIdentity $row): array {
                $brand = (string) $row->brand;
                $region = (string) $row->region;

                return [
                    'type' => 'brand_region',
                    'brand' => $brand,
                    'brand_slug' => Str::slug($brand),
                    'region' => $region,
                    'region_slug' => Str::slug($region),
                    'label' => trim($brand.' '.Str::upper($region)),
                    'product_count' => (int) $row->getAttribute('product_count'),
                    'seller_offer_count' => (int) $row->getAttribute('seller_offer_count'),
                    'provider_count' => (int) $row->getAttribute('provider_count'),
                    'url' => route('meanly.catalog.brand-regions.show', [
                        'brandSlug' => Str::slug($brand),
                        'regionSlug' => Str::slug($region),
                    ]),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function regionsForBrand(string $brand, int $limit = 24): Collection
    {
        $brandSlug = Str::slug($brand);

        return $this->brandRegions()
            ->filter(fn (array $edge): bool => $edge['brand_slug'] === $brandSlug)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function brandsForRegion(string $region, int $limit = 24): Collection
    {
        $regionSlug = Str::slug($region);

        return $this->brandRegions()
            ->filter(fn (array $edge): bool => $edge['region_slug'] === $regionSlug)
            ->take($limit)
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categoriesForBrand(string $brand, int $limit = 24): Collection
    {
        return $this->categoryEdges('brand', $brand, $limit);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function categoriesForRegion(string $region, int $limit = 24): Collection
    {
        return $this->categoryEdges('region', $region, $limit);
    }

    public function brandUrl(string $brand): string
    {
        return route('meanly.catalog.brands.show', Str::slug($brand));
    }

    public function regionUrl(string $region): string
    {
        return route('meanly.catalog.regions.show', Str::slug($region));
    }

    public function brandRegionUrl(string $brand, string $region): string
    {
        return route('meanly.catalog.brand-regions.show', [
            'brandSlug' => Str::slug($brand),
            'regionSlug' => Str::slug($region),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function brandNode(string $brand, int $products, int $sellerOffers, int $providers, array $demand): array
    {
        return [
            'type' => 'brand',
            'name' => $brand,
            'slug' => Str::slug($brand),
            'label' => $brand,
            'product_count' => $products,
            'seller_offer_count' => $sellerOffers,
            'provider_count' => $providers,
            'demand' => $demand,
            'url' => $this->brandUrl($brand),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function regionNode(string $region, int $products, int $sellerOffers, int $providers, array $demand): array
    {
        return [
            'type' => 'region',
            'name' => $region,
            'slug' => Str::slug($region),
            'label' => Str::upper($region),
            'product_count' => $products,
            'seller_offer_count' => $sellerOffers,
            'provider_count' => $providers,
            'demand' => $demand,
            'url' => $this->regionUrl($region),
        ];
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    private function demandByEntity(string $column): array
    {
        return DemandGap::query()
            ->whereNotNull($column)
            ->select($column)
            ->selectRaw('sum(search_volume) as searches')
            ->selectRaw('sum(estimated_lost_gmv) as estimated_lost_gmv')
            ->selectRaw('max(opportunity_score) as max_opportunity_score')
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn (DemandGap $row): array => [
                (string) $row->getAttribute($column) => [
                    'searches' => (int) $row->getAttribute('searches'),
                    'estimated_lost_gmv' => (float) $row->getAttribute('estimated_lost_gmv'),
                    'max_opportunity_score' => (float) $row->getAttribute('max_opportunity_score'),
                ],
            ])
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function categoryEdges(string $entityColumn, string $value, int $limit): Collection
    {
        return CanonicalProductIdentity::query()
            ->where($entityColumn, $value)
            ->whereNotNull('canonical_category')
            ->select('canonical_category')
            ->selectRaw('count(*) as product_count')
            ->groupBy('canonical_category')
            ->orderByDesc('product_count')
            ->limit($limit)
            ->get()
            ->map(function (CanonicalProductIdentity $row): array {
                $category = (string) $row->canonical_category;
                $meta = (array) config("catalog_taxonomy.categories.{$category}", []);

                return [
                    'type' => 'category_edge',
                    'category' => $category,
                    'label' => $meta['label_ru'] ?? Str::headline($category),
                    'product_count' => (int) $row->getAttribute('product_count'),
                    'url' => route('meanly.catalog.categories.show', $category),
                ];
            })
            ->values();
    }
}
