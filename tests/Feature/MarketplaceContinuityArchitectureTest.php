<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\MarketplaceTransitionOutbox;
use App\Models\ProjectionRebuildRegistry;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletLedgerEntry;
use App\Models\WildflowKernelOrder;
use App\Services\Continuity\WriterAuthorityService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceContinuityArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_continuity_payloads_are_encrypted_at_rest(): void
    {
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Continuity Partner',
            'inn' => '770000001234',
            'available_balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
            'meanly_api_token' => 'partner-api-token',
            'meanly_ip_whitelist' => ['127.0.0.1'],
            'agreement_metadata' => ['meanly_api_client_id' => 'client-1'],
            'vendor_credentials' => ['secret' => 'provider-secret'],
        ]));

        $rawEntity = DB::table('legal_entities')->where('id', $entity->id)->first();

        $this->assertStringStartsWith('vault:', $rawEntity->meanly_api_token);
        $this->assertStringStartsWith('vault:', $rawEntity->meanly_ip_whitelist);
        $this->assertStringStartsWith('vault:', $rawEntity->agreement_metadata);
        $this->assertStringStartsWith('vault:', $rawEntity->vendor_credentials);
        $this->assertSame('partner-api-token', $entity->fresh()->meanlyApiToken());

        $ledger = SovereignLedger::create([
            'legal_entity_id' => $entity->id,
            'event_type' => 'CONTINUITY_TEST',
            'payload' => ['secret' => 'ledger-secret'],
            'fingerprint' => str_repeat('a', 64),
            'created_at' => now(),
        ]);

        $walletEntry = WalletLedgerEntry::create([
            'user_id' => User::factory()->create()->id,
            'asset' => 'RUBT',
            'direction' => 'credit',
            'entry_type' => 'TEST',
            'amount_minor' => 100,
            'balance_after_minor' => 100,
            'idempotency_key' => 'wallet-continuity-test',
            'payload' => ['secret' => 'wallet-secret'],
        ]);

        $kernelOrder = WildflowKernelOrder::create([
            'legal_entity_id' => $entity->id,
            'provider' => 'ezpin',
            'marketplace_reference' => 'order-1',
            'proxy_reference' => 'proxy-1',
            'vendor_reference' => 'vendor-1',
            'service_sku' => 'sku-1',
            'price' => 1,
            'currency' => 'USD',
            'status' => 'accepted',
            'request_payload' => ['terminal_pin' => '1234'],
            'response_payload' => ['voucher' => 'secret-code'],
        ]);

        $this->assertStringStartsWith('vault:', DB::table('sovereign_ledger')->where('id', $ledger->id)->value('payload'));
        $this->assertStringStartsWith('vault:', DB::table('wallet_ledger_entries')->where('id', $walletEntry->id)->value('payload'));
        $this->assertStringStartsWith('vault:', DB::table('wildflow_kernel_orders')->where('id', $kernelOrder->id)->value('request_payload'));
        $this->assertStringStartsWith('vault:', DB::table('wildflow_kernel_orders')->where('id', $kernelOrder->id)->value('response_payload'));
    }

    public function test_ledger_record_creates_transition_outbox_entry(): void
    {
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Ledger Partner',
            'inn' => '770000005555',
            'available_balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]));

        $ledger = app(LedgerService::class)->record(
            shop: null,
            eventType: 'FINANCE_TOPUP',
            entity: $entity,
            payload: ['amount' => 25, 'currency' => 'RUBT'],
            legalEntity: $entity,
        );

        $outbox = MarketplaceTransitionOutbox::query()
            ->where('transition_hash', $ledger->fingerprint)
            ->first();

        $this->assertNotNull($outbox);
        $this->assertSame('legal_entity:'.$entity->id, $outbox->scope);
        $this->assertSame('FINANCE_TOPUP', $outbox->transition_type);
        $this->assertStringStartsWith('vault:', DB::table('marketplace_transition_outbox')->where('id', $outbox->id)->value('payload'));
        $this->assertStringStartsWith('vault:', DB::table('sovereign_ledger')->where('id', $ledger->id)->value('payload'));
    }

    public function test_continuity_readiness_reports_recovery_confidence(): void
    {
        $exitCode = Artisan::call('marketplace:db-continuity-readiness', ['--json' => true]);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('recovery_confidence', $payload);
        $this->assertArrayHasKey('continuity_status', $payload);
        $this->assertArrayHasKey('writer_authority', $payload);
        $this->assertArrayHasKey('projection_rebuild', $payload);
        $this->assertArrayHasKey('ledger_continuity', $payload);
        $this->assertArrayHasKey('anchor_verification', $payload);
        $this->assertContains($payload['status'], ['HEALTHY', 'DEGRADED']);
        $this->assertContains($payload['continuity_status'], ['healthy', 'degraded']);
    }

    public function test_writer_authority_conflict_makes_continuity_unhealthy(): void
    {
        app(WriterAuthorityService::class)->markConflict('marketplace:global', 'duplicate writer detected');

        $exitCode = Artisan::call('marketplace:db-continuity-readiness', ['--json' => true]);

        $this->assertSame(1, $exitCode);

        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertSame('UNHEALTHY', $payload['status']);
    }

    public function test_balance_rebuild_and_verify_commands_use_ledger_projection(): void
    {
        $entity = LegalEntity::withoutEvents(fn () => LegalEntity::create([
            'name' => 'Balance Partner',
            'inn' => '770000006666',
            'available_balance' => 0,
            'reserved_balance' => 0,
            'balance' => 0,
            'currency' => 'USD',
            'is_active' => true,
        ]));

        app(LedgerService::class)->record(
            shop: null,
            eventType: 'FINANCE_TOPUP',
            entity: $entity,
            payload: ['amount' => 15, 'currency' => 'RUBT'],
            legalEntity: $entity,
        );

        $this->artisan('marketplace:rebuild-balances', [
            '--legal-entity' => $entity->id,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertSame('15.00', $entity->fresh()->available_balance);

        $this->artisan('marketplace:verify-balances', [
            '--legal-entity' => $entity->id,
            '--json' => true,
        ])->assertExitCode(0);

        $exitCode = Artisan::call('marketplace:db-continuity-readiness', ['--json' => true]);
        $payload = json_decode(trim(Artisan::output()), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($payload);
        $this->assertNotNull($payload['last_balance_rebuild']);
        $this->assertNotNull($payload['last_balance_verify']);
        $this->assertContains($payload['projection_rebuild'], ['healthy', 'degraded']);
    }

    public function test_buyer_wallet_projection_rebuild_and_verify_replay_wallet_ledger_entries(): void
    {
        $user = User::factory()->create();

        WalletAccount::create([
            'user_id' => $user->id,
            'asset' => 'SL',
            'available_minor' => 1,
            'reserved_minor' => 2,
        ]);

        foreach ([
            ['direction' => 'credit', 'entry_type' => 'CASHBACK', 'amount_minor' => 1000, 'balance_after_minor' => 1000],
            ['direction' => 'reserve', 'entry_type' => 'MULTIPAY_SL1_RESERVE', 'amount_minor' => 300, 'balance_after_minor' => 700],
            ['direction' => 'debit', 'entry_type' => 'MULTIPAY_SL1_COMMIT', 'amount_minor' => 200, 'balance_after_minor' => 700],
            ['direction' => 'release', 'entry_type' => 'MULTIPAY_SL1_RELEASE', 'amount_minor' => 50, 'balance_after_minor' => 750],
        ] as $index => $entry) {
            WalletLedgerEntry::create([
                'user_id' => $user->id,
                'asset' => 'SL',
                'direction' => $entry['direction'],
                'entry_type' => $entry['entry_type'],
                'amount_minor' => $entry['amount_minor'],
                'balance_after_minor' => $entry['balance_after_minor'],
                'idempotency_key' => 'wallet-projection-test-'.$index,
                'payload' => ['source' => 'test'],
            ]);
        }

        $this->artisan('marketplace:verify-buyer-wallets', [
            '--user' => $user->id,
            '--json' => true,
        ])->assertExitCode(1);

        $this->artisan('marketplace:rebuild-buyer-wallets', [
            '--user' => $user->id,
            '--json' => true,
        ])->assertExitCode(0);

        $account = WalletAccount::query()
            ->where('user_id', $user->id)
            ->where('asset', 'SL')
            ->firstOrFail();

        $this->assertSame(750, (int) $account->available_minor);
        $this->assertSame(50, (int) $account->reserved_minor);

        $this->artisan('marketplace:verify-buyer-wallets', [
            '--user' => $user->id,
            '--json' => true,
        ])->assertExitCode(0);

        $this->assertTrue(ProjectionRebuildRegistry::query()
            ->where('projection_name', 'buyer_wallet_projection')
            ->firstOrFail()
            ->isHealthy());
    }

    public function test_marketplace_orders_projection_rebuild_and_verify_denormalized_fields(): void
    {
        $order = Order::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => 909001,
            'status' => 'PROCESSING',
            'progress_id' => 2,
            'info' => ['buyerTotal' => 20, 'currency' => 'RUB'],
            'client_info' => [],
            'total_amount' => 1,
            'currency' => 'RUB',
            'total_amount_base' => 1,
            'exchange_rate' => 1,
            'cost_amount' => 1,
            'cost_currency' => 'RUB',
            'cost_amount_base' => 1,
            'margin_base' => 0,
        ]);

        OrderItems::create([
            'key' => 'projection-key-1',
            'order_id' => $order->id,
            'sku' => (string) Str::uuid(),
            'count' => 1,
            'activate_till' => now()->addDay()->toDateString(),
            'price_rub' => 5,
            'price_try' => 0,
            'purchase_status' => 'success',
            'is_activated' => true,
        ]);

        $this->artisan('marketplace:verify-orders', [
            '--order' => $order->id,
            '--json' => true,
        ])->assertExitCode(1);

        $this->artisan('marketplace:rebuild-orders', [
            '--order' => $order->id,
            '--json' => true,
        ])->assertExitCode(0);

        $order->refresh();

        $this->assertSame('20.00', $order->total_amount);
        $this->assertSame('5.00', $order->cost_amount);
        $this->assertSame('15.00', $order->margin_base);
        $this->assertSame(4, (int) $order->progress_id);

        $this->artisan('marketplace:verify-orders', [
            '--order' => $order->id,
            '--json' => true,
        ])->assertExitCode(0);
    }

    public function test_catalog_search_registry_is_split_into_concrete_verifiable_projections(): void
    {
        $this->artisan('catalog:verify-identities', ['--json' => true])->assertExitCode(0);
        $this->artisan('search-profile:verify', ['--json' => true])->assertExitCode(0);
        $this->artisan('marketplace:verify-catalog-search', ['--json' => true])->assertExitCode(0);

        $this->assertTrue(ProjectionRebuildRegistry::query()
            ->where('projection_name', 'canonical_product_identity_projection')
            ->firstOrFail()
            ->isHealthy());
        $this->assertTrue(ProjectionRebuildRegistry::query()
            ->where('projection_name', 'canonical_product_search_profile_projection')
            ->firstOrFail()
            ->isHealthy());
        $this->assertSame('class_b_aggregate_projection', ProjectionRebuildRegistry::query()
            ->where('projection_name', 'catalog_search_projection')
            ->value('classification'));
    }
}
