<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Services\Settlement\RecipientResolverService;
use App\Services\Settlement\SettlementInstrumentCapabilityService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StorefrontResolveRecipientTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withManagedWalletsEnabled();
        $this->withIdentityPaymentsEnabled();
        config([
            'managed_wallets.networks.ethereum' => true,
            'managed_wallets.networks.base' => true,
        ]);
    }

    #[Test]
    public function resolve_recipient_returns_capability_graph_not_address_lookup(): void
    {
        $alice = $this->createRecipientUser('alice', 'sl1e_'.str_repeat('a', 39));
        $this->provisionManagedWallets($alice['entity'], ['base', 'polygon']);

        $senderToken = $this->vaultToken('sl1e_'.str_repeat('b', 39));
        User::factory()->create(['entity_l1_address' => 'sl1e_'.str_repeat('b', 39)]);

        $payload = $this->withToken($senderToken)
            ->postJson('/api/storefront/v1/settlement/resolve-recipient', [
                'alias' => '@alice',
            ])
            ->assertOk()
            ->assertJsonPath('contract.name', RecipientResolverService::CONTRACT_NAME)
            ->assertJsonPath('contract.version', RecipientResolverService::CONTRACT_VERSION)
            ->assertJsonPath('alias', '@alice')
            ->assertJsonPath('identity_id', $alice['entity'])
            ->json();

        $this->assertArrayNotHasKey('address', $payload);
        $this->assertCount(2, $payload['receiving_capabilities']);
        $this->assertCount(2, $payload['ownership']['bindings']);
        $this->assertSame(
            $payload['ownership']['bindings'],
            $payload['receiving_capabilities'],
        );

        foreach ($payload['receiving_capabilities'] as $capability) {
            $this->assertArrayHasKey('binding_id', $capability);
            $this->assertArrayHasKey('network', $capability);
            $this->assertArrayHasKey('asset', $capability);
            $this->assertArrayHasKey('capability', $capability);
            $this->assertSame('receive', $capability['capability']);
            $this->assertSame('receive_enabled', $capability['status']);
            $this->assertArrayNotHasKey('address', $capability);
        }

        $this->assertCount(2, $payload['payment_routing_capabilities']);
        foreach ($payload['payment_routing_capabilities'] as $capability) {
            $this->assertSame('payment_routing', $capability['capability']);
            $this->assertSame(RecipientResolverService::STATUS_ROUTING_ENABLED, $capability['status']);
            $this->assertSame(
                SettlementInstrumentCapabilityService::CAPABILITY_POLICY_VERSION,
                $capability['capability_policy_version'],
            );
            $this->assertContains('USDC', $capability['assets']);
        }
    }

    #[Test]
    public function additive_instrument_drill_keeps_identity_and_prior_bindings_stable(): void
    {
        $alice = $this->createRecipientUser('alice_drill', 'sl1e_'.str_repeat('c', 39));
        $senderToken = $this->vaultToken('sl1e_'.str_repeat('d', 39));
        User::factory()->create(['entity_l1_address' => 'sl1e_'.str_repeat('d', 39)]);

        $this->provisionManagedWallets($alice['entity'], ['base', 'polygon']);

        $before = $this->resolveRecipient($senderToken, '@alice_drill');
        $this->assertCount(2, $before['receiving_capabilities']);

        $baseBindingId = $this->bindingIdForNetwork($before, 'base');
        $polygonBindingId = $this->bindingIdForNetwork($before, 'polygon');

        $this->provisionManagedWallets($alice['entity'], ['ethereum']);

        $after = $this->resolveRecipient($senderToken, '@alice_drill');

        $this->assertSame($before['identity_id'], $after['identity_id']);
        $this->assertSame($before['ownership']['vault_id'], $after['ownership']['vault_id']);
        $this->assertCount(3, $after['receiving_capabilities']);
        $this->assertSame($baseBindingId, $this->bindingIdForNetwork($after, 'base'));
        $this->assertSame($polygonBindingId, $this->bindingIdForNetwork($after, 'polygon'));
        $this->assertNotNull($this->bindingIdForNetwork($after, 'ethereum'));
    }

    #[Test]
    public function repeated_resolve_is_deterministic_for_subject(): void
    {
        $alice = $this->createRecipientUser('alice_stable', 'sl1e_'.str_repeat('e', 39));
        $senderToken = $this->vaultToken('sl1e_'.str_repeat('f', 39));
        User::factory()->create(['entity_l1_address' => 'sl1e_'.str_repeat('f', 39)]);

        $this->provisionManagedWallets($alice['entity'], ['base']);

        $first = $this->resolveRecipient($senderToken, '@alice_stable');
        $second = $this->resolveRecipient($senderToken, 'alice_stable');

        $this->assertSame($first['identity_id'], $second['identity_id']);
        $this->assertSame($first['receiving_capabilities'], $second['receiving_capabilities']);
    }

    #[Test]
    public function unknown_alias_returns_not_found(): void
    {
        $senderToken = $this->vaultToken('sl1e_'.str_repeat('9', 39));
        User::factory()->create(['entity_l1_address' => 'sl1e_'.str_repeat('9', 39)]);

        $this->withToken($senderToken)
            ->postJson('/api/storefront/v1/settlement/resolve-recipient', [
                'alias' => '@nobody_here',
            ])
            ->assertNotFound();
    }

    /**
     * @return array{entity: string, user: User}
     */
    private function createRecipientUser(string $username, string $entityAddress): array
    {
        $user = User::factory()->create([
            'entity_l1_address' => $entityAddress,
            'username' => $username,
            'username_key' => $username,
        ]);

        return ['entity' => $entityAddress, 'user' => $user];
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

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
