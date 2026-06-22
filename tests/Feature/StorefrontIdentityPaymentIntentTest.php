<?php

namespace Tests\Feature;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\IdentityBinding;
use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentIntent;
use App\Models\ReconciliationRecord;
use App\Models\SettlementAttempt;
use App\Models\User;
use App\Services\Settlement\IdentityPaymentRoutingService;
use App\Services\Settlement\IdentityPaymentService;
use App\Services\Settlement\PaymentFeeAccountingDerivationService;
use App\Services\Settlement\RecipientResolverService;
use App\Services\Settlement\SettlementInstrumentCapabilityService;
use App\Services\StorefrontTokenService;
use App\Services\VaultIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontIdentityPaymentIntentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
        $this->withIdentityPaymentsEnabled();
        config([
            'identity_payments.assets.USDC.decimals' => 6,
            'managed_wallets.networks.ethereum' => true,
            'managed_wallets.networks.base' => true,
        ]);
    }

    #[Test]
    public function payment_intent_routes_identity_to_identity_without_address_lookup(): void
    {
        $alice = $this->createUser('alice', 'sl1e_'.str_repeat('a', 39));
        $selim = $this->createUser('selim_dev', 'sl1e_'.str_repeat('b', 39));

        $this->provisionManagedWallets($alice['entity'], ['base', 'polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payload = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice',
                'asset' => 'USDC',
                'amount' => '10',
            ])
            ->assertCreated()
            ->assertJsonPath('contract.name', IdentityPaymentService::CONTRACT_NAME)
            ->assertJsonPath('contract.version', IdentityPaymentService::CONTRACT_VERSION)
            ->assertJsonPath('intent.status', IdentityPaymentIntent::STATUS_ROUTED)
            ->assertJsonPath('intent.from_identity', $selim['entity'])
            ->assertJsonPath('intent.to_identity', $alice['entity'])
            ->assertJsonPath('intent.from_alias', '@selim_dev')
            ->assertJsonPath('intent.to_alias', '@alice')
            ->assertJsonPath('routing_decision.policy', IdentityPaymentRoutingService::POLICY_SHARED_MANAGED_NETWORK)
            ->assertJsonPath('routing_decision.policy_version', IdentityPaymentRoutingService::POLICY_VERSION)
            ->assertJsonPath('routing_decision.capability_policy_version', SettlementInstrumentCapabilityService::CAPABILITY_POLICY_VERSION)
            ->assertJsonPath('routing_decision.selected.network', 'polygon')
            ->assertJsonPath('routing_decision.reason', IdentityPaymentRoutingService::REASON_HIGHEST_PRIORITY_SHARED_RAIL)
            ->assertJsonPath('routing_decision.amount_wei', '10000000')
            ->assertJsonPath('accounting_event', null)
            ->assertJsonPath('settlement_execution', null)
            ->assertJsonCount(0, 'settlement_attempts')
            ->json();

        $this->assertArrayNotHasKey('address', $payload);
        $this->assertArrayNotHasKey('address', $payload['routing_decision']);
        $this->assertCount(1, $payload['routing_decision']['candidates']);
        $this->assertSame('polygon', $payload['routing_decision']['selected']['network']);
        $this->assertNotNull($payload['recipient_resolution']['snapshot_at']);
        $this->assertSame($alice['entity'], $payload['recipient_resolution']['identity_id']);

        $this->assertSame(1, IdentityPaymentIntent::query()->count());
        $this->assertSame(0, IdentityPaymentAccountingEvent::query()->count());
        $this->assertTrue(data_get($payload, 'limit_decision.approved'));
        $this->assertSame('payment-limits:v1', data_get($payload, 'limit_decision.policy_key'));
        $this->assertSame('0.05', data_get($payload, 'fee_decision.fee_amount'));
        $this->assertSame('payment-fees:v1', data_get($payload, 'fee_decision.policy_key'));
    }

    #[Test]
    public function payment_intent_prefers_polygon_when_sender_and_recipient_share_multiple_rails(): void
    {
        $alice = $this->createUser('alice_route', 'sl1e_'.str_repeat('c', 39));
        $selim = $this->createUser('selim_route', 'sl1e_'.str_repeat('d', 39));

        $this->provisionManagedWallets($alice['entity'], ['base', 'polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $payload = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_route',
                'asset' => 'USDC',
                'amount' => '0.01',
            ])
            ->assertCreated()
            ->assertJsonPath('routing_decision.selected.network', 'polygon')
            ->assertJsonPath('routing_decision.reason', IdentityPaymentRoutingService::REASON_HIGHEST_PRIORITY_SHARED_RAIL)
            ->assertJsonPath('routing_decision.amount_wei', '10000')
            ->json();

        $this->assertCount(2, $payload['routing_decision']['candidates']);
    }

    #[Test]
    public function payment_intent_execution_records_settlement_reference_and_accounting_keys(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                return [
                    'transaction_hash' => '0xabc123def456',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_exec', 'sl1e_'.str_repeat('e', 39));
        $selim = $this->createUser('selim_exec', 'sl1e_'.str_repeat('f', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payload = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_exec',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('intent.status', IdentityPaymentIntent::STATUS_EXECUTED)
            ->assertJsonPath('settlement_execution.tx_reference', '0xabc123def456')
            ->assertJsonPath('settlement_execution.adapter', 'polygon')
            ->assertJsonPath('accounting_event.settlement_reference', '0xabc123def456')
            ->assertJsonPath('reconciliation_record.status', ReconciliationRecord::STATUS_MATCHED)
            ->assertJsonPath('reconciliation_record.identity_from_match', true)
            ->assertJsonPath('reconciliation_record.identity_to_match', true)
            ->assertJsonCount(1, 'settlement_attempts')
            ->json();

        $this->assertGreaterThan(0, (int) data_get($payload, 'accounting_event.debit.binding_id'));
        $this->assertGreaterThan(0, (int) data_get($payload, 'accounting_event.credit.binding_id'));
        $this->assertSame('10.05', data_get($payload, 'accounting_event.debit.total_amount'));
        $this->assertSame('0.05', data_get($payload, 'accounting_event.debit.fee_amount'));
        $this->assertSame('0.05', data_get($payload, 'fee_decision.fee_amount'));
    }

    #[Test]
    public function payment_intent_is_idempotent_for_sender_vault(): void
    {
        $alice = $this->createUser('alice_idem', 'sl1e_'.str_repeat('1', 39));
        $selim = $this->createUser('selim_idem', 'sl1e_'.str_repeat('2', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $first = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_idem',
                'asset' => 'USDC',
                'amount' => '1',
                'idempotency_key' => 'drill-001',
            ])
            ->assertCreated()
            ->json('intent.id');

        $second = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_idem',
                'asset' => 'USDC',
                'amount' => '1',
                'idempotency_key' => 'drill-001',
            ])
            ->assertCreated()
            ->json('intent.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, IdentityPaymentIntent::query()->count());
    }

    #[Test]
    public function payment_intent_rejects_self_transfer(): void
    {
        $selim = $this->createUser('selim_self', 'sl1e_'.str_repeat('3', 39));
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@selim_self',
                'asset' => 'USDC',
                'amount' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_alias']);
    }

    #[Test]
    public function payment_intent_requires_shared_managed_rail(): void
    {
        $alice = $this->createUser('alice_only_base', 'sl1e_'.str_repeat('4', 39));
        $selim = $this->createUser('selim_only_polygon', 'sl1e_'.str_repeat('5', 39));

        $this->provisionManagedWallets($alice['entity'], ['base']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_only_base',
                'asset' => 'USDC',
                'amount' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_alias']);
    }

    #[Test]
    public function payment_routing_uses_payment_routing_capabilities_not_external_receive_rails(): void
    {
        $alice = $this->createUser('alice_solana', 'sl1e_'.str_repeat('a', 39));
        $selim = $this->createUser('selim_solana', 'sl1e_'.str_repeat('b', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $aliceVault = app(VaultIdentityService::class)->resolveForStorefront(
            ['entity_l1_address' => $alice['entity']],
            $alice['user'],
        );

        IdentityBinding::query()->create([
            'vault_id' => $aliceVault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'solana',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'binding_value_original' => 'Solana11111111111111111111111111111112',
            'binding_value_normalized' => 'solana11111111111111111111111111111112',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'metadata' => [
                'protocol' => 'solana',
                'network_label' => 'Solana',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        $resolve = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/resolve-recipient', [
                'alias' => '@alice_solana',
            ])
            ->assertOk()
            ->json();

        $receivingNetworks = array_column($resolve['receiving_capabilities'], 'network');
        $routingNetworks = array_column($resolve['payment_routing_capabilities'], 'network');

        $this->assertContains('solana', $receivingNetworks);
        $this->assertContains('polygon', $receivingNetworks);
        $this->assertNotContains('solana', $routingNetworks);
        $this->assertContains('polygon', $routingNetworks);

        $payload = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_solana',
                'asset' => 'USDC',
                'amount' => '1',
            ])
            ->assertCreated()
            ->json();

        $this->assertSame('polygon', data_get($payload, 'routing_decision.selected.network'));
    }

    #[Test]
    public function policy_v2_does_not_mutate_existing_intents(): void
    {
        config(['capability_policies.default' => 'v1']);

        $alice = $this->createUser('alice_policy', 'sl1e_'.str_repeat('c', 39));
        $selim = $this->createUser('selim_policy', 'sl1e_'.str_repeat('d', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);
        $this->attachSolanaExternalBinding($alice['entity'], $alice['user']);

        $intentA = $this->createPaymentIntent($selim['token'], '@alice_policy', '10');

        $this->assertSame('polygon', data_get($intentA, 'routing_decision.selected.network'));
        $this->assertSame(
            'instrument-capability-policy:v1',
            data_get($intentA, 'routing_decision.capability_policy_version'),
        );
        $this->assertSame('v1', data_get($intentA, 'routing_decision.capability_policy_key'));
        $this->assertSame(
            [
                'instrument-capability-policy:v1',
                'shared_managed_network:v1',
            ],
            data_get($intentA, 'routing_decision.decision_context.policy_keys'),
        );
        $intentARulesetHash = data_get($intentA, 'routing_decision.decision_context.ruleset_hash');
        $this->assertStringStartsWith('sha256:', (string) $intentARulesetHash);

        config(['capability_policies.default' => 'v2']);
        $this->attachSolanaExternalBinding($selim['entity'], $selim['user']);

        $intentB = $this->createPaymentIntent($selim['token'], '@alice_policy', '5');

        $this->assertSame('solana', data_get($intentB, 'routing_decision.selected.network'));
        $this->assertSame(
            'instrument-capability-policy:v2',
            data_get($intentB, 'routing_decision.capability_policy_version'),
        );
        $this->assertSame('v2', data_get($intentB, 'routing_decision.capability_policy_key'));
        $this->assertNotSame(
            $intentARulesetHash,
            data_get($intentB, 'routing_decision.decision_context.ruleset_hash'),
        );

        $reloadedA = app(IdentityPaymentService::class)->formatResponse(
            IdentityPaymentIntent::query()
                ->where('intent_uuid', $intentA['payment_intent']['id'])
                ->firstOrFail(),
        );

        $this->assertSame('polygon', data_get($reloadedA, 'routing_decision.selected.network'));
        $this->assertSame(
            'instrument-capability-policy:v1',
            data_get($reloadedA, 'routing_decision.capability_policy_version'),
        );
        $this->assertSame('v1', data_get($reloadedA, 'routing_decision.capability_policy_key'));
        $this->assertSame($intentARulesetHash, data_get($reloadedA, 'routing_decision.decision_context.ruleset_hash'));
    }

    #[Test]
    public function limit_policy_change_does_not_mutate_existing_intents(): void
    {
        config([
            'capability_policies.default' => 'v1',
            'payment_limits.default' => 'v1',
        ]);

        $alice = $this->createUser('alice_limit', 'sl1e_'.str_repeat('e', 39));
        $selim = $this->createUser('selim_limit', 'sl1e_'.str_repeat('f', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $intentA = $this->createPaymentIntent($selim['token'], '@alice_limit', '10');
        $intentALimitHash = data_get($intentA, 'limit_decision.ruleset_hash');
        $this->assertTrue(data_get($intentA, 'limit_decision.approved'));
        $this->assertSame('payment-limits:v1', data_get($intentA, 'limit_decision.policy_key'));

        config(['payment_limits.default' => 'v2']);

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_limit',
                'asset' => 'USDC',
                'amount' => '5000',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $reloadedA = app(IdentityPaymentService::class)->formatResponse(
            IdentityPaymentIntent::query()
                ->where('intent_uuid', $intentA['payment_intent']['id'])
                ->firstOrFail(),
        );

        $this->assertTrue(data_get($reloadedA, 'limit_decision.approved'));
        $this->assertSame('payment-limits:v1', data_get($reloadedA, 'limit_decision.policy_key'));
        $this->assertSame($intentALimitHash, data_get($reloadedA, 'limit_decision.ruleset_hash'));
    }

    #[Test]
    public function daily_limit_consumption_is_accounting_derived(): void
    {
        config([
            'payment_limits.default' => 'v1',
            'payment_limits.versions.v1.managed_evm.USDC.daily' => '200',
            'payment_limits.versions.v1.managed_evm.USDC.per_transaction' => '200',
        ]);
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            private int $calls = 0;

            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                $this->calls++;

                return [
                    'transaction_hash' => '0xdaily'.$this->calls,
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_daily', 'sl1e_'.str_repeat('1', 39));
        $selim = $this->createUser('selim_daily', 'sl1e_'.str_repeat('2', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $first = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_daily',
                'asset' => 'USDC',
                'amount' => '100',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $this->assertSame('0', data_get($first, 'limit_decision.daily_consumed_before'));
        $this->assertSame('100', data_get($first, 'limit_decision.daily_remaining_after'));

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_daily',
                'asset' => 'USDC',
                'amount' => '150',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $originalIntentUuid = $first['payment_intent']['id'];

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $originalIntentUuid,
                'reversal_reason' => 'refund',
                'execute' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('limit_decision.approved', true);

        $afterReversal = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_daily',
                'asset' => 'USDC',
                'amount' => '150',
            ])
            ->assertCreated()
            ->json();

        $this->assertTrue(data_get($afterReversal, 'limit_decision.approved'));
        $this->assertSame('0', data_get($afterReversal, 'limit_decision.daily_consumed_before'));
        $this->assertSame('50', data_get($afterReversal, 'limit_decision.daily_remaining_after'));
    }

    #[Test]
    public function fee_policy_change_does_not_mutate_existing_intents(): void
    {
        config([
            'payment_fees.default' => 'v1',
            'payment_limits.default' => 'v1',
        ]);

        $alice = $this->createUser('alice_fee', 'sl1e_'.str_repeat('3', 39));
        $selim = $this->createUser('selim_fee', 'sl1e_'.str_repeat('4', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $intentA = $this->createPaymentIntent($selim['token'], '@alice_fee', '10');
        $intentAFeeHash = data_get($intentA, 'fee_decision.ruleset_hash');
        $this->assertSame('0.05', data_get($intentA, 'fee_decision.fee_amount'));
        $this->assertSame('payment-fees:v1', data_get($intentA, 'fee_decision.policy_key'));

        config(['payment_fees.default' => 'v2']);

        $intentB = $this->createPaymentIntent($selim['token'], '@alice_fee', '10');
        $this->assertSame('0.1', data_get($intentB, 'fee_decision.fee_amount'));
        $this->assertSame('payment-fees:v2', data_get($intentB, 'fee_decision.policy_key'));

        $reloadedA = app(IdentityPaymentService::class)->formatResponse(
            IdentityPaymentIntent::query()
                ->where('intent_uuid', $intentA['payment_intent']['id'])
                ->firstOrFail(),
        );

        $this->assertSame('0.05', data_get($reloadedA, 'fee_decision.fee_amount'));
        $this->assertSame('payment-fees:v1', data_get($reloadedA, 'fee_decision.policy_key'));
        $this->assertSame($intentAFeeHash, data_get($reloadedA, 'fee_decision.ruleset_hash'));
    }

    #[Test]
    public function fee_accounting_is_derived_from_accounting_entries(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                return [
                    'transaction_hash' => '0xfeeaccounting',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_feeacct', 'sl1e_'.str_repeat('5', 39));
        $selim = $this->createUser('selim_feeacct', 'sl1e_'.str_repeat('6', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payload = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_feeacct',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $accounting = IdentityPaymentAccountingEvent::query()->firstOrFail();
        $derived = app(PaymentFeeAccountingDerivationService::class)->deriveSenderEconomics($accounting);

        $this->assertSame('10', $derived['payment_amount']);
        $this->assertSame('0.05', $derived['fee_amount']);
        $this->assertSame('10.05', $derived['sender_total_debit']);
        $this->assertNotSame(
            $derived['sender_total_debit'],
            data_get($payload, 'payment_intent.amount'),
        );
        $this->assertSame(
            '+0.05',
            collect(data_get($payload, 'accounting_event.entries', []))
                ->firstWhere('account', 'platform_fee')['delta'] ?? null,
        );
    }

    #[Test]
    public function reversal_does_not_mutate_original_fee_decision_or_accounting(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            private int $calls = 0;

            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                $this->calls++;

                return [
                    'transaction_hash' => '0xfeeoriginal'.$this->calls,
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_feerev', 'sl1e_'.str_repeat('7', 39));
        $selim = $this->createUser('selim_feerev', 'sl1e_'.str_repeat('8', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $original = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_feerev',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $originalIntentUuid = $original['payment_intent']['id'];
        $originalFeeDecision = $original['fee_decision'];
        $originalAccounting = $original['accounting_event'];

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $originalIntentUuid,
                'reversal_reason' => 'refund',
                'execute' => true,
            ])
            ->assertCreated();

        $reloadedOriginal = app(IdentityPaymentService::class)->formatResponse(
            IdentityPaymentIntent::query()
                ->where('intent_uuid', $originalIntentUuid)
                ->with('accountingEvent')
                ->firstOrFail(),
        );

        $this->assertSame($originalFeeDecision, $reloadedOriginal['fee_decision']);
        $this->assertSame($originalAccounting['debit']['fee_amount'], data_get($reloadedOriginal, 'accounting_event.debit.fee_amount'));
        $this->assertSame(
            '+0.05',
            collect(data_get($reloadedOriginal, 'accounting_event.entries', []))
                ->firstWhere('account', 'platform_fee')['delta'] ?? null,
        );
        $this->assertSame(2, IdentityPaymentAccountingEvent::query()->count());
    }

    #[Test]
    public function payment_intent_is_disabled_without_feature_flag(): void
    {
        config(['identity_payments.enabled' => false]);

        $alice = $this->createUser('alice_disabled', 'sl1e_'.str_repeat('6', 39));
        $selim = $this->createUser('selim_disabled', 'sl1e_'.str_repeat('7', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_disabled',
                'asset' => 'USDC',
                'amount' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment']);
    }

    #[Test]
    public function routing_decision_is_reproducible_for_unchanged_graph_and_policy(): void
    {
        $alice = $this->createUser('alice_repro', 'sl1e_'.str_repeat('8', 39));
        $selim = $this->createUser('selim_repro', 'sl1e_'.str_repeat('9', 39));

        $this->provisionManagedWallets($alice['entity'], ['base', 'polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $first = $this->createPaymentIntent($selim['token'], '@alice_repro', '10');
        $second = $this->createPaymentIntent($selim['token'], '@alice_repro', '10');

        $this->assertSame(
            $this->routingFingerprint($first),
            $this->routingFingerprint($second),
        );
        $this->assertSame(
            IdentityPaymentRoutingService::POLICY_VERSION,
            data_get($first, 'routing_decision.policy_version'),
        );
    }

    #[Test]
    public function instrument_mutation_keeps_recipient_identity_and_changes_selected_rail(): void
    {
        $alice = $this->createUser('alice_mutate', 'sl1e_'.str_repeat('0', 39));
        $selim = $this->createUser('selim_mutate', 'sl1e_'.str_repeat('1', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $beforePayment = $this->createPaymentIntent($selim['token'], '@alice_mutate', '10');
        $beforeResolve = $this->resolveRecipient($selim['token'], '@alice_mutate');

        $this->assertSame('polygon', data_get($beforePayment, 'routing_decision.selected.network'));
        $this->assertSame($alice['entity'], data_get($beforePayment, 'intent.to_identity'));

        $polygonBindingId = $this->bindingIdForNetwork($beforeResolve, 'polygon');
        $this->revokeManagedBinding($alice['token'], $polygonBindingId);
        $this->provisionManagedWallets($alice['entity'], ['base']);

        $afterResolve = $this->resolveRecipient($selim['token'], '@alice_mutate');
        $afterPayment = $this->createPaymentIntent($selim['token'], '@alice_mutate', '10');

        $this->assertSame($beforeResolve['identity_id'], $afterResolve['identity_id']);
        $this->assertNull($this->bindingIdForNetwork($afterResolve, 'polygon'));
        $this->assertNotNull($this->bindingIdForNetwork($afterResolve, 'base'));

        $this->assertSame($alice['entity'], data_get($afterPayment, 'intent.to_identity'));
        $this->assertSame('@alice_mutate', data_get($afterPayment, 'intent.to_alias'));
        $this->assertSame('base', data_get($afterPayment, 'routing_decision.selected.network'));
        $this->assertNotSame(
            data_get($beforePayment, 'routing_decision.selected.network'),
            data_get($afterPayment, 'routing_decision.selected.network'),
        );
    }

    #[Test]
    public function routing_and_resolution_snapshots_remain_immutable_after_recipient_graph_changes(): void
    {
        $alice = $this->createUser('alice_snapshot', 'sl1e_'.str_repeat('2', 39));
        $selim = $this->createUser('selim_snapshot', 'sl1e_'.str_repeat('3', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $original = $this->createPaymentIntent($selim['token'], '@alice_snapshot', '10');
        $originalRouting = $original['routing_decision'];
        $originalResolution = $original['recipient_resolution'];

        $polygonBindingId = $this->bindingIdForNetwork($originalResolution, 'polygon');
        $this->revokeManagedBinding($alice['token'], $polygonBindingId);
        $this->provisionManagedWallets($alice['entity'], ['base']);

        $reloaded = IdentityPaymentIntent::query()
            ->where('intent_uuid', $original['payment_intent']['id'])
            ->firstOrFail();

        $formatted = app(IdentityPaymentService::class)->formatResponse($reloaded);

        $this->assertSame($originalRouting, $formatted['routing_decision']);
        $this->assertSame($originalResolution, $formatted['recipient_resolution']);
        $this->assertSame('polygon', $formatted['routing_decision']['selected']['network']);
        $this->assertNotEmpty($formatted['recipient_resolution']['payment_routing_capabilities']);
        $this->assertSame(
            SettlementInstrumentCapabilityService::CAPABILITY_POLICY_VERSION,
            $formatted['routing_decision']['capability_policy_version'],
        );
    }

    #[Test]
    public function payment_intent_only_references_capabilities_frozen_at_creation(): void
    {
        $alice = $this->createUser('alice_t0', 'sl1e_'.str_repeat('6', 39));
        $selim = $this->createUser('selim_t0', 'sl1e_'.str_repeat('7', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $original = $this->createPaymentIntent($selim['token'], '@alice_t0', '10');
        $this->assertSame('polygon', data_get($original, 'routing_decision.selected.network'));
        $this->assertCount(1, data_get($original, 'routing_decision.recipient_payment_routing_capabilities'));

        $polygonBindingId = $this->bindingIdForNetwork($original['recipient_resolution'], 'polygon');
        $this->revokeManagedBinding($alice['token'], $polygonBindingId);
        $this->provisionManagedWallets($alice['entity'], ['base']);

        $newIntent = $this->createPaymentIntent($selim['token'], '@alice_t0', '1');
        $this->assertSame('base', data_get($newIntent, 'routing_decision.selected.network'));

        $reloaded = app(IdentityPaymentService::class)->formatResponse(
            IdentityPaymentIntent::query()
                ->where('intent_uuid', $original['payment_intent']['id'])
                ->firstOrFail(),
        );

        $this->assertSame('polygon', data_get($reloaded, 'routing_decision.selected.network'));
        $this->assertSame(
            data_get($original, 'routing_decision.recipient_payment_routing_capabilities'),
            data_get($reloaded, 'routing_decision.recipient_payment_routing_capabilities'),
        );
        $this->assertNotSame(
            data_get($newIntent, 'routing_decision.recipient_payment_routing_capabilities'),
            data_get($reloaded, 'routing_decision.recipient_payment_routing_capabilities'),
        );
    }

    #[Test]
    public function deferred_execution_uses_frozen_routing_snapshot_after_recipient_graph_changes(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);

        $captured = new class
        {
            public ?int $senderBindingId = null;

            public ?string $networkKey = null;
        };

        $this->app->instance(IdentityPaymentExecutor::class, new class($captured) implements IdentityPaymentExecutor
        {
            public function __construct(private object $captured) {}

            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                $this->captured->senderBindingId = $senderBindingId;
                $this->captured->networkKey = $networkKey;

                return [
                    'transaction_hash' => '0xfrozenroute',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_deferred', 'sl1e_'.str_repeat('4', 39));
        $selim = $this->createUser('selim_deferred', 'sl1e_'.str_repeat('5', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['base', 'polygon']);

        $planned = $this->createPaymentIntent($selim['token'], '@alice_deferred', '10');
        $frozenSenderBindingId = (int) data_get($planned, 'routing_decision.selected.sender_binding_id');
        $frozenReceiverBindingId = (int) data_get($planned, 'routing_decision.selected.receiver_binding_id');

        $polygonBindingId = $this->bindingIdForNetwork($planned['recipient_resolution'], 'polygon');
        $this->revokeManagedBinding($alice['token'], $polygonBindingId);
        $this->provisionManagedWallets($alice['entity'], ['base']);

        $afterMutation = $this->createPaymentIntent($selim['token'], '@alice_deferred', '1');
        $this->assertSame('base', data_get($afterMutation, 'routing_decision.selected.network'));

        $executed = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$planned['payment_intent']['id'].'/execute')
            ->assertOk()
            ->assertJsonPath('payment_intent.status', IdentityPaymentIntent::STATUS_EXECUTED)
            ->assertJsonPath('routing_decision.selected.network', 'polygon')
            ->assertJsonPath('settlement_execution.tx_reference', '0xfrozenroute')
            ->json();

        $this->assertSame($frozenSenderBindingId, $captured->senderBindingId);
        $this->assertSame('polygon', $captured->networkKey);
        $this->assertSame($frozenSenderBindingId, (int) data_get($executed, 'settlement_execution.sender_binding_id'));
        $this->assertSame($frozenReceiverBindingId, (int) data_get($executed, 'settlement_execution.receiver_binding_id'));
        $this->assertSame(1, (int) data_get($executed, 'settlement_attempts.0.attempt_no'));
    }

    #[Test]
    public function failed_settlement_attempt_keeps_intent_routed_and_defers_accounting_until_success(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);

        $attemptCounter = new class
        {
            public int $count = 0;
        };

        $this->app->instance(IdentityPaymentExecutor::class, new class($attemptCounter) implements IdentityPaymentExecutor
        {
            public function __construct(private object $attemptCounter) {}

            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                $this->attemptCounter->count++;

                if ($this->attemptCounter->count === 1) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'execute' => 'gas issue',
                    ]);
                }

                return [
                    'transaction_hash' => '0xretryok',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_retry', 'sl1e_'.str_repeat('6', 39));
        $selim = $this->createUser('selim_retry', 'sl1e_'.str_repeat('7', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $planned = $this->createPaymentIntent($selim['token'], '@alice_retry', '10');
        $intentId = $planned['payment_intent']['id'];

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$intentId.'/execute')
            ->assertUnprocessable();

        $afterFailure = IdentityPaymentIntent::query()->where('intent_uuid', $intentId)->firstOrFail();
        $this->assertSame(IdentityPaymentIntent::STATUS_ROUTED, $afterFailure->status);
        $this->assertSame(1, SettlementAttempt::query()->where('identity_payment_intent_id', $afterFailure->id)->count());
        $this->assertSame(SettlementAttempt::STATUS_FAILED, SettlementAttempt::query()->first()->status);
        $this->assertSame(0, IdentityPaymentAccountingEvent::query()->count());

        $success = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$intentId.'/execute')
            ->assertOk()
            ->assertJsonPath('payment_intent.status', IdentityPaymentIntent::STATUS_EXECUTED)
            ->assertJsonPath('accounting_event.narrative', 'selim_retry → alice_retry : 10 USDC')
            ->assertJsonCount(2, 'settlement_attempts')
            ->json();

        $this->assertSame(SettlementAttempt::STATUS_FAILED, $success['settlement_attempts'][0]['status']);
        $this->assertSame(SettlementAttempt::STATUS_CONFIRMED, $success['settlement_attempts'][1]['status']);
        $this->assertSame(1, IdentityPaymentAccountingEvent::query()->count());
        $this->assertSame(ReconciliationRecord::STATUS_MATCHED, data_get($success, 'reconciliation_record.status'));
    }

    #[Test]
    public function reversal_preserves_original_intent_and_reconciliation(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                return [
                    'transaction_hash' => '0xoriginalpay',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_rev', 'sl1e_'.str_repeat('8', 39));
        $selim = $this->createUser('selim_rev', 'sl1e_'.str_repeat('9', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $original = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_rev',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $originalIntentUuid = $original['payment_intent']['id'];
        $originalSnapshot = $original['routing_decision'];
        $originalReconciliationStatus = $original['reconciliation_record']['status'];

        $reversal = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $originalIntentUuid,
                'reversal_reason' => 'refund',
            ])
            ->assertCreated()
            ->assertJsonPath('payment_intent.reversal_of_intent_id', $originalIntentUuid)
            ->assertJsonPath('payment_intent.reversal_reason', 'refund')
            ->assertJsonPath('payment_intent.from_alias', '@alice_rev')
            ->assertJsonPath('payment_intent.to_alias', '@selim_rev')
            ->json();

        $reloadedOriginal = IdentityPaymentIntent::query()
            ->where('intent_uuid', $originalIntentUuid)
            ->with(['accountingEvent.reconciliationRecord'])
            ->firstOrFail();

        $formattedOriginal = app(IdentityPaymentService::class)->formatResponse($reloadedOriginal);

        $this->assertSame($originalSnapshot, $formattedOriginal['routing_decision']);
        $this->assertSame($originalReconciliationStatus, $formattedOriginal['reconciliation_record']['status']);
        $this->assertSame(IdentityPaymentIntent::STATUS_EXECUTED, $formattedOriginal['payment_intent']['status']);
        $this->assertSame(IdentityPaymentIntent::STATUS_ROUTED, $reversal['payment_intent']['status']);
        $this->assertNull($reversal['accounting_event']);
    }

    #[Test]
    public function double_reversal_of_same_intent_is_rejected(): void
    {
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->app->instance(IdentityPaymentExecutor::class, new class implements IdentityPaymentExecutor
        {
            public function executeUsdcTransfer(
                int $senderBindingId,
                string $recipientAddressNormalized,
                string $amountWei,
                string $networkKey,
            ): array {
                return [
                    'transaction_hash' => '0xpay',
                    'network' => $networkKey,
                ];
            }
        });

        $alice = $this->createUser('alice_double', 'sl1e_'.str_repeat('0', 39));
        $selim = $this->createUser('selim_double', 'sl1e_'.str_repeat('1', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $originalUuid = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_double',
                'asset' => 'USDC',
                'amount' => '5',
                'execute' => true,
            ])
            ->assertCreated()
            ->json('payment_intent.id');

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $originalUuid,
            ])
            ->assertCreated();

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $originalUuid,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reversal_of_intent_id']);
    }

    #[Test]
    public function payment_intent_list_returns_sender_vault_history_newest_first(): void
    {
        $alice = $this->createUser('alice_list', 'sl1e_'.str_repeat('7', 39));
        $selim = $this->createUser('selim_list', 'sl1e_'.str_repeat('8', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $first = $this->createPaymentIntent($selim['token'], '@alice_list', '1');
        $second = $this->createPaymentIntent($selim['token'], '@alice_list', '2');

        $payload = $this->withToken($selim['token'])
            ->getJson('/api/storefront/v1/settlement/payment-intents')
            ->assertOk()
            ->assertJsonPath('contract.name', 'payment-intent-list')
            ->assertJsonPath('contract.version', IdentityPaymentService::CONTRACT_VERSION)
            ->assertJsonCount(2, 'items')
            ->json();

        $this->assertSame($second['payment_intent']['id'], $payload['items'][0]['payment_intent']['id']);
        $this->assertSame($first['payment_intent']['id'], $payload['items'][1]['payment_intent']['id']);
        $this->assertSame('outgoing', $payload['items'][0]['activity_direction']);
    }

    #[Test]
    public function payment_intent_list_includes_incoming_for_receiver_identity(): void
    {
        $alice = $this->createUser('alice_in', 'sl1e_'.str_repeat('9', 39));
        $selim = $this->createUser('selim_in', 'sl1e_'.str_repeat('a', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $this->createPaymentIntent($selim['token'], '@alice_in', '10');
        $this->createPaymentIntent($alice['token'], '@selim_in', '25');

        $payload = $this->withToken($selim['token'])
            ->getJson('/api/storefront/v1/settlement/payment-intents')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->json();

        $directions = array_map(
            static fn (array $item) => $item['activity_direction'] ?? null,
            $payload['items'],
        );

        $this->assertContains('outgoing', $directions);
        $this->assertContains('incoming', $directions);
    }

    /**
     * @return array<string, mixed>
     */
    private function createPaymentIntent(string $senderToken, string $toAlias, string $amount): array
    {
        return $this->withToken($senderToken)
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => $toAlias,
                'asset' => 'USDC',
                'amount' => $amount,
            ])
            ->assertCreated()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function routingFingerprint(array $payload): array
    {
        return [
            'network' => data_get($payload, 'routing_decision.selected.network'),
            'sender_binding_id' => data_get($payload, 'routing_decision.selected.sender_binding_id'),
            'recipient_binding_id' => data_get($payload, 'routing_decision.selected.receiver_binding_id'),
            'policy' => data_get($payload, 'routing_decision.policy'),
            'policy_version' => data_get($payload, 'routing_decision.policy_version'),
            'reason' => data_get($payload, 'routing_decision.reason'),
            'shared_rails' => data_get($payload, 'routing_decision.candidates'),
            'candidates' => data_get($payload, 'routing_decision.candidates'),
            'selected' => data_get($payload, 'routing_decision.selected'),
            'amount_wei' => data_get($payload, 'routing_decision.amount_wei'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveRecipient(string $senderToken, string $alias): array
    {
        return $this->withToken($senderToken)
            ->postJson('/api/storefront/v1/settlement/resolve-recipient', [
                'alias' => $alias,
            ])
            ->assertOk()
            ->json();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function bindingIdForNetwork(array $payload, string $network): ?int
    {
        foreach ($payload['receiving_capabilities'] as $capability) {
            if (($capability['network'] ?? null) === $network) {
                return (int) $capability['binding_id'];
            }
        }

        return null;
    }

    private function revokeManagedBinding(string $token, ?int $bindingId): void
    {
        $this->assertNotNull($bindingId);

        $this->withToken($token)
            ->deleteJson('/api/storefront/v1/wallet/bindings/'.$bindingId)
            ->assertOk()
            ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_REVOKED);
    }

    /**
     * @return array{entity: string, token: string, user: User}
     */
    private function createUser(string $username, string $entityAddress): array
    {
        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'username' => $username,
            'username_key' => $username,
        ]);

        return [
            'entity' => $entityAddress,
            'user' => $user,
            'token' => $this->vaultToken($entityAddress),
        ];
    }

    /**
     * @param  list<string>  $networks
     */
    private function provisionManagedWallets(string $entityAddress, array $networks): void
    {
        $token = $this->vaultToken($entityAddress);

        foreach ($networks as $networkKey) {
            $this->withToken($token)
                ->postJson('/api/storefront/v1/wallet/bindings/managed', [
                    'binding_key' => $networkKey,
                ])
                ->assertCreated()
                ->assertJsonPath('binding.binding_source', IdentityBinding::SOURCE_MANAGED)
                ->assertJsonPath('binding.verification_state', IdentityBinding::STATE_VERIFIED);
        }
    }

    private function attachSolanaExternalBinding(string $entityAddress, User $user): void
    {
        $vault = app(VaultIdentityService::class)->resolveForStorefront(
            ['entity_l1_address' => $entityAddress],
            $user,
        );

        IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'solana',
            'binding_source' => IdentityBinding::SOURCE_EXTERNAL,
            'binding_value_original' => 'Solana11111111111111111111111111111112',
            'binding_value_normalized' => 'solana11111111111111111111111111111112',
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'metadata' => [
                'protocol' => 'solana',
                'network_label' => 'Solana',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
