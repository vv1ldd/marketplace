<?php

namespace Tests\Feature;

use App\Contracts\IdentityPaymentExecutor;
use App\Models\User;
use App\Services\Settlement\IdentityStatementService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontIdentityStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
        $this->withIdentityPaymentsEnabled(execute: true);
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
                    'transaction_hash' => '0xstatement'.$this->calls,
                    'network' => $networkKey,
                ];
            }
        });
    }

    #[Test]
    public function statement_derives_from_accounting_history_only_not_payment_intents(): void
    {
        $alice = $this->createUser('alice_stmt', 'sl1e_'.str_repeat('a', 39));
        $bob = $this->createUser('bob_stmt', 'sl1e_'.str_repeat('b', 39));
        $selim = $this->createUser('selim_stmt', 'sl1e_'.str_repeat('c', 39));

        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($bob['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $paymentA = $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_stmt',
                'asset' => 'USDC',
                'amount' => '10',
                'execute' => true,
            ])
            ->assertCreated()
            ->json();

        $this->withToken($bob['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@selim_stmt',
                'asset' => 'USDC',
                'amount' => '20',
                'execute' => true,
            ])
            ->assertCreated();

        $this->withToken($alice['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'reversal_of_intent_id' => $paymentA['payment_intent']['id'],
                'reversal_reason' => 'refund',
                'execute' => true,
            ])
            ->assertCreated();

        $from = now()->startOfMonth()->toDateString();
        $to = now()->endOfMonth()->toDateString();

        $payload = $this->withToken($selim['token'])
            ->getJson('/api/storefront/v1/settlement/statement?from='.$from.'&to='.$to.'&asset=USDC')
            ->assertOk()
            ->assertJsonPath('contract.name', IdentityStatementService::CONTRACT_NAME)
            ->assertJsonPath('contract.version', IdentityStatementService::CONTRACT_VERSION)
            ->assertJsonPath('statement_version', IdentityStatementService::STATEMENT_VERSION)
            ->assertJsonPath('derivation', 'accounting_history_only')
            ->assertJsonPath('identity_id', $selim['entity'])
            ->json();

        $this->assertSame('0', $payload['opening_balance']);
        $this->assertSame('+19.95', $payload['closing_balance']);
        $this->assertSame('-10', $payload['totals']['outbound']);
        $this->assertSame('+20', $payload['totals']['inbound']);
        $this->assertSame('+10', $payload['totals']['compensations']);
        $this->assertSame('-0.05', $payload['totals']['fees']);
        $this->assertSame('+19.95', $payload['totals']['net_change']);

        $grossInbound = bcadd(
            ltrim((string) $payload['totals']['inbound'], '+'),
            ltrim((string) $payload['totals']['compensations'], '+'),
            2,
        );
        $this->assertSame('30.00', number_format((float) $grossInbound, 2, '.', ''));

        foreach ($payload['lines'] as $line) {
            $this->assertArrayHasKey('line_id', $line);
            $this->assertStringStartsWith('identity-statement:v1:', $line['line_id']);
            $this->assertTrue($line['explainable']);
            $this->assertArrayHasKey('drilldown_available', $line);
            $this->assertArrayHasKey('type', $line);
            $this->assertArrayHasKey('signed_amount', $line);
            $this->assertSame('accounting_event', $line['provenance']['source'] ?? null);
            $this->assertArrayHasKey('accounting_event_id', $line['provenance']);
            if ($line['drilldown_available']) {
                $this->assertArrayHasKey('payment_intent_id', $line['provenance']);
            }
        }

        $this->assertCount(4, $payload['lines']);
        $this->assertSame(
            1,
            collect($payload['lines'])->where('type', 'outbound_payment')->count(),
        );
        $this->assertSame(
            1,
            collect($payload['lines'])->where('type', 'inbound_payment')->count(),
        );
        $this->assertSame(
            1,
            collect($payload['lines'])->where('type', 'compensation')->count(),
        );
        $this->assertSame(
            1,
            collect($payload['lines'])->where('type', 'fee')->count(),
        );
    }

    #[Test]
    public function statement_line_provenance_points_to_accounting_not_settlement(): void
    {
        $alice = $this->createUser('alice_stmt_prov', 'sl1e_'.str_repeat('d', 39));
        $selim = $this->createUser('selim_stmt_prov', 'sl1e_'.str_repeat('e', 39));
        $this->provisionManagedWallets($alice['entity'], ['polygon']);
        $this->provisionManagedWallets($selim['entity'], ['polygon']);

        $this->withToken($selim['token'])
            ->postJson('/api/storefront/v1/settlement/payment-intents', [
                'to_alias' => '@alice_stmt_prov',
                'asset' => 'USDC',
                'amount' => '5',
                'execute' => true,
            ])
            ->assertCreated();

        $payload = $this->withToken($selim['token'])
            ->getJson('/api/storefront/v1/settlement/statement?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
            ->assertOk()
            ->json();

        $line = $payload['lines'][0];
        $this->assertArrayNotHasKey('settlement_reference', $line['provenance'] ?? []);
        $this->assertArrayNotHasKey('tx_reference', $line['provenance'] ?? []);
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
