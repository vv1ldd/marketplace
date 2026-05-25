<?php

namespace App\Http\Controllers;

use App\Services\CanonicalStorefrontHomepageService;
use App\Services\DiscoveryEntityGraphService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeanlyCatalogCategoryController extends Controller
{
    public function index(Request $request, CanonicalStorefrontHomepageService $catalog, \App\Services\CatalogSearchLogService $logService): View
    {
        $products = $catalog->catalogPage($request);

        if (filled($request->query('q'))) {
            $logService->log(
                (string) $request->query('q'),
                'storefront',
                $products->total()
            );
        }

        $facets = $catalog->catalogFacets($request);
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => route('meanly.catalog.index').'#catalog',
                    'url' => route('meanly.catalog.index'),
                    'name' => 'Meanly catalog',
                    'description' => 'Общий каталог цифровых товаров Meanly с фильтрами по категории, бренду и номиналу.',
                    'inLanguage' => 'ru',
                    'mainEntity' => [
                        '@id' => route('meanly.catalog.index').'#items',
                    ],
                ],
                $catalog->itemListJsonLd($products, 'Meanly catalog') + [
                    '@id' => route('meanly.catalog.index').'#items',
                ],
            ],
        ];

        return view('catalog.index', [
            'categories' => $catalog->publicCategorySummaries()
                ->filter(fn (array $category) => (int) $category['product_count'] > 0)
                ->values(),
            'products' => $products,
            'facets' => $facets,
            'jsonLd' => $jsonLd,
        ]);
    }

    public function show(string $category, Request $request, CanonicalStorefrontHomepageService $catalog): View
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);

        $meta = (array) config("catalog_taxonomy.categories.{$category}", []);
        $products = $catalog->categoryPage($category, $request);
        $facets = $catalog->categoryFacets($category, $request);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => route('meanly.catalog.categories.show', $category).'#category',
                    'url' => route('meanly.catalog.categories.show', $category),
                    'name' => $meta['label_en'] ?? $meta['label_ru'] ?? $category,
                    'description' => $meta['description_ru'] ?? null,
                    'inLanguage' => 'ru',
                    'mainEntity' => [
                        '@id' => route('meanly.catalog.categories.show', $category).'#items',
                    ],
                ],
                $catalog->itemListJsonLd($products, $meta['label_en'] ?? $category) + [
                    '@id' => route('meanly.catalog.categories.show', $category).'#items',
                ],
            ],
        ];

        return view('catalog.show', [
            'category' => $category,
            'meta' => $meta,
            'products' => $products,
            'facets' => $facets,
            'jsonLd' => $jsonLd,
        ]);
    }

    public function group(string $category, string $brandSlug, string $kindSlug, Request $request, CanonicalStorefrontHomepageService $catalog): View
    {
        $data = $catalog->productGroupPage($category, $brandSlug, $kindSlug, $request);

        abort_unless($data !== null, 404);

        $group = $data['group'];
        $products = $data['products'];
        $meta = $data['meta'];
        $facets = $data['facets'];
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => $group['canonical_url'].'#group',
                    'url' => $group['canonical_url'],
                    'name' => $group['title'],
                    'description' => $group['description'],
                    'inLanguage' => 'ru',
                    'mainEntity' => [
                        '@id' => $group['canonical_url'].'#items',
                    ],
                ],
                $catalog->itemListJsonLd($products, (string) $group['title']) + [
                    '@id' => $group['canonical_url'].'#items',
                ],
            ],
        ];

        return view('catalog.show', [
            'category' => $category,
            'meta' => $meta,
            'products' => $products,
            'facets' => $facets,
            'group' => $group,
            'jsonLd' => $jsonLd,
        ]);
    }

    public function collection(string $slug, Request $request, CanonicalStorefrontHomepageService $catalog): View
    {
        $collection = \App\Models\SeoCollection::where('slug', $slug)->where('is_active', true)->firstOrFail();

        // Inject the search query as 'q' into request
        $request->merge(['q' => $collection->search_query]);

        $products = $catalog->catalogPage($request);
        $facets = $catalog->catalogFacets($request);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => route('meanly.catalog.collections.show', $slug).'#collection',
                    'url' => route('meanly.catalog.collections.show', $slug),
                    'name' => $collection->title,
                    'description' => $collection->meta_description,
                    'inLanguage' => 'ru',
                    'mainEntity' => [
                        '@id' => route('meanly.catalog.collections.show', $slug).'#items',
                    ],
                ],
                $catalog->itemListJsonLd($products, $collection->title) + [
                    '@id' => route('meanly.catalog.collections.show', $slug).'#items',
                ],
            ],
        ];

        return view('catalog.index', [
            'collection' => $collection,
            'categories' => $catalog->publicCategorySummaries()
                ->filter(fn (array $category) => (int) $category['product_count'] > 0)
                ->values(),
            'products' => $products,
            'facets' => $facets,
            'jsonLd' => $jsonLd,
        ]);
    }

    public function brand(string $brandSlug, Request $request, CanonicalStorefrontHomepageService $catalog, DiscoveryEntityGraphService $graph): View
    {
        $brand = $graph->brand($brandSlug);
        abort_unless($brand !== null, 404);

        $request->merge(['brand' => $brand['name']]);

        return $this->discoveryLanding(
            $request,
            $catalog,
            [
                'type' => 'brand',
                'eyebrow' => 'Бренд',
                'title' => "{$brand['label']} — цифровые товары и подарочные карты",
                'h1' => "{$brand['label']} в Meanly",
                'description' => "Каноническая страница бренда {$brand['label']}: подарочные карты, игровые коды, подписки и пополнения из единого каталога Meanly.",
                'canonical_url' => $brand['url'],
                'related_regions' => $graph->regionsForBrand($brand['name']),
                'related_categories' => $graph->categoriesForBrand($brand['name']),
            ],
            $graph,
        );
    }

    public function region(string $regionSlug, Request $request, CanonicalStorefrontHomepageService $catalog, DiscoveryEntityGraphService $graph): View
    {
        $region = $graph->region($regionSlug);
        abort_unless($region !== null, 404);

        $request->merge(['region' => $region['name']]);

        return $this->discoveryLanding(
            $request,
            $catalog,
            [
                'type' => 'region',
                'eyebrow' => 'Регион',
                'title' => "{$region['label']} — цифровые товары для региона",
                'h1' => "Цифровые товары {$region['label']}",
                'description' => "Региональная витрина Meanly для {$region['label']}: бренды, пополнения, подписки и подарочные карты с понятной структурой каталога.",
                'canonical_url' => $region['url'],
                'related_brands' => $graph->brandsForRegion($region['name']),
                'related_categories' => $graph->categoriesForRegion($region['name']),
            ],
            $graph,
        );
    }

    public function brandRegion(string $brandSlug, string $regionSlug, Request $request, CanonicalStorefrontHomepageService $catalog, DiscoveryEntityGraphService $graph): View
    {
        $edge = $graph->brandRegion($brandSlug, $regionSlug);
        abort_unless($edge !== null, 404);
        $brand = $edge['brand_node'];
        $region = $edge['region_node'];

        $request->merge([
            'brand' => $brand['name'],
            'region' => $region['name'],
        ]);

        return $this->discoveryLanding(
            $request,
            $catalog,
            [
                'type' => 'brand_region',
                'eyebrow' => 'Бренд × регион',
                'title' => "{$brand['label']} {$region['label']} — карты, коды и пополнения",
                'h1' => "{$brand['label']} {$region['label']}",
                'description' => "Пересечение бренда {$brand['label']} и региона {$region['label']}: канонические товары, активные офферы и будущий спрос в каталоге Meanly.",
                'canonical_url' => $edge['url'],
                'related_categories' => $graph->categoriesForBrand($brand['name']),
            ],
            $graph,
        );
    }

    /**
     * @param  array<string, mixed>  $landing
     */
    private function discoveryLanding(Request $request, CanonicalStorefrontHomepageService $catalog, array $landing, DiscoveryEntityGraphService $graph): View
    {
        $products = $catalog->catalogPage($request);
        $facets = $catalog->catalogFacets($request);
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'CollectionPage',
                    '@id' => $landing['canonical_url'].'#discovery',
                    'url' => $landing['canonical_url'],
                    'name' => $landing['title'],
                    'description' => $landing['description'],
                    'inLanguage' => 'ru',
                    'mainEntity' => [
                        '@id' => $landing['canonical_url'].'#items',
                    ],
                ],
                $catalog->itemListJsonLd($products, (string) $landing['title']) + [
                    '@id' => $landing['canonical_url'].'#items',
                ],
            ],
        ];

        return view('catalog.index', [
            'landing' => $landing,
            'categories' => $catalog->publicCategorySummaries()
                ->filter(fn (array $category) => (int) $category['product_count'] > 0)
                ->values(),
            'products' => $products,
            'facets' => $facets,
            'jsonLd' => $jsonLd,
        ]);
    }
}
