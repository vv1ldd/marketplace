<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\MarketplaceTransitionOutbox;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\WalletLedgerEntry;
use App\Models\WildflowKernelOrder;
use App\Services\Continuity\WriterAuthorityService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
}
