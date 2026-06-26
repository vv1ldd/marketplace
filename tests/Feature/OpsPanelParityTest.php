<?php

namespace Tests\Feature;

use App\Jobs\SyncProviderCatalogJob;
use App\Models\LegalEntity;
use App\Models\Currency;
use App\Models\DemandGap;
use App\Models\ExternalSearchQuerySignal;
use App\Models\IntentLiquidityCorridor;
use App\Models\IntentLiquidityNode;
use App\Models\LiquidityCorridor;
use App\Models\LiquidityMethod;
use App\Models\MeanlyOperationalAlert;
use App\Models\OpportunityCase;
use App\Models\Product;
use App\Models\ProductInventory;
use App\Models\ProductSalesChannel;
use App\Models\Provider;
use App\Models\SearchDemandRecommendation;
use App\Models\Shop;
use App\Models\SovereignBalanceRequest;
use App\Models\SovereignLedger;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Provider\EzpinDriver;
use App\Services\Provider\FazerDriver;
use App\Services\Provider\ProviderHub;
use App\Models\ZeroLayerIntegration;
use App\Models\ZeroLayerSignal;
use App\Models\WildflowCreditReservation;
use App\Models\WildflowKernelOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OpsPanelParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
        Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => User::ROLE_LEDGER_AUDITOR, 'guard_name' => 'web']);
    }

    public function test_ops_dashboard_merges_ledger_tribunal_into_audit_ai_tab(): void
    {
        $admin = $this->opsAdmin();

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=tribunal')
            ->assertRedirect('/ops?tab=tribunal');
    }

    public function test_tribunal_chain_validation_is_available_under_ops(): void
    {
        $admin = $this->opsAdmin('b');

        SovereignLedger::create([
            'event_type' => 'test.event',
            'amount_base' => 100,
            'currency' => 'RUB',
            'payload' => ['test' => true],
            'previous_fingerprint' => null,
            'fingerprint' => hash('sha256', 'genesis'),
        ]);

        $this->actingAs($admin)
            ->postJson('https://meanly.test/ops/dashboard/tribunal/validate-chain')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('valid_count', 1);
    }

    public function test_ops_dashboard_exposes_provider_kernel_controls(): void
    {
        $admin = $this->opsAdmin('d');

        Provider::updateOrCreate(
            ['type' => 'ezpin'],
            [
                'name' => 'EZPin',
                'is_active' => true,
                'credentials' => [
                    'client_id' => 'configured',
                    'secret_key' => 'configured',
                    'terminal_id' => '123456',
                    'terminal_pin' => '1234',
                ],
            ],
        );
        Provider::updateOrCreate(
            ['type' => 'fazer'],
            [
                'name' => 'Fazer Cards',
                'is_active' => true,
                'credentials' => [
                    'api_key' => 'configured',
                ],
            ],
        );
        Provider::updateOrCreate(
            ['type' => 'playstation'],
            [
                'name' => 'PlayStation Store (TR)',
                'is_active' => true,
            ],
        );

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=providers')
            ->assertRedirect('/ops?tab=providers');

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/providers/data')
            ->assertOk();

        $provider = collect($response->json('data'))->firstWhere('type', 'ezpin');
        $fazer = collect($response->json('data'))->firstWhere('type', 'fazer');
        $source = collect($response->json('catalog_sources'))->firstWhere('type', 'playstation');
        $this->assertNotNull($provider);
        $this->assertNotNull($fazer);
        $this->assertNotNull($source);
        $this->assertSame('meanly.one', data_get($provider, 'authority'));
        $this->assertSame('ezpin', data_get($provider, 'upstream_provider'));
        $this->assertSame('Fazer Cards', data_get($fazer, 'name'));
        $this->assertSame('fazer', data_get($fazer, 'upstream_provider'));
        $this->assertSame('Fazer Cards', data_get($fazer, 'upstream_label'));
        $this->assertSame('parsed_catalog_source', data_get($source, 'source_kind'));
        $this->assertFalse(data_get($provider, 'is_legacy_alias'));
        $this->assertSame('direct_supply_authority', data_get($response->json('kernel'), 'mode'));
        $this->assertSame('meanly.one', data_get($response->json('kernel'), 'authority'));
        $this->assertSame('ezpin+fazercards', data_get($response->json('kernel'), 'upstream'));
        $this->assertSame('EZPin + Fazer Cards', data_get($response->json('kernel'), 'upstream_label'));
        $this->assertTrue(data_get($provider, 'credentials.client_id'));
        $this->assertTrue(data_get($provider, 'credentials.secret_key'));
        $this->assertTrue(data_get($provider, 'terminal.id_configured'));
        $this->assertTrue(data_get($provider, 'terminal.pin_configured'));
        $this->assertTrue(data_get($provider, 'health.credentials_ready'));
        $this->assertSame('/api/v1/providers/{provider}/order', data_get($response->json('kernel'), 'support_planes.docs.orders'));
        $this->assertIsInt(data_get($response->json('kernel'), 'support_planes.devices.terminals_total'));
    }

    public function test_ops_providers_kernel_shows_remote_consumer_when_ru_pulls_from_one(): void
    {
        config([
            'services.wildflow.kernel_url' => 'https://api.meanly.one/api/v1',
            'services.wildflow.kernel_mode' => 'http',
        ]);

        $admin = $this->opsAdmin('ru-supply');

        Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow',
                'is_active' => true,
                'credentials' => [
                    'api_key' => 'ru-partner-token',
                    'client_id' => 'ru-legal-entity',
                    'financial_secret' => 'ru-financial-secret',
                ],
            ],
        );

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/providers/data')
            ->assertOk();

        $this->assertSame('remote_kernel_consumer', data_get($response->json('kernel'), 'mode'));
        $this->assertSame('api.meanly.one', data_get($response->json('kernel'), 'authority'));
        $this->assertSame('meanly.one', data_get($response->json('kernel'), 'upstream'));
        $this->assertCount(1, collect($response->json('data')));
        $this->assertNull(collect($response->json('data'))->firstWhere('sync_status', 'not_configured'));

        $wildflow = collect($response->json('data'))->first();
        $this->assertSame('ezpin', data_get($wildflow, 'type'));
        $this->assertTrue(data_get($wildflow, 'is_legacy_alias'));
        $this->assertFalse(data_get($wildflow, 'health.supports_upstream_pull'));
    }

    public function test_ops_provider_sync_rejects_direct_upstream_pull_on_remote_consumer_contour(): void
    {
        config([
            'services.wildflow.kernel_url' => 'https://api.meanly.one/api/v1',
            'services.wildflow.kernel_mode' => 'http',
        ]);

        Queue::fake();

        $admin = $this->opsAdmin('ru-pull-block');
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            ['name' => 'Wildflow', 'is_active' => true],
        );

        $this->actingAs($admin)
            ->postJson("https://meanly.test/ops/dashboard/providers/{$provider->id}/sync", [
                'mode' => 'pull-upstream',
            ])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_legacy_wildflow_provider_type_resolves_to_ezpin_driver_alias(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Legacy Wildflow Alias',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin'],
            ],
        );

        $driver = app(ProviderHub::class)->forProvider($provider);

        $this->assertInstanceOf(EzpinDriver::class, $driver);
    }

    public function test_fazer_provider_type_resolves_to_fazer_driver(): void
    {
        $provider = Provider::updateOrCreate(
            ['type' => 'fazer'],
            [
                'name' => 'Fazer Cards',
                'is_active' => true,
            ],
        );

        $driver = app(ProviderHub::class)->forProvider($provider);

        $this->assertInstanceOf(FazerDriver::class, $driver);
    }

    public function test_ops_provider_registry_shows_missing_fazer_supplier_as_not_configured(): void
    {
        $admin = $this->opsAdmin('g');
        Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Legacy Wildflow Alias',
                'is_active' => true,
                'settings' => ['upstream_provider' => 'ezpin'],
            ],
        );

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/providers/data')
            ->assertOk();

        $ezpin = collect($response->json('data'))->firstWhere('type', 'ezpin');
        $fazer = collect($response->json('data'))->firstWhere('type', 'fazer');

        $this->assertNotNull($ezpin);
        $this->assertNotNull($fazer);
        $this->assertSame('EZPin', data_get($ezpin, 'name'));
        $this->assertSame('Fazer Cards', data_get($fazer, 'name'));
        $this->assertSame('not_configured', data_get($fazer, 'sync_status'));
        $this->assertFalse(data_get($fazer, 'health.supports_upstream_pull'));
        $this->assertNull(data_get($fazer, 'sync_url'));
    }

    public function test_ops_provider_sync_is_queued_instead_of_running_in_request(): void
    {
        Queue::fake();

        $admin = $this->opsAdmin('h');
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Legacy Wildflow Alias',
                'is_active' => true,
                'sync_status' => 'idle',
            ],
        );

        $this->actingAs($admin)
            ->postJson("https://meanly.test/ops/dashboard/providers/{$provider->id}/sync", [
                'mode' => 'pull-upstream',
            ])
            ->assertStatus(202)
            ->assertJsonPath('queued', true)
            ->assertJsonPath('provider.sync_status', 'syncing');

        Queue::assertPushed(SyncProviderCatalogJob::class, function (SyncProviderCatalogJob $job) use ($provider): bool {
            return $job->providerId === $provider->id
                && $job->pullUpstream === true
                && $job->embedded === false;
        });
    }

    public function test_ops_provider_sync_status_is_reset_when_queue_job_is_missing(): void
    {
        $admin = $this->opsAdmin('h2');
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Legacy Wildflow Alias',
                'is_active' => true,
                'sync_status' => 'syncing',
            ],
        );

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/providers/data')
            ->assertOk()
            ->assertJsonPath('data.0.sync_status', 'idle')
            ->assertJsonPath('data.0.sync_active', false);

        $this->assertSame('idle', $provider->fresh()->sync_status);
    }

    public function test_ops_provider_sync_can_be_requeued_after_stale_syncing_state(): void
    {
        Queue::fake();

        $admin = $this->opsAdmin('h3');
        $provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Legacy Wildflow Alias',
                'is_active' => true,
                'sync_status' => 'syncing',
            ],
        );

        $this->actingAs($admin)
            ->postJson("https://meanly.test/ops/dashboard/providers/{$provider->id}/sync", [
                'mode' => 'embedded',
            ])
            ->assertStatus(202)
            ->assertJsonPath('provider.sync_status', 'syncing');

        Queue::assertPushed(SyncProviderCatalogJob::class);
    }

    public function test_ops_organizations_expose_partner_api_identity_and_settlement_controls(): void
    {
        $admin = $this->opsAdmin('f');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Unified API Partner',
            'inn' => '770000004444',
            'available_balance' => 500,
            'reserved_balance' => 75,
            'currency' => 'USD',
            'status' => 'active',
            'is_active' => true,
            'meanly_api_token' => 'meanly_test_token_1234567890',
            'meanly_financial_secret' => 'meanly_fin_secret_1234567890',
            'meanly_ip_whitelist' => ['203.0.113.10'],
            'agreement_metadata' => ['kernel_external_id' => 'partner-api-42'],
        ]));

        WildflowCreditReservation::create([
            'legal_entity_id' => $entity->id,
            'amount' => 25,
            'reference' => 'OPS-RESERVE-1',
            'status' => 'active',
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/partners/data')
            ->assertOk();

        $partner = collect($response->json('data'))->firstWhere('id', $entity->id);
        $this->assertNotNull($partner);
        $this->assertTrue(data_get($partner, 'api_identity.token_configured'));
        $this->assertTrue(data_get($partner, 'api_identity.financial_secret_configured'));
        $this->assertSame('partner-api-42', data_get($partner, 'api_identity.kernel_external_id'));
        $this->assertSame(1, data_get($partner, 'api_identity.ip_whitelist_count'));
        $this->assertSame(1, data_get($partner, 'settlement.active_reservations_count'));
        $this->assertEquals(25.0, data_get($partner, 'settlement.active_reservations_amount'));
        $this->assertNull(data_get($partner, 'action_urls.grant_credit'));
        $this->assertNotEmpty(data_get($partner, 'action_urls.top_up'));
    }

    public function test_ops_organizations_can_top_up_partner_balance(): void
    {
        $admin = $this->opsAdmin('e');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Ops Finance Partner',
            'inn' => '770000003333',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]));

        $this->actingAs($admin)
            ->postJson("https://meanly.test/ops/dashboard/partners/{$entity->id}/top-up", [
                'amount' => 10,
                'reference' => 'OPS-TOPUP-1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('partner.available_balance', 110);

        $this->assertTrue(SovereignLedger::query()
            ->where('legal_entity_id', $entity->id)
            ->where('event_type', 'FINANCE_CREDITED')
            ->exists());
    }

    public function test_ops_operation_history_unifies_kernel_and_ledger_events(): void
    {
        $admin = $this->opsAdmin('g');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'History Partner',
            'inn' => '770000005555',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]));

        WildflowKernelOrder::create([
            'legal_entity_id' => $entity->id,
            'provider' => 'ezpin',
            'marketplace_reference' => 'MP-HISTORY-1',
            'proxy_reference' => 'WF-HISTORY-1',
            'vendor_reference' => null,
            'service_sku' => 'SKU-HISTORY',
            'price' => 10,
            'currency' => 'USD',
            'status' => 'failed',
            'error_message' => 'Upstream denied',
        ]);

        SovereignLedger::create([
            'legal_entity_id' => $entity->id,
            'event_type' => 'OPS_HISTORY_LEDGER_EVENT',
            'amount_base' => 10,
            'currency' => 'USD',
            'payload' => ['provider' => 'ezpin', 'sku' => 'SKU-HISTORY', 'status' => 'recorded'],
            'previous_fingerprint' => null,
            'fingerprint' => hash('sha256', 'history'),
            'created_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/operations/data?search=SKU-HISTORY')
            ->assertOk();

        $events = collect($response->json('data'));
        $this->assertNotNull($events->firstWhere('source', 'kernel_order'));
        $this->assertNotNull($events->firstWhere('source', 'ledger'));
        $this->assertSame('Upstream denied', data_get($events->firstWhere('source', 'kernel_order'), 'failure_reason'));
        $this->assertSame('History Partner', data_get($events->firstWhere('source', 'ledger'), 'partner'));
    }

    public function test_ops_recovers_treasury_liquidity_and_channels_panels(): void
    {
        $admin = $this->opsAdmin('h');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Treasury Partner',
            'inn' => '770000006666',
            'available_balance' => 1000,
            'reserved_balance' => 150,
            'native_token_balance' => 20,
            'native_token_reserved' => 3,
            'currency' => 'RUB',
            'is_active' => true,
        ]));
        $shop = Shop::create([
            'name' => 'Treasury Shop',
            'legal_entity_id' => $entity->id,
            'is_active' => true,
            'business_id' => 123,
            'campaign_id' => 456,
            'ym_warehouse_id' => 789,
            'api_key' => 'market-token',
        ]);
        $product = Product::create([
            'sku' => 'OPS-CHANNEL-1',
            'name' => 'Ops Channel Product',
            'price_rub' => 1000,
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);
        ProductSalesChannel::create([
            'product_id' => $product->id,
            'shop_id' => $shop->id,
            'channel' => 'meanly_storefront',
            'is_enabled' => true,
        ]);
        SovereignBalanceRequest::create([
            'legal_entity_id' => $entity->id,
            'type' => 'top_up',
            'amount' => 250,
            'currency' => 'RUB',
            'status' => 'pending',
            'l1_address' => 'sl1e_treasury_fixture',
            'signature_assertion' => ['fixture' => true],
            'comment' => 'Ops recovery fixture',
        ]);
        WildflowCreditReservation::create([
            'legal_entity_id' => $entity->id,
            'amount' => 75,
            'reference' => 'OPS-LIQUIDITY-HOLD',
            'status' => 'active',
            'expires_at' => now()->addHour(),
        ]);
        Currency::create([
            'code' => 'GEL',
            'name' => 'Georgian Lari',
            'rate_to_rub' => 30,
            'base_asset' => 'USDT',
            'quote_asset' => 'GEL',
            'market_regime' => 'THIN',
            'execution_ready' => true,
            'confidence_score' => 0.77,
            'observability_score' => 0.81,
            'liquidity_stress_index' => 0.2,
            'max_executable_size' => 1000,
            'estimated_slippage' => 0.03,
            'settlement_time_hours' => 2,
            'is_auto_update' => false,
        ]);
        LiquidityMethod::create([
            'name' => 'Bank Transfer',
            'slug' => 'bank_transfer',
            'type' => 'bank',
            'is_global' => true,
            'is_active' => true,
        ]);
        LiquidityCorridor::create([
            'currency_code' => 'GEL',
            'provider_node' => 'bank_georgia',
            'routing_asset' => 'USDT',
            'direction' => 'outbound',
            'trust_tier' => 2,
            'base_fee_percent' => 1.5,
            'min_volume' => 10,
            'max_volume' => 1000,
            'sla_minutes' => 60,
            'is_active' => true,
            'metadata' => ['country' => 'GE'],
        ]);
        $node = IntentLiquidityNode::create([
            'intent_key' => 'exchange:currency:GEL',
            'intent_type' => 'exchange',
            'actor_role' => 'liquidity_provider',
            'entity_type' => 'currency',
            'entity_slug' => 'gel',
            'entity_label' => 'GEL',
            'demand_score' => 10,
            'readiness_score' => 80,
            'confidence_score' => 77,
            'status' => 'execution_ready',
        ]);
        IntentLiquidityCorridor::create([
            'intent_liquidity_node_id' => $node->id,
            'corridor_type' => 'currency',
            'corridor_key' => 'USDT:GEL',
            'source' => 'currency_graph',
            'route_type' => 'fx_bridge',
            'route_score' => 80,
            'capacity' => 1000,
            'friction_score' => 20,
            'execution_ready' => true,
            'observed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=treasury')
            ->assertRedirect('/ops?tab=treasury');

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/treasury/data')
            ->assertOk()
            ->assertJsonPath('summary.pending_requests', 1)
            ->assertJsonPath('requests.0.partner', 'Treasury Partner');

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/liquidity/data')
            ->assertOk()
            ->assertJsonPath('summary.execution_ready_currencies', 1)
            ->assertJsonPath('currencies.0.code', 'GEL')
            ->assertJsonPath('methods.0.slug', 'bank_transfer')
            ->assertJsonPath('corridors.0.provider_node', 'bank_georgia')
            ->assertJsonPath('intent_corridors.0.corridor_key', 'USDT:GEL')
            ->assertJsonPath('data.0.partner', 'Treasury Partner')
            ->assertJsonPath('data.0.api_active_reservations', 75);

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/channels/data')
            ->assertOk()
            ->assertJsonPath('summary.enabled_product_links', 1)
            ->assertJsonPath('channels.0.key', 'meanly_storefront');
    }

    public function test_ops_recovers_growth_decision_alerts_panel(): void
    {
        $admin = $this->opsAdmin('i');

        DemandGap::create([
            'canonical_query' => 'apple gift card georgia',
            'brand_entity_key' => 'apple',
            'region_entity_key' => 'georgia',
            'category_entity_key' => 'gift-card',
            'search_volume' => 120,
            'views_count' => 80,
            'carts_count' => 12,
            'zero_results_count' => 20,
            'average_results_count' => 0.4,
            'attributed_orders_count' => 2,
            'attributed_gmv' => 100,
            'estimated_lost_gmv' => 2500,
            'opportunity_score' => 91,
            'opportunity_diagnosis' => 'supply_gap',
            'diagnosis_confidence' => 0.87,
            'demand_gap_score' => 91,
            'priority_label' => 'critical',
            'last_searched_at' => now(),
        ]);
        OpportunityCase::create([
            'canonical_query' => 'apple gift card georgia',
            'status' => OpportunityCase::STATUS_OPEN,
            'owner_team' => OpportunityCase::TEAM_SUPPLIERS,
            'sla_due_at' => now()->subHour(),
            'auto_created' => true,
            'before_opportunity_score' => 91,
            'before_search_volume' => 120,
            'before_views_count' => 80,
            'before_carts_count' => 12,
            'before_orders_count' => 2,
            'before_gmv' => 100,
            'before_diagnosis' => 'supply_gap',
            'before_diagnosis_graph' => json_encode([['cause' => 'NO_SUPPLY']]),
        ]);
        SearchDemandRecommendation::create([
            'recommendation_hash' => hash('sha256', 'apple gift card georgia'),
            'type' => 'catalog_supply',
            'query' => 'Apple Gift Card Georgia',
            'normalized_query' => 'apple gift card georgia',
            'insight_type' => 'add_supply',
            'expected_entity' => ['brand' => 'Apple', 'region' => 'GE'],
            'impact_score' => 91,
            'confidence' => 0.87,
            'evidence' => ['zero_results' => 20],
            'status' => SearchDemandRecommendation::STATUS_PROPOSED,
        ]);
        MeanlyOperationalAlert::create([
            'alert_key' => 'growth:supply-gap:apple-georgia',
            'type' => 'supply_gap',
            'severity' => 'critical',
            'surface' => 'growth',
            'status' => 'open',
            'title' => 'Apple Georgia supply gap',
            'description' => 'Demand exists without enough supply.',
            'occurrence_count' => 3,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=decision-console')
            ->assertRedirect('/ops?tab=decision-console');

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/growth/data')
            ->assertOk()
            ->assertJsonPath('summary.demand_gaps', 1)
            ->assertJsonPath('summary.overdue_cases', 1)
            ->assertJsonPath('summary.proposed_recommendations', 1)
            ->assertJsonPath('summary.open_alerts', 1)
            ->assertJsonPath('demand_gaps.0.query', 'apple gift card georgia')
            ->assertJsonPath('opportunity_cases.0.overdue', true)
            ->assertJsonPath('recommendations.0.insight_type', 'add_supply')
            ->assertJsonPath('alerts.0.title', 'Apple Georgia supply gap');
    }

    public function test_ops_recovers_inventory_warehouse_and_voucher_panel(): void
    {
        $admin = $this->opsAdmin('j');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Inventory Partner',
            'inn' => '770000007777',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]));
        $shop = Shop::create([
            'name' => 'Inventory Shop',
            'legal_entity_id' => $entity->id,
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'OPS-INVENTORY-1',
            'name' => 'Ops Inventory Product',
            'price_rub' => 1000,
            'shop_id' => $shop->id,
            'is_active' => true,
        ]);
        $warehouse = Warehouse::create([
            'shop_id' => $shop->id,
            'name' => 'Master Inventory Warehouse',
            'is_active' => true,
            'is_main' => true,
        ]);
        WarehouseStock::withoutEvents(fn () => WarehouseStock::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'count' => 3,
            'synced_at' => now(),
        ]));
        ProductInventory::withoutEvents(fn () => ProductInventory::create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'sku' => $product->sku,
            'voucher' => 'OPS-INVENTORY-VOUCHER-1',
            'is_used' => false,
            'status' => 'available',
            'nominal_amount' => 10,
            'nominal_currency' => 'USD',
        ]));

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=inventory')
            ->assertRedirect('/ops?tab=inventory');

        $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/inventory/data')
            ->assertOk()
            ->assertJsonPath('summary.warehouses', 1)
            ->assertJsonPath('summary.low_stock_rows', 1)
            ->assertJsonPath('summary.available_vouchers', 1)
            ->assertJsonPath('warehouses.0.name', 'Master Inventory Warehouse')
            ->assertJsonPath('stock.0.count', 3)
            ->assertJsonPath('vouchers.0.status', 'available');
    }

    public function test_ops_inventory_syncs_marketplace_warehouses_from_distribution_master(): void
    {
        $admin = $this->opsAdmin('m');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Marketplace Warehouse Partner',
            'inn' => '770000008888',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]));
        $distributionShop = Shop::create([
            'name' => 'Distribution Center',
            'legal_entity_id' => $entity->id,
            'is_active' => true,
            'is_distribution_center' => true,
        ]);
        $marketplaceShop = Shop::create([
            'name' => 'Marketplace Shop',
            'legal_entity_id' => $entity->id,
            'is_active' => true,
        ]);
        $product = Product::create([
            'sku' => 'OPS-MARKETPLACE-STOCK-1',
            'name' => 'Ops Marketplace Stock Product',
            'price_rub' => 1000,
            'shop_id' => $marketplaceShop->id,
            'is_active' => true,
        ]);
        $master = Warehouse::create([
            'shop_id' => $distributionShop->id,
            'name' => 'Distribution Master',
            'is_active' => true,
            'is_main' => true,
            'channel_quota' => 100,
        ]);
        $marketplaceWarehouse = Warehouse::create([
            'shop_id' => $marketplaceShop->id,
            'name' => 'Marketplace Projection',
            'type' => 'channel',
            'is_active' => true,
            'is_main' => false,
            'channel' => 'meanly_storefront',
            'channel_quota' => 50,
        ]);
        WarehouseStock::withoutEvents(fn () => WarehouseStock::create([
            'warehouse_id' => $master->id,
            'product_id' => $product->id,
            'count' => 9,
            'synced_at' => now(),
        ]));

        $this->actingAs($admin)
            ->postJson('https://meanly.test/ops/dashboard/inventory/sync-warehouses')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('synced_shops', 1)
            ->assertJsonPath('channel_warehouses', 1);

        $this->assertDatabaseHas('warehouse_stocks', [
            'warehouse_id' => $marketplaceWarehouse->id,
            'product_id' => $product->id,
            'count' => 4,
        ]);
    }

    public function test_ops_support_reply_marks_admin_reply_and_resolves_ticket(): void
    {
        $admin = $this->opsAdmin('n');
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Support Partner',
            'inn' => '770000009999',
            'available_balance' => 100,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'is_active' => true,
        ]));
        $shop = Shop::create([
            'name' => 'Support Shop',
            'legal_entity_id' => $entity->id,
            'is_active' => true,
        ]);
        $ticket = Ticket::create([
            'shop_id' => $shop->id,
            'subject' => 'B2B onboarding',
            'status' => 'open',
            'priority' => 'medium',
        ]);
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'message' => 'Need onboarding help',
            'is_admin_reply' => false,
        ]);

        $this->actingAs($admin)
            ->postJson("https://meanly.test/ops/dashboard/tickets/{$ticket->id}/reply", [
                'message' => 'Approved. We will contact you today.',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('ticket.status', 'resolved')
            ->assertJsonPath('reply.is_admin', true);

        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertNotNull($ticket->last_reply_at);
        $this->assertTrue(TicketMessage::where('ticket_id', $ticket->id)->latest('id')->first()->is_admin_reply);

        $this->actingAs($admin)
            ->getJson("https://meanly.test/ops/dashboard/tickets/{$ticket->id}/details")
            ->assertOk()
            ->assertJsonPath('messages.1.is_admin', true)
            ->assertJsonPath('messages.1.message', 'Approved. We will contact you today.');
    }

    public function test_ops_search_integrations_surface_masks_credentials_and_promotes_signals(): void
    {
        $admin = $this->opsAdmin('k');

        $integration = ZeroLayerIntegration::create([
            'name' => 'Google Search Console',
            'source' => 'google_search_console',
            'status' => 'active',
            'credentials' => [
                'access_token' => 'super-secret-token',
                'refresh_token' => 'super-secret-refresh',
            ],
            'settings' => ['site_url' => 'https://meanly.one/'],
            'last_synced_at' => now(),
        ]);

        ZeroLayerSignal::create([
            'zero_layer_integration_id' => $integration->id,
            'source' => 'google_search_console',
            'source_key' => 'gsc:xbox-gift-card-usa',
            'signal_type' => 'search_query',
            'query_text' => 'xbox gift card usa',
            'page_url' => 'https://meanly.one/catalog/products/xbox-usd',
            'position' => 3.2,
            'impressions' => 100,
            'clicks' => 12,
            'signal_date' => now()->toDateString(),
            'payload' => [],
        ]);

        SearchDemandRecommendation::create([
            'recommendation_hash' => hash('sha256', 'ops-search-rec'),
            'type' => 'ADD_PRODUCT',
            'query' => 'xbox gift card usa',
            'normalized_query' => 'xbox gift card usa',
            'insight_type' => 'COVERAGE_GAP',
            'impact_score' => 75,
            'confidence' => 0.88,
            'evidence' => ['source' => 'test'],
            'status' => SearchDemandRecommendation::STATUS_PROPOSED,
        ]);

        $this->actingAs($admin)
            ->get('https://meanly.test/ops?tab=search-integrations')
            ->assertRedirect('/ops?tab=search-integrations');

        $response = $this->actingAs($admin)
            ->getJson('https://meanly.test/ops/dashboard/search-integrations/data')
            ->assertOk()
            ->assertJsonPath('summary.zero_layer_integrations', 1)
            ->assertJsonPath('summary.active_zero_layer_integrations', 1)
            ->assertJsonPath('summary.zero_layer_signals', 1)
            ->assertJsonPath('summary.recommendations_proposed', 1)
            ->assertJsonPath('integrations.0.credential_keys.0', 'access_token')
            ->assertJsonPath('zero_layer_signals.0.query_text', 'xbox gift card usa');

        $this->assertStringNotContainsString('super-secret-token', $response->getContent());

        $connectResponse = $this->actingAs($admin)
            ->postJson('https://meanly.test/ops/dashboard/zero-layer/connect', [
                'name' => 'Meanly GA4',
                'source' => 'google_analytics',
                'credentials' => ['access_token' => 'ga-secret-token'],
                'settings' => ['property_id' => '123456789'],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('integration.name', 'Meanly GA4')
            ->assertJsonPath('integration.source', 'google_analytics')
            ->assertJsonPath('integration.credential_keys.0', 'access_token');

        $this->assertStringNotContainsString('ga-secret-token', $connectResponse->getContent());
        $this->assertDatabaseHas('zero_layer_integrations', [
            'name' => 'Meanly GA4',
            'source' => 'google_analytics',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->postJson('https://meanly.test/ops/dashboard/search-signals/promote-zero-layer', ['limit' => 10])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('imported', 1);

        $this->assertSame(1, ExternalSearchQuerySignal::where('normalized_query', 'xbox gift card usa')->count());
    }

    public function test_ledger_auditor_can_access_tribunal_validation_without_sovereign_validator(): void
    {
        $auditor = $this->opsUserWithRole(User::ROLE_LEDGER_AUDITOR, 'c');

        $this->actingAs($auditor)
            ->postJson('https://meanly.test/ops/dashboard/tribunal/validate-chain')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function opsAdmin(string $identityHex = 'a'): User
    {
        return $this->opsUserWithRole(User::ROLE_SOVEREIGN_VALIDATOR, $identityHex);
    }

    private function opsUserWithRole(string $role, string $identityHex): User
    {
        $user = User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat($identityHex, 39)],
        ]);
        $user->assignRole($role);
        Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        return $user;
    }
}
