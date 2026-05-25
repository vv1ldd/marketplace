<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CanonicalStorefrontHomepageService;
use App\Services\LlmProductFactsService;
use App\Services\LlmServiceFactsService;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LlmCatalogController extends Controller
{
    public function llmsTxt(CanonicalStorefrontHomepageService $catalog, LlmServiceFactsService $serviceFacts): Response
    {
        $categories = $catalog->publicCategorySummaries();
        $services = $serviceFacts->services();
        $sampleProducts = $catalog->storefrontReadyCards(null, 20);

        $lines = [
            '# Meanly',
            '',
            'Meanly is a marketplace for digital vouchers, gift cards, game top-ups, software licenses, subscriptions, and prepaid digital products.',
            '',
            '## Machine-readable catalog',
            '- Catalog JSON: '.route('llms.catalog.index'),
            '- Catalog query understanding API: '.route('llms.catalog.understand').' (GET for debugging, POST for structured query understanding)',
            '- Catalog retrieval API: '.route('llms.catalog.retrieve').' (GET for debugging, POST for structured retrieval)',
            '- Commerce opportunity graph API: '.route('llms.commerce.opportunities').' (query demand gaps, diagnosis, active cases, and outcomes)',
            '- Commerce entity opportunity pattern: '.url('/llms/commerce/entities/{brands|regions|intersections}/{slug}'),
            '- Commerce entity node pattern: '.url('/llms/commerce/entities/commerce/{commerce-entity-slug}'),
            '- Commerce action effectiveness API: '.route('llms.commerce.actions.effectiveness'),
            '- Intent liquidity graph API: '.route('llms.intents.index').' (intent -> entity -> corridor -> route -> outcome readiness)',
            '- Intent liquidity node pattern: '.url('/llms/intents/{intent-key}'),
            '- Product JSON pattern: '.url('/llms/products/{slug}.json'),
            '- Category JSON pattern: '.url('/llms/categories/{canonical_category}.json'),
            '- Services JSON: '.route('llms.services.index'),
            '- Service JSON pattern: '.url('/llms/services/{slug}.json'),
            '- Provider network category JSON pattern: '.url('/llms/network/categories/{canonical_category}.json'),
            '- Provider network identity groups JSON pattern: '.url('/llms/network/categories/{canonical_category}/identities.json'),
            '- Provider network product JSON pattern: '.url('/llms/network/products/{id-slug}.json'),
            '- Ranked seller offer JSON pattern: '.url('/llms/network/products/{id-slug}/offers.json'),
            '- Product intent decision JSON pattern: '.url('/llms/network/products/{id-slug}/intents/{intent}.json'),
            '- Canonical product page JSON pattern: '.url('/llms/catalog/products/{canonical_identity_slug}.json'),
            '- Canonical product intent JSON pattern: '.url('/llms/catalog/products/{canonical_identity_slug}/intents/{intent}.json'),
            '',
            '## Canonical product categories',
        ];

        foreach ($categories as $category) {
            if ((int) $category['product_count'] <= 0) {
                continue;
            }
            $lines[] = "- {$category['slug']}: {$category['label_en']} / {$category['label_ru']} ({$category['product_count']} products)";
        }

        $lines[] = '';
        $lines[] = '## Sample products';
        foreach ($sampleProducts as $product) {
            $lines[] = "- {$product['name']}: {$product['machine_readable_at']}";
        }

        $lines[] = '';
        $lines[] = '## Meanly services';
        foreach ($services as $service) {
            $lines[] = "- {$service['slug']}: {$service['name']} ({$service['service_type']}) - {$service['url']} (facts: ".route('llms.services.show', $service['slug']).')';
        }

        $lines[] = '';
        $lines[] = '## Notes for AI systems';
        $lines[] = '- Use canonical_category as the stable product taxonomy.';
        $lines[] = '- Use canonical_identity.fingerprint to group duplicate provider candidates and seller products into one logical product when confidence is high or medium.';
        $lines[] = '- Use service_type as the stable service taxonomy.';
        $lines[] = '- Channel categories such as Yandex Market IDs are mappings, not the source of truth.';
        $lines[] = '- Prices are public storefront prices in RUB unless stated otherwise.';
        $lines[] = '- Products are digital and delivered electronically after checkout.';
        $lines[] = '- Provider network product pages resolve intent first, then select the best compatible seller offer for rendering.';
        $lines[] = '- Canonical product pages under /catalog/products/{canonical_identity_slug} group provider-network candidates and seller offers by canonical_identity instead of provider product ID.';
        $lines[] = '- Use /llms/catalog/understand to normalize natural-language catalog queries into intent, filters, entities, confidence, and a rewritten retrieval query.';
        $lines[] = '- Use /llms/catalog/retrieve with query, intent, filters, and limit to retrieve canonical product matches before fetching detailed product JSON.';
        $lines[] = '- Use /llms/commerce/opportunities to retrieve business opportunities by brand, region, category, score, and active case state.';
        $lines[] = '- Use /llms/commerce/entities/{type}/{slug} to inspect demand, diagnosis, cases, and top opportunities for a discovery graph entity.';
        $lines[] = '- Use /llms/commerce/entities/commerce/{slug} to inspect a stable commerce intent node with product/provider links and materialized metrics.';
        $lines[] = '- Use /llms/commerce/actions/effectiveness to learn which operator actions historically improved GMV or conversion.';
        $lines[] = '- Use /llms/intents to inspect buyer, seller, indexer, and liquidity-provider intents across commerce, currency, product, index, and opportunity corridors.';
        $lines[] = '- Add auto_understand=true to /llms/catalog/retrieve to run deterministic query understanding before retrieval; explicit filters and intent take priority.';
        $lines[] = '- Supported provider network intents: best_offer, lowest_price, in_stock, trusted_seller.';
        $lines[] = '- Provider network candidates are not direct checkout offers until a seller enables them in a storefront.';
        $lines[] = '- Services are modeled separately from voucher products and use schema.org Service/Offer.';

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function catalog(CanonicalStorefrontHomepageService $catalog): JsonResponse
    {
        $products = $catalog->storefrontReadyCards(null, 100);

        return response()->json([
            'type' => 'MeanlyLlmCatalog',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'canonical_taxonomy' => [
                'default' => config('catalog_taxonomy.default'),
                'categories' => $catalog->publicCategorySummaries(),
            ],
            'products' => $products
                ->map(fn (array $product) => $this->canonicalProductSummary($product))
                ->values(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function services(LlmServiceFactsService $serviceFacts): JsonResponse
    {
        $services = $serviceFacts->services();

        return response()->json([
            'type' => 'MeanlyLlmServicesCatalog',
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'services' => $services,
            'json_ld' => $serviceFacts->serviceListJsonLd(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function service(string $slug, LlmServiceFactsService $serviceFacts): JsonResponse
    {
        $service = $serviceFacts->find($slug);
        abort_unless($service !== null, 404);

        return response()->json([
            'type' => 'MeanlyLlmService',
            'version' => 1,
            'service' => $service,
            'json_ld' => $serviceFacts->serviceJsonLd($service),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function category(string $category, CanonicalStorefrontHomepageService $catalog): JsonResponse
    {
        abort_unless(array_key_exists($category, (array) config('catalog_taxonomy.categories', [])), 404);

        $products = $catalog->categoryCards($category);

        return response()->json([
            'type' => 'MeanlyLlmCategory',
            'canonical_category' => $category,
            'meta' => config("catalog_taxonomy.categories.{$category}"),
            'product_count' => $products->count(),
            'products' => $products
                ->take(100)
                ->map(fn (array $product) => $this->canonicalProductSummary($product))
                ->values(),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function product(string $slug, MeanlyFirstPartyStorefrontService $storefront, LlmProductFactsService $facts): JsonResponse
    {
        $product = $storefront->marketplaceProductsQuery()
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json([
            'type' => 'MeanlyLlmProduct',
            'version' => 1,
            'product' => $facts->productFacts($product),
            'json_ld' => $facts->productJsonLd($product),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function canonicalProductSummary(array $product): array
    {
        return [
            'type' => 'CanonicalProductIdentity',
            'id' => $product['id'],
            'url' => $product['url'],
            'machine_readable_at' => $product['machine_readable_at'],
            'name' => $product['name'],
            'canonical_category' => $product['category'],
            'canonical_category_label' => $product['category_label'],
            'brand' => $product['brand'],
            'product_family' => $product['product_family'],
            'face_value' => $product['face_value'],
            'face_value_currency' => $product['face_value_currency'],
            'region' => $product['region'],
            'availability' => $product['has_selected_offer'] ? 'seller_offer_available' : 'provider_network_available',
            'provider_candidates' => [
                'count' => $product['provider_count'],
            ],
            'seller_offers' => [
                'count' => $product['seller_offer_count'],
                'best_offer' => $product['selected_offer'],
            ],
            'indexing_policy' => $product['policy'],
        ];
    }
}
