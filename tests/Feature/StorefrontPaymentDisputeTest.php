<?php

namespace Tests\Feature;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\IdentityPaymentAccountingEvent;
use App\Models\IdentityPaymentDispute;
use App\Models\IdentityPaymentIntent;
use App\Models\User;
use App\Services\Settlement\IdentityPaymentService;
use App\Services\Settlement\PaymentDisputeEvidenceViewerService;
use App\Services\Settlement\PaymentDisputeService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontPaymentDisputeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
        $this->withIdentityPaymentsEnabled(execute: true);
        $this->withIdentityPaymentDisputesEnabled();
        config([
            'identity_payments.assets.USDC.decimals' => 6,
            'managed_wallets.networks.ethereum' => true,
            'managed_wallets.networks.base' => true,
        ]);
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
                    'transaction_hash' => '0xdispute'.$this->calls,
                    'network' => $networkKey,
                ];
            }
        });
    }

    #[Test]
    public function dispute_does_not_mutate_financial_history(): void
    {
        $alice = $this->createUser('alice_dispute', 'sl1e_'.str_repeat('a', 39));
        $selim = $this->createUser('selim_dispute', 'sl1e_'.str_repeat('b', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $paymentA = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_dispute',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $intentAUuid = $paymentA['payment_intent']['id'];
        $originalAccounting = IdentityPaymentAccountingEvent::query()
            ->where('identity_payment_intent_id', IdentityPaymentIntent::query()->where('intent_uuid', $intentAUuid)->value('id'))
            ->firstOrFail()
            ->toArray();

        $dispute = $this->openAndResolveDispute(
            $alice['token'],
            $intentAUuid,
            executeCompensation: true,
        );

        $this->assertSame(IdentityPaymentDispute::STATUS_RESOLVED, $dispute['dispute']['status']);
        $this->assertSame('refund_approved', $dispute['resolution']['outcome']);

        $reloadedIntentA = IdentityPaymentIntent::query()
            ->where('intent_uuid', $intentAUuid)
            ->with('accountingEvent')
            ->firstOrFail();

        $this->assertSame(IdentityPaymentIntent::STATUS_EXECUTED, $reloadedIntentA->status);

        $reloadedAccounting = $reloadedIntentA->accountingEvent?->toArray();
        $this->assertSame($originalAccounting['amount'], $reloadedAccounting['amount']);
        $this->assertSame($originalAccounting['metadata'], $reloadedAccounting['metadata']);

        $this->assertSame(2, IdentityPaymentIntent::query()->count());
        $this->assertSame(2, IdentityPaymentAccountingEvent::query()->count());

        $compensationUuid = $dispute['compensation_intent_id'];
        $this->assertNotNull($compensationUuid);

        $compensation = IdentityPaymentIntent::query()
            ->where('intent_uuid', $compensationUuid)
            ->with('accountingEvent')
            ->firstOrFail();

        $this->assertSame($intentAUuid, $compensation->reversalOf?->intent_uuid);
        $this->assertSame('10', $compensation->amount);
        $this->assertSame(IdentityPaymentIntent::STATUS_EXECUTED, $compensation->status);

        $formattedA = app(IdentityPaymentService::class)->formatResponse($reloadedIntentA);
        $this->assertSame(
            '+0.05',
            collect(data_get($formattedA, 'accounting_event.entries', []))
                ->firstWhere('account', 'platform_fee')['delta'] ?? null,
        );

        $formattedB = app(IdentityPaymentService::class)->formatResponse($compensation);
        $this->assertSame('10', data_get($formattedB, 'accounting_event.credit.amount'));
        $this->assertSame('10', data_get($formattedB, 'accounting_event.debit.amount'));
    }

    #[Test]
    public function dispute_evidence_snapshot_is_immutable_after_policy_changes(): void
    {
        config(['payment_fees.default' => 'v1']);

        $alice = $this->createUser('alice_evidence', 'sl1e_'.str_repeat('c', 39));
        $selim = $this->createUser('selim_evidence', 'sl1e_'.str_repeat('d', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_evidence',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $dispute = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$payment['payment_intent']['id'].'/disputes', [
                'reason' => 'unauthorized',
            ])
            ->assertCreated()
            ->json();

        $frozenFeePolicy = data_get($dispute, 'evidence.fee_policy');
        $frozenFeeAmount = data_get($dispute, 'evidence.fee_decision.fee_amount');

        config(['payment_fees.default' => 'v2']);

        $reloaded = $this->withToken($alice['token'])
            ->getJson('/api/storefront/v1/settlement/disputes/'.$dispute['dispute']['id'])
            ->assertOk()
            ->json();

        $this->assertSame($frozenFeePolicy, data_get($reloaded, 'evidence.fee_policy'));
        $this->assertSame('payment-fees:v1', data_get($reloaded, 'evidence.fee_policy'));
        $this->assertSame($frozenFeeAmount, data_get($reloaded, 'evidence.fee_decision.fee_amount'));
        $this->assertSame('0.05', data_get($reloaded, 'evidence.fee_decision.fee_amount'));
    }

    #[Test]
    public function dispute_lifecycle_records_append_only_events(): void
    {
        $alice = $this->createUser('alice_lifecycle', 'sl1e_'.str_repeat('e', 39));
        $selim = $this->createUser('selim_lifecycle', 'sl1e_'.str_repeat('f', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_lifecycle',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $opened = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$payment['payment_intent']['id'].'/disputes', [
                'reason' => 'duplicate_payment',
            ])
            ->assertCreated()
            ->assertJsonPath('contract.name', PaymentDisputeService::CONTRACT_NAME)
            ->assertJsonPath('dispute.status', IdentityPaymentDispute::STATUS_OPENED)
            ->json();

        $disputeId = $opened['dispute']['id'];

        $requested = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/request-evidence')
            ->assertOk()
            ->assertJsonPath('dispute.status', IdentityPaymentDispute::STATUS_EVIDENCE_REQUESTED)
            ->json();

        $collected = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/collect-evidence')
            ->assertOk()
            ->assertJsonPath('dispute.status', IdentityPaymentDispute::STATUS_EVIDENCE_COLLECTED)
            ->json();

        $reviewed = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/review')
            ->assertOk()
            ->assertJsonPath('dispute.status', IdentityPaymentDispute::STATUS_REVIEWED)
            ->json();

        $events = collect($reviewed['lifecycle'])->pluck('event')->all();
        $this->assertSame([
            IdentityPaymentDispute::EVENT_OPENED,
            IdentityPaymentDispute::EVENT_EVIDENCE_REQUESTED,
            IdentityPaymentDispute::EVENT_EVIDENCE_COLLECTED,
            IdentityPaymentDispute::EVENT_REVIEWED,
        ], $events);

        $this->assertSame($opened['evidence'], $collected['evidence']);
        $this->assertSame($opened['evidence'], $reviewed['evidence']);
    }

    #[Test]
    public function evidence_viewer_matches_original_payment_context(): void
    {
        $alice = $this->createUser('alice_viewer', 'sl1e_'.str_repeat('1', 39));
        $selim = $this->createUser('selim_viewer', 'sl1e_'.str_repeat('2', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_viewer',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $paymentContext = app(PaymentDisputeEvidenceViewerService::class)
            ->paymentContextFromIntentResponse($payment);

        $dispute = $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$payment['payment_intent']['id'].'/disputes', [
                'reason' => 'unauthorized',
            ])
            ->assertCreated()
            ->json();

        $viewer = app(PaymentDisputeService::class)->formatOpsResponse(
            IdentityPaymentDispute::query()->where('dispute_uuid', $dispute['dispute']['id'])->firstOrFail(),
        );

        $this->assertTrue(
            app(PaymentDisputeEvidenceViewerService::class)->matchesPaymentContext(
                $paymentContext,
                (array) $viewer['evidence'],
            ),
        );

        config(['payment_fees.default' => 'v2', 'capability_policies.default' => 'v2']);

        $reloaded = app(PaymentDisputeService::class)->showForOps($dispute['dispute']['id']);
        $this->assertSame('payment-fees:v1', data_get($reloaded, 'evidence_viewer.fees.policy'));
    }

    #[Test]
    public function resolution_does_not_mutate_payment_intent_or_accounting(): void
    {
        $alice = $this->createUser('alice_resolve', 'sl1e_'.str_repeat('3', 39));
        $selim = $this->createUser('selim_resolve', 'sl1e_'.str_repeat('4', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_resolve',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $intentUuid = $payment['payment_intent']['id'];
        $intentSnapshot = IdentityPaymentIntent::query()->where('intent_uuid', $intentUuid)->firstOrFail()->toArray();
        $accountingSnapshot = IdentityPaymentAccountingEvent::query()->firstOrFail()->toArray();

        $resolved = $this->openAndResolveDisputeWithDecision($alice['token'], $intentUuid);

        $this->assertSame('approved', data_get($resolved, 'resolution.decision'));
        $this->assertTrue(data_get($resolved, 'resolution.creates_compensation_intent'));

        $reloadedIntent = IdentityPaymentIntent::query()->where('intent_uuid', $intentUuid)->firstOrFail()->toArray();
        $reloadedAccounting = IdentityPaymentAccountingEvent::query()
            ->where('identity_payment_intent_id', $reloadedIntent['id'])
            ->firstOrFail()
            ->toArray();

        $this->assertSame($intentSnapshot['status'], $reloadedIntent['status']);
        $this->assertSame($intentSnapshot['amount'], $reloadedIntent['amount']);
        $this->assertSame($accountingSnapshot['metadata'], $reloadedAccounting['metadata']);
        $this->assertNotNull(data_get($resolved, 'compensation_intent_id'));
    }

    #[Test]
    public function double_resolution_is_rejected(): void
    {
        $alice = $this->createUser('alice_double', 'sl1e_'.str_repeat('5', 39));
        $selim = $this->createUser('selim_double', 'sl1e_'.str_repeat('6', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_double',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $resolved = $this->openAndResolveDisputeWithDecision($alice['token'], $payment['payment_intent']['id']);
        $disputeId = $resolved['dispute']['id'];

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/resolve', [
                'decision' => 'rejected',
                'reason' => 'second_attempt',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['dispute']);
    }

    /**
     * @return array<string, mixed>
     */
    private function openAndResolveDisputeWithDecision(string $openerToken, string $intentUuid): array
    {
        $opened = $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$intentUuid.'/disputes', [
                'reason' => 'duplicate_payment',
            ])
            ->assertCreated()
            ->json();

        $disputeId = $opened['dispute']['id'];

        $this->withToken($openerToken)->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/request-evidence')->assertOk();
        $this->withToken($openerToken)->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/collect-evidence')->assertOk();
        $this->withToken($openerToken)->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/review')->assertOk();

        return $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/resolve', [
                'decision' => 'approved',
                'creates_compensation_intent' => true,
                'reason' => 'duplicate_payment',
                'resolved_by' => 'system',
            ])
            ->assertOk()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function openAndResolveDispute(
        string $openerToken,
        string $intentUuid,
        bool $executeCompensation = false,
    ): array {
        $opened = $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$intentUuid.'/disputes', [
                'reason' => 'unauthorized',
            ])
            ->assertCreated()
            ->json();

        $disputeId = $opened['dispute']['id'];

        $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/request-evidence')
            ->assertOk();
        $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/collect-evidence')
            ->assertOk();
        $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/review')
            ->assertOk();

        return $this->withToken($openerToken)
            ->postJson('/api/storefront/v1/settlement/disputes/'.$disputeId.'/resolve', [
                'outcome' => 'refund_approved',
                'reason' => 'duplicate_payment',
                'resolved_by' => 'system',
                'execute_compensation' => $executeCompensation,
            ])
            ->assertOk()
            ->json();
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
                ->assertCreated();
        }
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
