<?php

namespace Tests\Feature;

use App\Services\CanonicalProductSearchSuggestService;
use App\Services\CanonicalStorefrontHomepageService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StorefrontApiCatalogTest extends TestCase
{
    public function test_storefront_catalog_api_returns_dto_action_contract_without_html_or_provider_payloads(): void
    {
        $selectedCard = $this->productCard([
            'id' => 10,
            'slug' => 'steam-us-10',
            'name' => 'Steam US 10 USD',
            'selected_offer' => [
                'product_id' => 99,
                'sku' => 'SECRET-SKU',
                'name' => 'Steam US 10 USD',
                'seller' => ['name' => 'Meanly seller', 'legal_entity' => 'Hidden LLC'],
                'price' => ['amount' => 10, 'currency' => 'USD'],
                'availability' => 'available_to_order',
                'provider_payload' => ['upstream' => 'must-not-leak'],
            ],
            'provider_payload' => ['credential' => 'must-not-leak'],
        ]);

        $networkCard = $this->productCard([
            'id' => 11,
            'slug' => 'netflix-br-25',
            'name' => 'Netflix BR 25 USD',
            'selected_offer' => null,
        ]);

        $groupCard = $this->productCard([
            'id' => 13,
            'slug' => 'steam-group',
            'name' => 'Steam wallet grouped',
            'variant_group' => [
                'is_grouped' => true,
                'variant_count' => 4,
                'region_count' => 2,
                'nominal_count' => 3,
            ],
            'seller_offer_count' => 2,
            'provider_count' => 5,
        ]);

        $this->mock(CanonicalStorefrontHomepageService::class, function ($mock) use ($selectedCard, $networkCard, $groupCard): void {
            $mock->shouldReceive('homepage')
                ->once()
                ->andReturn([
                    'query' => '',
                    'quick_chips' => ['steam'],
                    'featured_products' => collect([$selectedCard]),
                    'provider_network_products' => collect([$networkCard]),
                    'product_groups' => collect([$groupCard]),
                    'categories' => collect([
                        [
                            'slug' => 'shop',
                            'name' => 'Gift & shop',
                            'label' => 'Gift & shop',
                            'intent_key' => 'discover:shop',
                            'count' => 2,
                            'seller_offer_count' => 1,
                            'provider_count' => 3,
                            'url' => 'https://meanly.test/catalog/shop',
                        ],
                    ]),
                    'brands' => collect([
                        [
                            'name' => 'Steam',
                            'count' => 1,
                            'seller_offer_count' => 1,
                            'url' => 'https://meanly.test/catalog/brands/steam',
                        ],
                    ]),
                    'browse_products' => new LengthAwarePaginator([$selectedCard, $networkCard], 2, 24, 1),
                    'stats' => ['public_storefront_products' => 2],
                ]);
        });

        $this->getJson('/api/storefront/v1/catalog')
            ->assertOk()
            ->assertJsonPath('data.contract.name', 'storefront-catalog')
            ->assertJsonPath('data.contract.dto_boundary', 'transitions_not_conditions')
            ->assertJsonPath('data.products.featured.0.type', 'storefront_product')
            ->assertJsonPath('data.products.featured.0.actions.allowed_actions.2', 'CHECKOUT')
            ->assertJsonPath('data.products.featured.0.actions.next_action', 'CHECKOUT')
            ->assertJsonPath('data.products.provider_network.0.actions.blocked_actions.1', 'CHECKOUT')
            ->assertJsonPath('data.products.provider_network.0.actions.blocking_reason', 'no_selected_offer')
            ->assertJsonPath('data.products.groups.0.variant_group.variant_count', 4)
            ->assertJsonPath('data.product_groups.0.seller_offer_count', 2)
            ->assertJsonPath('data.categories.0.actions.allowed_actions.0', 'VIEW')
            ->assertJsonPath('data.categories.0.seller_offer_count', 1)
            ->assertJsonPath('data.categories.0.provider_count', 3)
            ->assertJsonPath('data.categories.0.links.self', 'https://meanly.test/catalog/shop')
            ->assertJsonPath('data.brands.0.seller_offer_count', 1)
            ->assertJsonPath('data.brands.0.links.self', 'https://meanly.test/catalog/brands/steam')
            ->assertJsonMissing(['html'])
            ->assertJsonMissing(['provider_payload' => ['credential' => 'must-not-leak']])
            ->assertJsonMissing(['sku' => 'SECRET-SKU']);
    }

    public function test_storefront_catalog_search_uses_same_dto_contract(): void
    {
        $card = $this->productCard(['id' => 12, 'slug' => 'xbox-global-15', 'name' => 'Xbox Global 15 USD']);

        $this->mock(CanonicalStorefrontHomepageService::class, function ($mock) use ($card): void {
            $mock->shouldReceive('searchPage')
                ->once()
                ->andReturn([
                    'query' => 'xbox',
                    'browse_products' => new LengthAwarePaginator([$card], 1, 24, 1),
                ]);
        });

        $this->getJson('/api/storefront/v1/catalog/search?q=xbox')
            ->assertOk()
            ->assertJsonPath('data.query', 'xbox')
            ->assertJsonPath('data.products.browse.0.slug', 'xbox-global-15')
            ->assertJsonPath('data.products.browse.0.actions.next_action', 'CHECKOUT');
    }

    public function test_storefront_category_projection_returns_real_catalog_results(): void
    {
        config([
            'catalog_taxonomy.intent_corridors.stream' => [
                'intent_key' => 'discover:stream',
                'label_en' => 'Watch & listen',
                'description_ru' => 'Streaming products',
            ],
        ]);

        $card = $this->productCard(['id' => 14, 'slug' => 'spotify-global', 'name' => 'Spotify Global']);

        $this->mock(CanonicalStorefrontHomepageService::class, function ($mock) use ($card): void {
            $mock->shouldReceive('categoryPage')
                ->once()
                ->with('stream', \Mockery::type(\Illuminate\Http\Request::class))
                ->andReturn(new LengthAwarePaginator([$card], 1, 24, 1));
        });

        $this->getJson('/api/storefront/v1/catalog/categories/stream')
            ->assertOk()
            ->assertJsonPath('data.contract.name', 'storefront-catalog')
            ->assertJsonPath('data.surface.type', 'storefront_catalog_category')
            ->assertJsonPath('data.surface.discovery_intent', 'stream')
            ->assertJsonPath('data.products.browse.0.slug', 'spotify-global')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_storefront_group_projection_returns_variant_results(): void
    {
        $card = $this->productCard(['id' => 15, 'slug' => 'psn-us-10', 'name' => 'PlayStation US 10 USD']);

        $this->mock(CanonicalStorefrontHomepageService::class, function ($mock) use ($card): void {
            $mock->shouldReceive('productGroupPage')
                ->once()
                ->with('play', 'playstation', 'gift-cards', \Mockery::type(\Illuminate\Http\Request::class))
                ->andReturn([
                    'group' => [
                        'title' => 'PlayStation Gift Cards',
                        'description' => 'PlayStation wallet variants',
                    ],
                    'meta' => ['description_ru' => 'PlayStation wallet variants'],
                    'facets' => ['regions' => [], 'nominals' => [], 'selected' => []],
                    'products' => new LengthAwarePaginator([$card], 1, 24, 1),
                ]);
        });

        $this->getJson('/api/storefront/v1/catalog/groups/play/playstation/gift-cards')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-catalog-group')
            ->assertJsonPath('group.title', 'PlayStation Gift Cards')
            ->assertJsonPath('products.0.slug', 'psn-us-10')
            ->assertJsonPath('pagination.total', 1);
    }

    public function test_storefront_suggest_is_versioned_under_storefront_boundary(): void
    {
        $this->mock(CanonicalProductSearchSuggestService::class, function ($mock): void {
            $mock->shouldReceive('suggestions')
                ->once()
                ->andReturn([
                    'query' => 'ste',
                    'results' => [['label' => 'Steam', 'value' => 'steam']],
                ]);
        });

        $this->getJson('/api/storefront/v1/catalog/suggest?q=ste')
            ->assertOk()
            ->assertJsonPath('contract.name', 'storefront-suggest')
            ->assertJsonPath('contract.dto_boundary', 'transitions_not_conditions')
            ->assertJsonPath('data.results.0.label', 'Steam');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productCard(array $overrides = []): array
    {
        return [
            'id' => 1,
            'slug' => 'steam-us-10',
            'url' => 'https://meanly.test/catalog/products/steam-us-10',
            'machine_readable_at' => 'https://meanly.test/llms/catalog/products/steam-us-10.json',
            'name' => 'Steam US 10 USD',
            'category' => 'gift_cards',
            'category_label' => 'Gift Cards',
            'brand' => 'Steam',
            'product_family' => 'wallet',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'region' => 'US',
            'status_label' => 'Best offer',
            'selected_offer' => [
                'product_id' => 99,
                'name' => 'Steam US 10 USD',
                'seller' => ['name' => 'Meanly seller'],
                'price' => ['amount' => 10, 'currency' => 'USD'],
                'availability' => 'available_to_order',
            ],
            ...$overrides,
        ];
    }
}
