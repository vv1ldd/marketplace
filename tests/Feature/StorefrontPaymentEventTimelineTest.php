<?php

namespace Tests\Feature;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\IdentityPaymentIntent;
use App\Models\User;
use App\Services\Settlement\PaymentEventTimelineService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontPaymentEventTimelineTest extends TestCase
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
            'managed_wallets.networks.polygon' => true,
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
                    'transaction_hash' => '0xtimeline'.$this->calls,
                    'network' => $networkKey,
                ];
            }
        });
    }

    #[Test]
    public function timeline_projects_existing_facts_in_chronological_order_with_provenance(): void
    {
        $alice = $this->createUser('alice_timeline', 'sl1e_'.str_repeat('a', 39));
        $selim = $this->createUser('selim_timeline', 'sl1e_'.str_repeat('b', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_timeline',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $intentUuid = $payment['payment_intent']['id'];

        $payload = $this->withToken($selim['token'])
            ->getJson('/api/storefront/v1/settlement/payment-intents/'.$intentUuid.'/timeline')
            ->assertOk()
            ->assertJsonPath('contract.name', PaymentEventTimelineService::CONTRACT_NAME)
            ->assertJsonPath('contract.version', PaymentEventTimelineService::CONTRACT_VERSION)
            ->assertJsonPath('payment_intent_id', $intentUuid)
            ->json();

        $events = $payload['events'];
        $this->assertNotEmpty($events);

        $types = array_column($events, 'type');
        $this->assertContains('intent_created', $types);
        $this->assertContains('routing_decided', $types);
        $this->assertContains('limit_decided', $types);
        $this->assertContains('fee_quoted', $types);
        $this->assertContains('settlement_confirmed', $types);
        $this->assertContains('accounting_recorded', $types);

        foreach ($events as $event) {
            $this->assertArrayHasKey('type', $event);
            $this->assertArrayHasKey('occurred_at', $event);
            $this->assertArrayHasKey('source', $event);
            $this->assertNotEmpty($event['source']);
        }

        $occurredAt = array_map(
            static fn (string $value): int => \Illuminate\Support\Carbon::parse($value)->getTimestamp(),
            array_column($events, 'occurred_at'),
        );
        $sorted = $occurredAt;
        sort($sorted);
        $this->assertSame($sorted, $occurredAt);

        $routing = collect($events)->firstWhere('type', 'routing_decided');
        $this->assertSame('routing_decision', $routing['source']);
        $this->assertSame('polygon', $routing['evidence']['network'] ?? null);

        $accounting = collect($events)->firstWhere('type', 'accounting_recorded');
        $this->assertSame('accounting_event', $accounting['source']);
        $this->assertNotEmpty($accounting['evidence']['entries'] ?? []);
    }

    #[Test]
    public function dispute_events_append_after_payment_facts_without_mutating_intent_status(): void
    {
        $alice = $this->createUser('alice_tl_dispute', 'sl1e_'.str_repeat('c', 39));
        $selim = $this->createUser('selim_tl_dispute', 'sl1e_'.str_repeat('d', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_tl_dispute',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $intentUuid = $payment['payment_intent']['id'];

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents/'.$intentUuid.'/disputes', [
                'reason' => 'unauthorized',
            ])
            ->assertCreated();

        $payload = $this->withToken($alice['token'])
            ->getJson('/api/storefront/v1/settlement/payment-intents/'.$intentUuid.'/timeline')
            ->assertOk()
            ->json();

        $types = array_column($payload['events'], 'type');
        $this->assertContains('dispute_opened', $types);

        $intent = IdentityPaymentIntent::query()->where('intent_uuid', $intentUuid)->firstOrFail();
        $this->assertSame(IdentityPaymentIntent::STATUS_EXECUTED, $intent->status);

        $accountingIndex = array_search('accounting_recorded', $types, true);
        $disputeIndex = array_search('dispute_opened', $types, true);
        $this->assertNotFalse($accountingIndex);
        $this->assertNotFalse($disputeIndex);
        $this->assertLessThan($disputeIndex, $accountingIndex);
    }

    #[Test]
    public function non_participant_cannot_read_timeline(): void
    {
        $alice = $this->createUser('alice_tl_gate', 'sl1e_'.str_repeat('e', 39));
        $selim = $this->createUser('selim_tl_gate', 'sl1e_'.str_repeat('f', 39));
        $bob = $this->createUser('bob_tl_gate', 'sl1e_'.str_repeat('9', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $payment = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_tl_gate',
                'asset' => 'USDC',
                'amount' => '5',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $this->withToken($bob['token'])
            ->getJson('/api/storefront/v1/settlement/payment-intents/'.$payment['payment_intent']['id'].'/timeline')
            ->assertForbidden();
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
