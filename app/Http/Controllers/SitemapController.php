<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProviderProduct;
use App\Services\CanonicalProductPageService;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\DiscoveryEntityGraphService;
use App\Services\LlmServiceFactsService;
use App\Services\MeanlyFirstPartyStorefrontService;
use App\Services\ProductIntentResolutionService;
use App\Services\ProviderNetworkCatalogService;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow:',
            '',
            'LLMs: '.parse_url(route('llms.txt'), PHP_URL_PATH),
            'LLMs-Catalog: '.parse_url(route('llms.catalog.index'), PHP_URL_PATH),
            'LLMs-Catalog-Understanding: '.parse_url(route('llms.catalog.understand'), PHP_URL_PATH),
            'LLMs-Catalog-Retrieval: '.parse_url(route('llms.catalog.retrieve'), PHP_URL_PATH),
            'LLMs-Services: '.parse_url(route('llms.services.index'), PHP_URL_PATH),
            'LLMs-Network-Category-Pattern: /llms/network/categories/{canonical_category}.json',
            'LLMs-Network-Identity-Pattern: /llms/network/categories/{canonical_category}/identities.json',
            'LLMs-Network-Product-Pattern: /llms/network/products/{id-slug}.json',
            'LLMs-Network-Offer-Pattern: /llms/network/products/{id-slug}/offers.json',
            'LLMs-Network-Intent-Pattern: /llms/network/products/{id-slug}/intents/{intent}.json',
            'LLMs-Canonical-Product-Pattern: /llms/catalog/products/{canonical-identity-slug}.json',
            'LLMs-Canonical-Intent-Pattern: /llms/catalog/products/{canonical-identity-slug}/intents/{intent}.json',
            'Canonical-Product-Pattern: /catalog/products/{canonical-identity-slug}',
            '',
            'Sitemap: '.route('sitemap.index'),
            'Sitemap: '.route('sitemap.products'),
            'Sitemap: '.route('sitemap.categories'),
            'Sitemap: '.route('sitemap.brands'),
            'Sitemap: '.route('sitemap.regions'),
            'Sitemap: '.route('sitemap.brand-regions'),
            'Sitemap: '.route('sitemap.services'),
            'Sitemap: '.route('sitemap.network'),
            'Sitemap: '.route('sitemap.llms'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function index(): Response
    {
        return $this->xml($this->sitemapIndex([
            route('sitemap.products'),
            route('sitemap.categories'),
            route('sitemap.brands'),
            route('sitemap.regions'),
            route('sitemap.brand-regions'),
            route('sitemap.services'),
            route('sitemap.network'),
            route('sitemap.llms'),
        ]));
    }

    public function products(MeanlyFirstPartyStorefrontService $storefront): Response
    {
        $products = $storefront->marketplaceProductsQuery()
            ->latest('updated_at')
            ->limit(5000)
            ->get(['id', 'slug', 'updated_at']);

        return $this->xml($this->urlSet($products->map(fn (Product $product) => [
            'loc' => route('meanly.storefront.products.show', $product->slug),
            'lastmod' => optional($product->updated_at)->toAtomString(),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ])));
    }

    public function categories(CanonicalStorefrontHomepageService $catalog): Response
    {
        $summaries = $catalog->publicCategorySummaries()
            ->filter(fn (array $category) => (int) $category['product_count'] > 0);

        $urls = $summaries->map(fn (array $category) => [
            'loc' => route('meanly.catalog.categories.show', $category['slug']),
            'changefreq' => 'daily',
            'priority' => '0.75',
        ]);

        // Append SEO collection tag pages
        $seoUrls = \App\Models\SeoCollection::where('is_active', true)->get()->map(fn (\App\Models\SeoCollection $collection) => [
            'loc' => route('meanly.catalog.collections.show', $collection->slug),
            'changefreq' => 'daily',
            'priority' => '0.70',
        ]);

        $urls = $urls->concat($seoUrls);

        $urls->prepend([
            'loc' => route('meanly.catalog.index'),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ]);

        return $this->xml($this->urlSet($urls));
    }

    public function brands(DiscoveryEntityGraphService $graph): Response
    {
        return $this->xml($this->urlSet($graph->brands(1000)->map(fn (array $brand) => [
            'loc' => $brand['url'],
            'changefreq' => 'daily',
            'priority' => $brand['seller_offer_count'] > 0 ? '0.72' : '0.62',
        ])));
    }

    public function regions(DiscoveryEntityGraphService $graph): Response
    {
        return $this->xml($this->urlSet($graph->regions(1000)->map(fn (array $region) => [
            'loc' => $region['url'],
            'changefreq' => 'daily',
            'priority' => $region['seller_offer_count'] > 0 ? '0.68' : '0.58',
        ])));
    }

    public function brandRegions(DiscoveryEntityGraphService $graph): Response
    {
        return $this->xml($this->urlSet($graph->brandRegions(2000)->map(fn (array $edge) => [
            'loc' => $edge['url'],
            'changefreq' => 'daily',
            'priority' => $edge['seller_offer_count'] > 0 ? '0.74' : '0.64',
        ])));
    }

    public function services(LlmServiceFactsService $facts): Response
    {
        return $this->xml($this->urlSet($facts->services()->map(fn (array $service) => [
            'loc' => $service['url'],
            'changefreq' => 'weekly',
            'priority' => '0.7',
        ])->prepend([
            'loc' => route('business.services.index'),
            'changefreq' => 'weekly',
            'priority' => '0.8',
        ])->prepend([
            'loc' => route('business.landing'),
            'changefreq' => 'weekly',
            'priority' => '0.75',
        ])));
    }

    public function network(ProviderNetworkCatalogService $network, CanonicalProductPageService $canonicalProducts): Response
    {
        $categoryUrls = $network->categorySummaries()
            ->map(fn (array $category) => [
                'loc' => route('meanly.network.categories.show', $category['slug']),
                'changefreq' => 'weekly',
                'priority' => '0.55',
            ]);

        $productUrls = $network->candidatesQuery()
            ->latest('updated_at')
            ->limit(1000)
            ->get()
            ->filter(fn (ProviderProduct $product) => (bool) data_get($network->indexingPolicyForProduct($product), 'indexable'))
            ->map(fn (ProviderProduct $product) => [
                'loc' => route('meanly.network.products.show', $network->publicSlug($product)),
                'lastmod' => optional($product->updated_at)->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => $network->quality($product) === 'ready' ? '0.45' : '0.35',
            ]);

        $canonicalProductUrls = $canonicalProducts->sitemapEntries(200);

        return $this->xml($this->urlSet(collect([
            ['loc' => route('meanly.network.index'), 'changefreq' => 'weekly', 'priority' => '0.6'],
        ])->concat($categoryUrls)->concat($productUrls)->concat($canonicalProductUrls)));
    }

    public function llms(MeanlyFirstPartyStorefrontService $storefront, LlmServiceFactsService $serviceFacts, ProviderNetworkCatalogService $network, ProductIntentResolutionService $intentResolver, CanonicalProductPageService $canonicalProducts, CanonicalStorefrontHomepageService $catalog): Response
    {
        $categoryUrls = $catalog->publicCategorySummaries()
            ->filter(fn (array $category) => (int) $category['product_count'] > 0)
            ->map(fn (array $category) => [
                'loc' => route('llms.categories.show', $category['slug']),
                'changefreq' => 'daily',
                'priority' => '0.5',
            ]);

        $productUrls = $storefront->marketplaceProductsQuery()
            ->latest('updated_at')
            ->limit(1000)
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn (Product $product) => [
                'loc' => route('llms.products.show', $product->slug),
                'lastmod' => optional($product->updated_at)->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.5',
            ]);

        $serviceUrls = $serviceFacts->services()
            ->map(fn (array $service) => [
                'loc' => route('llms.services.show', $service['slug']),
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ]);

        $networkCategoryUrls = $network->categorySummaries()
            ->map(fn (array $category) => [
                'loc' => route('llms.network.categories.show', $category['slug']),
                'changefreq' => 'weekly',
                'priority' => '0.45',
            ]);

        $networkProducts = $network->candidatesQuery()
            ->latest('updated_at')
            ->limit(500)
            ->get()
            ->filter(function (ProviderProduct $product) use ($network) {
                $policy = $network->indexingPolicyForProduct($product);

                return (bool) data_get($policy, 'indexable')
                    || data_get($policy, 'surface') === 'llm_only';
            })
            ->values();

        $networkProductUrls = $networkProducts
            ->map(fn (ProviderProduct $product) => [
                'loc' => route('llms.network.products.show', $network->publicSlug($product)),
                'lastmod' => optional($product->updated_at)->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.45',
            ]);

        $networkOfferUrls = $networkProducts
            ->filter(fn (ProviderProduct $product) => collect($network->facts($product)['seller_offers']['offers'] ?? [])
                ->contains(fn (array $offer) => (bool) data_get($offer, 'indexing.indexable')))
            ->map(fn (ProviderProduct $product) => [
                'loc' => route('llms.network.products.offers', $network->publicSlug($product)),
                'lastmod' => optional($product->updated_at)->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.5',
            ]);

        $networkIntentUrls = $networkProducts
            ->take(100)
            ->flatMap(fn (ProviderProduct $product) => $intentResolver->indexableIntentResolutions($product)
                ->map(fn (array $resolution) => [
                    'loc' => $resolution['machine_readable_at'],
                    'lastmod' => optional($product->updated_at)->toAtomString(),
                    'changefreq' => 'daily',
                    'priority' => $resolution['intent'] === ProductIntentResolutionService::DEFAULT_INTENT ? '0.52' : '0.48',
                ]));

        $canonicalLlmUrls = $canonicalProducts->sitemapEntries(200, includeLlmOnly: true)
            ->map(fn (array $entry) => [
                'loc' => route('llms.catalog.canonical-products.show', basename((string) parse_url((string) $entry['loc'], PHP_URL_PATH))),
                'lastmod' => $entry['lastmod'] ?? null,
                'changefreq' => 'weekly',
                'priority' => '0.5',
            ]);

        return $this->xml($this->urlSet(collect([
            ['loc' => route('llms.txt'), 'changefreq' => 'daily', 'priority' => '0.6'],
            ['loc' => route('llms.catalog.index'), 'changefreq' => 'daily', 'priority' => '0.55'],
            ['loc' => route('llms.services.index'), 'changefreq' => 'weekly', 'priority' => '0.55'],
        ])->concat($categoryUrls)->concat($productUrls)->concat($serviceUrls)->concat($networkCategoryUrls)->concat($networkProductUrls)->concat($networkOfferUrls)->concat($networkIntentUrls)->concat($canonicalLlmUrls)));
    }

    /**
     * @param  array<int, string>  $locations
     */
    private function sitemapIndex(array $locations): string
    {
        $items = collect($locations)
            ->map(fn (string $loc) => "    <sitemap>\n        <loc>{$this->escape($loc)}</loc>\n    </sitemap>")
            ->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<sitemapindex xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
            .$items."\n"
            ."</sitemapindex>\n";
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function urlSet(Collection $entries): string
    {
        $items = $entries
            ->filter(fn (array $entry) => ! empty($entry['loc']))
            ->unique('loc')
            ->map(function (array $entry) {
                $xml = "    <url>\n        <loc>{$this->escape((string) $entry['loc'])}</loc>";
                foreach ((array) ($entry['alternates'] ?? []) as $alternate) {
                    $href = (string) data_get($alternate, 'url', '');
                    $hreflang = (string) data_get($alternate, 'hreflang', '');
                    if ($href !== '' && $hreflang !== '') {
                        $xml .= "\n        <xhtml:link rel=\"alternate\" hreflang=\"{$this->escape($hreflang)}\" href=\"{$this->escape($href)}\" />";
                    }
                }
                foreach (['lastmod', 'changefreq', 'priority'] as $field) {
                    if (! empty($entry[$field])) {
                        $xml .= "\n        <{$field}>{$this->escape((string) $entry[$field])}</{$field}>";
                    }
                }

                return $xml."\n    </url>";
            })
            ->implode("\n");

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            ."<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\">\n"
            .$items."\n"
            ."</urlset>\n";
    }

    private function xml(string $content): Response
    {
        return response($content, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
