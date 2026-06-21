<?php

namespace Tests\Feature;

use App\Jobs\AddCatalogItemToShop;
use App\Models\AuthorityVerdict;
use App\Models\LegalEntity;
use App\Models\MerchantDepositIntent;
use App\Models\Product;
use App\Models\Provider;
use App\Models\ProviderProduct;
use App\Models\SettlementProof;
use App\Models\Shop;
use App\Models\SovereignLedger;
use App\Models\User;
use App\Models\ValidatorAttestation;
use App\Services\MerchantSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantSettlementRailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
        Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
    }

    public function test_creating_deposit_intent_does_not_credit_balance(): void
    {
        [$user, $entity] = $this->merchantEntity(available: 100.00);

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            amount: 250.00,
            options: ['idempotency_key' => 'intent-no-credit'],
        );

        $entity->refresh();

        $this->assertSame(MerchantDepositIntent::STATUS_WAITING_PAYMENT, $intent->status);
        $this->assertSame(100.00, (float) $entity->available_balance);
        $this->assertFalse(SovereignLedger::where('event_type', 'FINANCE_CREDITED')->exists());
    }

    public function test_confirmed_proof_credits_rub_once(): void
    {
        [$user, $entity] = $this->merchantEntity();

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            amount: 500.00,
            options: ['idempotency_key' => 'intent-credit-once'],
        );

        $service = app(MerchantSettlementService::class);
        $service->approveProofAndCredit($intent, $user, 'BANK-REF-1', 500.00);
        $service->approveProofAndCredit($intent->refresh(), $user, 'BANK-REF-1', 500.00);

        $this->assertSame(500.00, (float) $entity->refresh()->available_balance);
        $this->assertSame(1, SettlementProof::whereNotNull('credited_ledger_id')->count());
        $this->assertSame(1, SovereignLedger::where('event_type', 'FINANCE_CREDITED')->count());
        $this->assertSame(1, AuthorityVerdict::where('status', AuthorityVerdict::STATUS_CREDITED)->count());
        $this->assertSame(1, ValidatorAttestation::where('status', ValidatorAttestation::STATUS_ACCEPTED)->count());
    }

    public function test_recording_proof_and_attestation_do_not_credit_without_authority_evaluation(): void
    {
        [$user, $entity] = $this->merchantEntity();
        $service = app(MerchantSettlementService::class);
        $intent = $service->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            amount: 300.00,
            options: ['idempotency_key' => 'proof-attestation-no-direct-credit'],
        );

        $proof = $service->recordProof($intent, 'BANK-PROOF-ONLY', 300.00);
        $service->attestProof($proof, $user, ValidatorAttestation::TYPE_PROOF_OBSERVED, 'BANK-PROOF-ONLY');

        $this->assertSame(0.00, (float) $entity->refresh()->available_balance);
        $this->assertFalse(SovereignLedger::where('event_type', 'FINANCE_CREDITED')->exists());
        $this->assertFalse(AuthorityVerdict::where('status', AuthorityVerdict::STATUS_CREDITED)->exists());

        $service->evaluateAndCreditIfAllowed($proof->refresh());

        $this->assertSame(300.00, (float) $entity->refresh()->available_balance);
        $this->assertSame('AUTHORITY:CREDIT', SovereignLedger::where('event_type', 'FINANCE_CREDITED')->first()?->trigger_source);
    }

    public function test_rejected_intent_never_credits_balance(): void
    {
        [$user, $entity] = $this->merchantEntity(available: 50.00);

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_OPS_MANUAL_CREDIT,
            amount: 500.00,
            options: ['idempotency_key' => 'intent-rejected'],
        );

        app(MerchantSettlementService::class)->rejectIntent($intent, $user, 'External payment was not verified.');

        $this->assertSame(50.00, (float) $entity->refresh()->available_balance);
        $this->assertSame(MerchantDepositIntent::STATUS_REJECTED, $intent->refresh()->status);
        $this->assertFalse(SovereignLedger::where('event_type', 'FINANCE_CREDITED')->exists());
    }

    public function test_merchant_to_merchant_transfer_debits_source_and_credits_target(): void
    {
        [$sourceUser, $source] = $this->merchantEntity(name: 'Source Merchant', available: 1000.00);
        [, $target] = $this->merchantEntity(name: 'Target Merchant', available: 100.00);

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $source,
            createdBy: $sourceUser,
            rail: MerchantDepositIntent::RAIL_MERCHANT_TRANSFER,
            amount: 250.00,
            options: [
                'target_legal_entity_id' => $target->id,
                'note' => 'Shared inventory funding',
            ],
        );

        $this->assertSame(MerchantDepositIntent::STATUS_CREDITED, $intent->status);
        $this->assertSame(750.00, (float) $source->refresh()->available_balance);
        $this->assertSame(350.00, (float) $target->refresh()->available_balance);
        $this->assertTrue(SovereignLedger::where('event_type', 'FINANCE_TRANSFER_DEBIT')->where('legal_entity_id', $source->id)->exists());
        $this->assertTrue(SovereignLedger::where('event_type', 'FINANCE_TRANSFER_CREDIT')->where('legal_entity_id', $target->id)->exists());
        $this->assertTrue(AuthorityVerdict::where('reason_code', 'self_executing_transfer')->where('status', AuthorityVerdict::STATUS_CREDITED)->exists());
    }

    public function test_old_ops_top_up_route_cannot_bypass_authority_runtime(): void
    {
        $ops = $this->opsValidator();
        [, $entity] = $this->merchantEntity(available: 25.00);

        $this->actingAs($ops)
            ->postJson(route('ops.dashboard.partners.top-up', ['legalEntity' => $entity->id]), [
                'amount' => 75,
                'reference' => 'OPS-AUTH-1',
            ])
            ->assertOk()
            ->assertJsonPath('authority_verdict.decision', AuthorityVerdict::DECISION_ALLOW)
            ->assertJsonPath('authority_verdict.status', AuthorityVerdict::STATUS_CREDITED);

        $ledger = SovereignLedger::where('event_type', 'FINANCE_CREDITED')->first();
        $this->assertSame(100.00, (float) $entity->refresh()->available_balance);
        $this->assertSame('AUTHORITY:CREDIT', $ledger?->trigger_source);
        $this->assertNotNull(data_get($ledger?->payload, 'authority_verdict_id'));
        $this->assertTrue(ValidatorAttestation::where('attestation_type', ValidatorAttestation::TYPE_PROOF_OBSERVED)->exists());
    }

    public function test_stock_procurement_succeeds_after_credited_top_up(): void
    {
        Queue::fake();
        [$user, $entity] = $this->merchantEntity(available: 0.00);
        $shop = Shop::create([
            'name' => 'Settlement Stock Shop',
            'type' => Shop::TYPE_VOUCHERS,
            'domain' => 'settlement-stock.test',
            'voucher_prefix' => 'STK',
            'is_active' => true,
            'legal_entity_id' => $entity->id,
        ]);
        $provider = Provider::create([
            'name' => 'Direct Test Provider',
            'type' => 'direct_test',
            'is_active' => true,
        ]);
        $providerProduct = ProviderProduct::create([
            'provider_id' => $provider->id,
            'sku' => 'STOCK-TOP-UP-1',
            'market_sku' => 'STOCK-TOP-UP-1',
            'name' => 'Stock Top Up Voucher',
            'category' => 'Gift Cards',
            'canonical_category' => 'gift_cards',
            'purchase_price' => 100.00,
            'retail_price' => 100.00,
            'currency' => 'RUB',
            'is_active' => true,
            'data' => ['currency' => 'RUB'],
        ]);

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_INVOICE_MANUAL,
            amount: 200.00,
            options: ['idempotency_key' => 'stock-after-top-up'],
        );
        app(MerchantSettlementService::class)->approveProofAndCredit($intent, $user, 'BANK-STOCK-1', 200.00);

        (new AddCatalogItemToShop(
            catalogItemId: $providerProduct->id,
            shopId: $shop->id,
            sellerId: $user->id,
            salesChannels: [],
            count: 1,
            paymentMethod: 'rub',
            simpleLayerOneProof: ['signature_method' => 'test']
        ))->handle();

        $this->assertTrue(Product::where('shop_id', $shop->id)->where('provider_id', $provider->id)->exists());
        $this->assertTrue(SovereignLedger::where('event_type', 'FINANCE_HOLD')->where('legal_entity_id', $entity->id)->exists());
        $this->assertTrue(SovereignLedger::where('event_type', 'STOCK_REPLENISH')->where('legal_entity_id', $entity->id)->exists());
    }

    public function test_crypto_deposit_intent_issues_polygon_deposit_address(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        [$user, $entity] = $this->merchantEntity();

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 420.00,
            options: ['idempotency_key' => 'crypto-deposit-address'],
        );

        $this->assertSame(MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC, $intent->rail);
        $this->assertSame('polygon', data_get($intent->provider_payload, 'settlement_network'));
        $this->assertSame('issued', data_get($intent->provider_payload, 'deposit_address_status'));
        $this->assertMatchesRegularExpression(
            '/^0x[a-f0-9]{40}$/',
            (string) data_get($intent->provider_payload, 'deposit_address'),
        );
    }

    public function test_crypto_deposit_proof_verification_accepts_structural_evm_payload(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        [$user, $entity] = $this->merchantEntity();
        $service = app(MerchantSettlementService::class);
        $intent = $service->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 250.00,
            options: ['idempotency_key' => 'crypto-proof-structural'],
        );

        $depositAddress = (string) data_get($intent->provider_payload, 'deposit_address');
        $txHash = '0x'.str_repeat('a', 64);

        $proof = $service->recordVerifiedCryptoProof($intent, $user, [
            'tx_hash' => $txHash,
            'asset' => 'USDT',
            'amount' => '250.00',
            'deposit_address' => $depositAddress,
        ]);

        $this->assertSame($txHash, $proof->external_reference);
        $this->assertSame('evm_deposit_proof', $proof->source);
        $this->assertSame(250.00, (float) $entity->refresh()->available_balance);
    }

    public function test_crypto_deposit_proof_rejects_invalid_tx_hash(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        [$user, $entity] = $this->merchantEntity();
        $service = app(MerchantSettlementService::class);
        $intent = $service->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 100.00,
            options: ['idempotency_key' => 'crypto-proof-invalid'],
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->recordVerifiedCryptoProof($intent, $user, [
            'tx_hash' => 'not-a-hash',
            'asset' => 'USDC',
        ]);
    }

    /**
     * @return array{0: User, 1: LegalEntity}
     */
    private function merchantEntity(string $name = 'Settlement Merchant', float $available = 0.00): array
    {
        $user = User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $name.' '.substr(hash('sha256', uniqid('', true)), 0, 6),
            'short_name' => $name,
            'inn' => (string) random_int(1000000000, 9999999999),
            'status' => 'active',
            'is_active' => true,
            'balance' => $available,
            'available_balance' => $available,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'tariff_type' => 'privileged',
            'markup_percent' => 0,
        ]);
        $user->managedLegalEntities()->attach($entity->id, ['role' => 'owner']);

        return [$user, $entity];
    }

    private function opsValidator(): User
    {
        $user = User::factory()->create([
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat('a', 39)],
        ]);
        $user->assignRole(User::ROLE_SOVEREIGN_VALIDATOR);
        Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        return $user;
    }
}
