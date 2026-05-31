<?php

namespace Tests\Feature;

use App\Models\CatalogSearchLog;
use App\Models\DemandGap;
use App\Models\OpportunityCase;
use App\Models\Order\Order;
use App\Services\DemandGapEngineService;
use App\Services\OpportunityLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandGapTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_recalculates_demand_gap_scores_and_lost_gmv(): void
    {
        // 1. Seed storefront search logs with 0 results
        // 8 searches for "Steam Turkey" returning 0 results
        for ($i = 0; $i < 8; $i++) {
            CatalogSearchLog::create([
                'query' => 'Steam Turkey',
                'normalized_query' => 'steam turkey',
                'source' => 'storefront',
                'intent' => 'buy_now',
                'filters' => ['brand' => 'Steam', 'region' => 'turkey'],
                'confidence' => 0.95,
                'results_count' => 0,
            ]);
        }

        // 2 searches for "Steam Turkey" returning 2 results
        for ($i = 0; $i < 2; $i++) {
            CatalogSearchLog::create([
                'query' => 'Steam Turkey',
                'normalized_query' => 'steam turkey',
                'source' => 'storefront',
                'intent' => 'buy_now',
                'filters' => ['brand' => 'Steam', 'region' => 'turkey'],
                'confidence' => 0.95,
                'results_count' => 2,
            ]);
        }

        $this->assertEquals(0, DemandGap::count());

        // 2. Trigger Demand Gap Recalculation
        /** @var DemandGapEngineService $service */
        $service = app(DemandGapEngineService::class);
        $service->recalculateGaps();

        $this->assertEquals(1, DemandGap::count());

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('steam turkey', $gap->canonical_query);
        $this->assertEquals(10, $gap->search_volume);
        $this->assertEquals(8, $gap->zero_results_count);
        $this->assertEquals(0.4, $gap->average_results_count); // (8*0 + 2*2) / 10 = 0.40

        // Gap factor calculation check:
        // gap_factor = (8 + (10 - 8) * (1 / (1 + 0.40))) / 10 = (8 + 2 * 0.714) / 10 = 9.428 / 10 = 0.9428
        // estimated_lost_gmv = 10 * 0.9428 * 1000 = 9428.57
        $this->assertEquals(9428.57, $gap->estimated_lost_gmv);
        $this->assertEquals('medium', $gap->priority_label); // 9428 is between 2000 and 10000
    }

    public function test_it_generates_proactive_purchasing_recommendations(): void
    {
        // 1. Seed search log
        CatalogSearchLog::create([
            'query' => 'Xbox US $50',
            'normalized_query' => 'xbox us 50',
            'source' => 'storefront',
            'intent' => 'buy_now',
            'filters' => ['brand' => 'Xbox', 'region' => 'us', 'face_value' => 50, 'currency' => 'USD'],
            'confidence' => 0.95,
            'results_count' => 0,
        ]);

        // 2. Calculate gaps
        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();

        // 3. Define the recommendation resolver block (matching the resource implementation)
        $resolver = function ($record) {
            if (! $record) {
                return '—';
            }

            $log = \App\Models\CatalogSearchLog::where('normalized_query', $record->canonical_query)
                ->whereNotNull('filters')
                ->orderByDesc('id')
                ->first();

            if ($log && ! empty($log->filters)) {
                $brand = $log->filters['brand'] ?? null;
                $region = $log->filters['region'] ?? null;
                $faceValue = $log->filters['face_value'] ?? null;
                $currency = $log->filters['currency'] ?? null;
                $category = $log->filters['category'] ?? null;

                if ($brand) {
                    $recommendation = "Закупите и добавьте в каталог цифровые карты пополнения **{$brand}**";
                    if ($region) {
                        $recommendation .= " для региона **" . strtoupper($region) . "**";
                    }
                    if ($faceValue) {
                        $recommendation .= " номиналом **" . $faceValue . " " . ($currency ?: 'RUB') . "**";
                    }
                    return new \Illuminate\Support\HtmlString($recommendation . '. Это покроет неудовлетворенный спрос на сумму **' . number_format($record->estimated_lost_gmv, 2, '.', ' ') . ' ₽**.');
                }

                if ($category) {
                    return new \Illuminate\Support\HtmlString("Закупите и подключите новые канонические товары в категории **" . ucwords($category) . "**. Это поможет вернуть упущенную выручку в размере **" . number_format($record->estimated_lost_gmv, 2, '.', ' ') . " ₽**.");
                }
            }

            return new \Illuminate\Support\HtmlString("Закупите канонические товары, релевантные запросу **\"" . $record->canonical_query . "\"**. Это восстановит упущенные продажи на сумму **" . number_format($record->estimated_lost_gmv, 2, '.', ' ') . " ₽**.");
        };

        $htmlResult = $resolver($gap);
        $this->assertNotNull($htmlResult);

        // Assert purchase recommendation matches exact logs filters!
        $this->assertStringContainsString('Xbox', $htmlResult->toHtml());
        $this->assertStringContainsString('US', $htmlResult->toHtml());
        $this->assertStringContainsString('50 USD', $htmlResult->toHtml());
    }

    public function test_it_handles_multi_touch_journey_attributions_and_calculates_opportunity_score(): void
    {
        // 1. Seed three storefront search logs
        $log1 = CatalogSearchLog::create([
            'query' => 'Steam Argentina',
            'normalized_query' => 'steam argentina',
            'source' => 'storefront',
            'results_count' => 0,
        ]);

        $log2 = CatalogSearchLog::create([
            'query' => 'Steam Turkey',
            'normalized_query' => 'steam turkey',
            'source' => 'storefront',
            'results_count' => 0,
        ]);

        // Seed LegalEntity and Shop to satisfy FK constraints
        $entity = \App\Models\LegalEntity::create([
            'name' => 'Test Entity',
            'short_name' => 'TE',
            'inn' => '123456789012',
            'available_balance' => 10000,
            'is_active' => true,
        ]);

        $shop = \App\Models\Shop::create([
            'name' => 'Test Shop',
            'domain' => 'testshop.test',
            'voucher_prefix' => 'TEST',
            'is_active' => true,
            'legal_entity_id' => $entity->id,
        ]);

        // 2. Mock a complete Order and create fractional attributions (representing a 3-touch journey)
        $order = Order::create([
            'order_id' => 'MS-TESTATTRIB',
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'COMPLETED',
            'sub_status' => 'DIRECT_STOREFRONT',
            'shop_id' => $shop->id,
            'total_amount' => 3000.00,
            'currency' => 'RUB',
            'sales_channel' => 'meanly_storefront',
        ]);


        // Attribution weight = 1/2 = 0.5, GMV = 1500
        \App\Models\Order\OrderSearchAttribution::create([
            'order_id' => $order->id,
            'search_log_id' => $log1->id,
            'touch_type' => 'first',
            'attribution_weight' => 0.5,
            'attributed_gmv' => 1500.00,
        ]);

        \App\Models\Order\OrderSearchAttribution::create([
            'order_id' => $order->id,
            'search_log_id' => $log2->id,
            'touch_type' => 'last',
            'attribution_weight' => 0.5,
            'attributed_gmv' => 1500.00,
        ]);

        // 3. Trigger recalculation
        app(DemandGapEngineService::class)->recalculateGaps();

        // 4. Verify fractional calculations and opportunity score mapping
        $gap1 = DemandGap::where('canonical_query', 'steam argentina')->firstOrFail();
        $this->assertEquals(1, $gap1->search_volume);
        $this->assertEquals(0.5, $gap1->attributed_orders_count);
        $this->assertEquals(1500.00, $gap1->attributed_gmv);

        // Opportunity Score v2 calculation check:
        // searches = 1 => popularity weight = min(25, (1/120)*25) = 0.2083
        // deficit weight = 1.0 * 35 = 35
        // lost GMV weight = min(20, (1000/50000)*20) = 0.4
        // Opportunity Score = 0.2 + 35 + 0.4 = 35.6
        $this->assertEquals(35.6, $gap1->opportunity_score);
    }

    public function test_it_tracks_session_journey_and_attributes_fractionally_on_checkout(): void
    {
        // 1. Seed LegalEntity and Shop
        $entity = \App\Models\LegalEntity::create([
            'name' => 'Meanly B2B Corp',
            'short_name' => 'B2B',
            'inn' => '770000000222',
            'available_balance' => 50000,
            'is_active' => true,
        ]);

        $shop = new \App\Models\Shop([
            'name' => 'Storefront Outlet',
            'domain' => 'storefront.test',
            'voucher_prefix' => 'STOUT',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();


        // Seed Warehouse
        \App\Models\Warehouse::create([
            'shop_id' => $shop->id,
            'name' => 'Main Warehouse',
            'is_main' => true,
        ]);

        // Seed Product
        $product = \App\Models\Product::create([
            'shop_id' => $shop->id,
            'sku' => 'STOUT-PROD-999',
            'name' => 'Awesome Premium Product',
            'price_rub' => 200000, // 2000 RUB retail
            'type' => 'giftcard',
            'is_active' => true,
        ]);
        $product->load('shop');

        // Expose the product on the storefront channel
        app(\App\Services\MeanlyFirstPartyStorefrontService::class)->exposeProduct($product, $shop);



        // 2. Perform two storefront searches inside session
        $service = app(\App\Services\CatalogSearchLogService::class);
        
        $log1 = $service->log('Steam Card Argentina', 'storefront', 0);
        $log2 = $service->log('Steam Wallet Argentina', 'storefront', 0);

        $this->assertNotNull($log1);
        $this->assertNotNull($log2);

        // Verify session holds both search log IDs
        $this->assertEquals([$log1->id, $log2->id], session()->get('search_journey_log_ids'));
        $this->assertEquals($log2->id, session()->get('last_search_log_id'));

        // 3. Trigger checkout
        $customer = [
            'name' => 'Alexander Pushkin',
            'email' => 'pushkin@poetry.ru',
            'phone' => '+79998887766',
        ];
        $payment = [
            'method' => 'meanly_storefront_pending',
            'status' => 'pending',
        ];

        /** @var \App\Services\MeanlyRetailCheckoutService $checkoutService */
        $checkoutService = app(\App\Services\MeanlyRetailCheckoutService::class);
        $result = $checkoutService->checkout($product, $customer, 1, $payment);

        $this->assertNotNull($result['order']);
        $orderId = $result['order']->id;

        // 4. Assert session is cleared
        $this->assertFalse(session()->has('search_journey_log_ids'));
        $this->assertFalse(session()->has('last_search_log_id'));

        // 5. Assert OrderSearchAttribution rows are created fractionally
        $attributions = \App\Models\Order\OrderSearchAttribution::where('order_id', $orderId)->get();
        $this->assertCount(2, $attributions);

        $firstTouch = $attributions->where('touch_type', 'first')->first();
        $lastTouch = $attributions->where('touch_type', 'last')->first();

        $this->assertNotNull($firstTouch);
        $this->assertNotNull($lastTouch);

        $this->assertEquals(0.5, $firstTouch->attribution_weight);
        $this->assertEquals(1000.00, $firstTouch->attributed_gmv); // 2000 * 0.5 = 1000.00

        $this->assertEquals(0.5, $lastTouch->attribution_weight);
        $this->assertEquals(1000.00, $lastTouch->attributed_gmv); // 2000 * 0.5 = 1000.00
    }

    public function test_it_logs_storefront_views_and_carts_funnel_clicks(): void
    {
        // 1. Seed LegalEntity and Shop
        $entity = \App\Models\LegalEntity::create([
            'name' => 'Meanly B2B Corp',
            'short_name' => 'B2B',
            'inn' => '770000000222',
            'available_balance' => 50000,
            'is_active' => true,
        ]);

        $shop = new \App\Models\Shop([
            'name' => 'Storefront Outlet',
            'domain' => 'storefront.test',
            'voucher_prefix' => 'STOUT',
            'is_active' => true,
        ]);
        $shop->legal_entity_id = $entity->id;
        $shop->save();

        // Seed Warehouse
        \App\Models\Warehouse::create([
            'shop_id' => $shop->id,
            'name' => 'Main Warehouse',
            'is_main' => true,
        ]);

        // Seed Product
        $product = \App\Models\Product::create([
            'shop_id' => $shop->id,
            'sku' => 'STOUT-PROD-888',
            'name' => 'Funnel Telemetry Test Product',
            'price_rub' => 200000, // 2000 RUB retail
            'type' => 'giftcard',
            'is_active' => true,
            'slug' => 'funnel-telemetry-test-product',
        ]);
        $product->load('shop');

        // Expose the product on the storefront channel
        app(\App\Services\MeanlyFirstPartyStorefrontService::class)->exposeProduct($product, $shop);

        // 2. Simulate Search to initialize search session (this populates 'last_search_log_id' and 'search_journey_log_ids')
        $service = app(\App\Services\CatalogSearchLogService::class);
        $log = $service->log('Steam Argentina', 'storefront', 1);

        $this->assertNotNull($log);
        $this->assertEquals(0, $log->views_count);
        $this->assertEquals(0, $log->carts_count);

        // Assert session has the last search log ID
        $this->assertEquals($log->id, session()->get('last_search_log_id'));

        // 3. Visit the product page via the HTTP storefront controller to simulate 'Product View'
        $response = $this->get(route('meanly.storefront.products.show', ['slug' => $product->slug]));
        $response->assertStatus(200);

        // Verify views_count is incremented
        $log->refresh();
        $this->assertEquals(1, $log->views_count);
        $this->assertEquals(0, $log->carts_count);

        // 4. Hit the checkout availability endpoint to simulate 'Add to Cart / Checkout Started' action
        $response = $this->postJson(route('meanly.storefront.checkout.availability'), [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $response->assertStatus(200);

        // Verify carts_count is incremented
        $log->refresh();
        $this->assertEquals(1, $log->views_count);
        $this->assertEquals(1, $log->carts_count);
    }

    public function test_it_calculates_opportunity_score_v2_with_dropoff_rules(): void
    {
        // 1. Seed storefront search logs with views and carts
        for ($i = 0; $i < 10; $i++) {
            CatalogSearchLog::create([
                'query' => 'Xbox Argentina',
                'normalized_query' => 'xbox argentina',
                'source' => 'storefront',
                'intent' => 'buy_now',
                'results_count' => 0,
                'views_count' => 1,
                'carts_count' => ($i < 8) ? 1 : 0, // 8 carts in total
            ]);
        }

        $this->assertEquals(0, DemandGap::count());

        // 2. Trigger Recalculation
        /** @var DemandGapEngineService $service */
        $service = app(DemandGapEngineService::class);
        $service->recalculateGaps();

        $this->assertEquals(1, DemandGap::count());

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('xbox argentina', $gap->canonical_query);
        $this->assertEquals(10, $gap->search_volume);
        $this->assertEquals(10, $gap->views_count);
        $this->assertEquals(8, $gap->carts_count);
        $this->assertEquals(0, $gap->attributed_orders_count);

        // Verification of Opportunity Score v2 components:
        // popularity_weight = min(25, (10 / 120) * 25) = 2.0833
        // deficit_weight = 1.0 * 35 = 35
        // funnel_dropoff_weight = min(20, ((8 - 0) / 8) * 20) = 20
        // lost_gmv_weight = min(20, (10000 / 50000) * 20) = 4.0
        // Total Expected Opportunity Score = 2.0833 + 35 + 20 + 4 = 61.1 (rounded to 1 decimal place)
        $this->assertEquals(61.1, $gap->opportunity_score);
    }

    public function test_it_diagnoses_checkout_dropoff_correctly(): void
    {
        // checkout_dropoff: carts_count > 0 and (carts - orders)/carts >= 0.6
        for ($i = 0; $i < 10; $i++) {
            CatalogSearchLog::create([
                'query' => 'Steam Turkey Gift',
                'normalized_query' => 'steam turkey gift',
                'source' => 'storefront',
                'results_count' => 5,
                'views_count' => 1,
                'carts_count' => ($i < 8) ? 1 : 0, // 8 carts in total
            ]);
        }

        $entity = \App\Models\LegalEntity::create([
            'name' => 'B2B Co',
            'short_name' => 'B2B',
            'inn' => '770000000333',
            'available_balance' => 50000,
            'is_active' => true,
        ]);

        $shop = \App\Models\Shop::create([
            'name' => 'Outlet',
            'domain' => 'outlet.test',
            'voucher_prefix' => 'OUT',
            'is_active' => true,
            'legal_entity_id' => $entity->id,
        ]);

        $order = Order::create([
            'order_id' => 'MS-OUT-CHECKOUT-DROPOFF',
            'uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'status' => 'COMPLETED',
            'shop_id' => $shop->id,
            'total_amount' => 1000.00,
            'currency' => 'RUB',
            'sales_channel' => 'meanly_storefront',
        ]);

        \App\Models\Order\OrderSearchAttribution::create([
            'order_id' => $order->id,
            'search_log_id' => CatalogSearchLog::firstOrFail()->id,
            'touch_type' => 'single',
            'attribution_weight' => 1.0,
            'attributed_gmv' => 1000.00,
        ]);

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('checkout_dropoff', $gap->opportunity_diagnosis);
        $this->assertEquals(87.5, $gap->diagnosis_confidence);
    }

    public function test_it_diagnoses_catalog_gap_correctly(): void
    {
        // catalog_gap: zero_results/search_volume >= 0.4
        for ($i = 0; $i < 6; $i++) {
            CatalogSearchLog::create([
                'query' => 'Unknown Card US',
                'normalized_query' => 'unknown card us',
                'source' => 'storefront',
                'results_count' => 0,
            ]);
        }
        for ($i = 0; $i < 4; $i++) {
            CatalogSearchLog::create([
                'query' => 'Unknown Card US',
                'normalized_query' => 'unknown card us',
                'source' => 'storefront',
                'results_count' => 2,
            ]);
        }

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('catalog_gap', $gap->opportunity_diagnosis);
        $this->assertEquals(60.0, $gap->diagnosis_confidence);
    }

    public function test_it_diagnoses_pricing_issue_correctly(): void
    {
        // pricing_issue: views > 0 and carts / views < 0.3, and checkout dropoff is NOT met
        for ($i = 0; $i < 20; $i++) {
            CatalogSearchLog::create([
                'query' => 'Expensive Steam Card',
                'normalized_query' => 'expensive steam card',
                'source' => 'storefront',
                'results_count' => 5,
                'views_count' => 1,
                'carts_count' => ($i < 5) ? 1 : 0, // 5 carts in total
            ]);
        }

        $entity = \App\Models\LegalEntity::create([
            'name' => 'B2B Co Price',
            'short_name' => 'B2BPrice',
            'inn' => '770000000303',
            'available_balance' => 50000,
            'is_active' => true,
        ]);

        $shop = \App\Models\Shop::create([
            'name' => 'Outlet Price',
            'domain' => 'outletprice.test',
            'voucher_prefix' => 'OUTP',
            'is_active' => true,
            'legal_entity_id' => $entity->id,
        ]);

        // Create 3 orders to make checkout dropoff = (5 - 3)/5 = 0.4 < 0.6
        for ($i = 0; $i < 3; $i++) {
            $order = Order::create([
                'order_id' => 'MS-OUT-PRICE-' . $i,
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'COMPLETED',
                'shop_id' => $shop->id,
                'total_amount' => 1000.00,
                'currency' => 'RUB',
                'sales_channel' => 'meanly_storefront',
            ]);

            \App\Models\Order\OrderSearchAttribution::create([
                'order_id' => $order->id,
                'search_log_id' => CatalogSearchLog::firstOrFail()->id,
                'touch_type' => 'single',
                'attribution_weight' => 1.0,
                'attributed_gmv' => 1000.00,
            ]);
        }

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('pricing_issue', $gap->opportunity_diagnosis);
        $this->assertEquals(75.0, $gap->diagnosis_confidence);
    }

    public function test_it_diagnoses_healthy_state_correctly(): void
    {
        // healthy: conversion >= 10%, no critical dropoffs
        for ($i = 0; $i < 10; $i++) {
            CatalogSearchLog::create([
                'query' => 'Super Popular Card',
                'normalized_query' => 'super popular card',
                'source' => 'storefront',
                'results_count' => 5,
                'views_count' => 1,
                'carts_count' => ($i < 8) ? 1 : 0, // 8 carts in total
            ]);
        }

        $entity = \App\Models\LegalEntity::create([
            'name' => 'B2B Co 2',
            'short_name' => 'B2B2',
            'inn' => '770000000444',
            'available_balance' => 50000,
            'is_active' => true,
        ]);

        $shop = \App\Models\Shop::create([
            'name' => 'Outlet 2',
            'domain' => 'outlet2.test',
            'voucher_prefix' => 'OUT2',
            'is_active' => true,
            'legal_entity_id' => $entity->id,
        ]);

        // Create 6 orders so conversion is 6/10 = 60%, dropoff is (8-6)/8 = 0.25 < 0.6
        for ($i = 0; $i < 6; $i++) {
            $order = Order::create([
                'order_id' => 'MS-OUT-HEALTHY-' . $i,
                'uuid' => \Illuminate\Support\Str::uuid()->toString(),
                'status' => 'COMPLETED',
                'shop_id' => $shop->id,
                'total_amount' => 1000.00,
                'currency' => 'RUB',
                'sales_channel' => 'meanly_storefront',
            ]);

            \App\Models\Order\OrderSearchAttribution::create([
                'order_id' => $order->id,
                'search_log_id' => CatalogSearchLog::firstOrFail()->id,
                'touch_type' => 'single',
                'attribution_weight' => 1.0,
                'attributed_gmv' => 1000.00,
            ]);
        }

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();
        $this->assertEquals('healthy', $gap->opportunity_diagnosis);
        $this->assertEquals(60.0, $gap->diagnosis_confidence);
    }

    public function test_it_builds_multi_cause_diagnosis_graph(): void
    {
        // Conflicting signals scenario:
        // Searches: 100
        // Views: 20
        // Carts: 2
        // Orders: 0
        // Zero Results: 45
        for ($i = 0; $i < 100; $i++) {
            CatalogSearchLog::create([
                'query' => 'Conflicting Query',
                'normalized_query' => 'conflicting query',
                'source' => 'storefront',
                'results_count' => ($i < 45) ? 0 : 2,
                'views_count' => ($i < 20) ? 1 : 0,
                'carts_count' => ($i < 2) ? 1 : 0,
            ]);
        }

        app(DemandGapEngineService::class)->recalculateGaps();

        $gap = DemandGap::firstOrFail();

        // 1. Verify backward compatibility columns (Primary Cause matches checkout_dropoff at 99.0%)
        $this->assertEquals('checkout_dropoff', $gap->opportunity_diagnosis);
        $this->assertEquals(99.0, $gap->diagnosis_confidence);

        // 2. Verify complete ranked Multi-Cause Diagnosis Graph
        $graph = $gap->opportunity_diagnosis_graph;
        $this->assertCount(3, $graph);

        $this->assertEquals('checkout_dropoff', $graph[0]['cause']);
        $this->assertEquals(99.0, $graph[0]['score']);

        $this->assertEquals('pricing_issue', $graph[1]['cause']);
        $this->assertEquals(90.0, $graph[1]['score']);

        $this->assertEquals('catalog_gap', $graph[2]['cause']);
        $this->assertEquals(50.0, $graph[2]['score']);
    }

    public function test_it_manages_opportunity_case_lifecycle_and_effectiveness(): void
    {
        $gap = DemandGap::create([
            'canonical_query' => 'steam argentina',
            'search_volume' => 100,
            'views_count' => 3,
            'carts_count' => 0,
            'zero_results_count' => 80,
            'average_results_count' => 0.2,
            'attributed_orders_count' => 0,
            'attributed_gmv' => 0,
            'estimated_lost_gmv' => 50000,
            'opportunity_score' => 88.0,
            'opportunity_diagnosis' => 'catalog_gap',
            'diagnosis_confidence' => 95.0,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'catalog_gap', 'score' => 95.0],
                ['cause' => 'pricing_issue', 'score' => 45.0],
            ],
            'demand_gap_score' => 50000,
            'priority_label' => 'critical',
            'last_searched_at' => now(),
        ]);

        /** @var OpportunityLifecycleService $service */
        $service = app(OpportunityLifecycleService::class);
        $case = $service->openCase($gap);

        $this->assertEquals(OpportunityCase::STATUS_OPEN, $case->status);
        $this->assertEquals('steam argentina', $case->canonical_query);
        $this->assertEquals(88.0, $case->before_opportunity_score);
        $this->assertEquals('catalog_gap', $case->before_diagnosis);
        $this->assertCount(2, $case->before_diagnosis_graph);

        $sameCase = $service->openCase($gap);
        $this->assertEquals($case->id, $sameCase->id);

        $case = $service->recordAction($case, OpportunityCase::ACTION_ADD_SUPPLY, 'Added 12 Steam Argentina products.');
        $this->assertEquals(OpportunityCase::STATUS_IN_PROGRESS, $case->status);
        $this->assertEquals(OpportunityCase::ACTION_ADD_SUPPLY, $case->action_type);

        $gap->update([
            'search_volume' => 140,
            'views_count' => 118,
            'carts_count' => 48,
            'attributed_orders_count' => 10,
            'attributed_gmv' => 18000,
            'estimated_lost_gmv' => 7000,
            'opportunity_score' => 34.0,
            'opportunity_diagnosis' => 'healthy',
            'diagnosis_confidence' => 71.0,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'healthy', 'score' => 71.0],
            ],
        ]);

        $case = $service->resolveCase($case, recalculate: false);

        $this->assertEquals(OpportunityCase::STATUS_RESOLVED, $case->status);
        $this->assertEquals(34.0, $case->after_opportunity_score);
        $this->assertEquals(18000.0, $case->after_gmv);
        $this->assertEquals('healthy', $case->after_diagnosis);
        $this->assertEquals(100.0, $case->gmv_growth_percentage);
        $this->assertEquals(100.0, $case->conversion_growth_percentage);

        $summary = $service->actionEffectiveness(OpportunityCase::ACTION_ADD_SUPPLY);
        $this->assertEquals(1, $summary['cases_count']);
        $this->assertEquals(100.0, $summary['success_rate']);
        $this->assertEquals(54.0, $summary['avg_score_delta']);
        $this->assertEquals(100.0, $summary['avg_gmv_growth_percentage']);
    }

    public function test_it_auto_opens_high_score_opportunity_cases_with_owner_and_sla(): void
    {
        $highGap = DemandGap::create([
            'canonical_query' => 'steam turkey',
            'brand_entity_key' => 'steam',
            'region_entity_key' => 'turkey',
            'category_entity_key' => 'gift-cards',
            'search_volume' => 120,
            'views_count' => 5,
            'carts_count' => 0,
            'zero_results_count' => 100,
            'average_results_count' => 0.1,
            'attributed_orders_count' => 0,
            'attributed_gmv' => 0,
            'estimated_lost_gmv' => 90000,
            'opportunity_score' => 92.0,
            'opportunity_diagnosis' => 'catalog_gap',
            'diagnosis_confidence' => 95.0,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'catalog_gap', 'score' => 95.0],
            ],
            'demand_gap_score' => 90000,
            'priority_label' => 'critical',
            'last_searched_at' => now(),
        ]);

        DemandGap::create([
            'canonical_query' => 'xbox colombia',
            'search_volume' => 20,
            'zero_results_count' => 10,
            'average_results_count' => 1.0,
            'estimated_lost_gmv' => 8000,
            'opportunity_score' => 42.0,
            'opportunity_diagnosis' => 'pricing_issue',
            'diagnosis_confidence' => 70.0,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'pricing_issue', 'score' => 70.0],
            ],
            'demand_gap_score' => 8000,
            'priority_label' => 'medium',
            'last_searched_at' => now(),
        ]);

        /** @var OpportunityLifecycleService $service */
        $service = app(OpportunityLifecycleService::class);
        $cases = $service->autoOpenCases(80.0);

        $this->assertCount(1, $cases);

        $case = $cases->first();
        $this->assertEquals($highGap->canonical_query, $case->canonical_query);
        $this->assertEquals(OpportunityCase::STATUS_OPEN, $case->status);
        $this->assertEquals(OpportunityCase::TEAM_CONTENT, $case->owner_team);
        $this->assertTrue($case->auto_created);
        $this->assertStringContainsString('above threshold 80.0', $case->auto_reason);
        $this->assertNotNull($case->sla_due_at);
        $this->assertTrue($case->sla_due_at->between(now()->addHours(47), now()->addHours(49)));

        $secondRun = $service->autoOpenCases(80.0);
        $this->assertCount(0, $secondRun);
        $this->assertEquals(1, OpportunityCase::count());
    }

    public function test_it_assigns_payments_team_and_24_hour_sla_for_checkout_dropoff(): void
    {
        $gap = DemandGap::create([
            'canonical_query' => 'psn turkey',
            'search_volume' => 100,
            'views_count' => 90,
            'carts_count' => 80,
            'zero_results_count' => 0,
            'average_results_count' => 5.0,
            'attributed_orders_count' => 1,
            'attributed_gmv' => 1000,
            'estimated_lost_gmv' => 20000,
            'opportunity_score' => 86.0,
            'opportunity_diagnosis' => 'checkout_dropoff',
            'diagnosis_confidence' => 98.8,
            'opportunity_diagnosis_graph' => [
                ['cause' => 'checkout_dropoff', 'score' => 98.8],
            ],
            'demand_gap_score' => 20000,
            'priority_label' => 'high',
            'last_searched_at' => now(),
        ]);

        $case = app(OpportunityLifecycleService::class)->openCase($gap, autoCreated: true);

        $this->assertEquals(OpportunityCase::TEAM_PAYMENTS, $case->owner_team);
        $this->assertTrue($case->sla_due_at->between(now()->addHours(23), now()->addHours(25)));
    }
}


