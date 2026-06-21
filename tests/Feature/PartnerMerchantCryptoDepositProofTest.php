<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\MerchantDepositIntent;
use App\Models\User;
use App\Services\MerchantSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PartnerMerchantCryptoDepositProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);
        $this->withCommerceCryptoRailsEnabled();
    }

    public function test_partner_can_submit_crypto_deposit_proof_for_own_intent(): void
    {
        [$user, $entity] = $this->merchantEntity();
        $user->assignRole(User::ROLE_MERCHANT_NODE);

        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 180.00,
            options: ['idempotency_key' => 'partner-crypto-proof'],
        );

        $depositAddress = (string) data_get($intent->provider_payload, 'deposit_address');
        $txHash = '0x'.str_repeat('e', 64);

        $response = $this->postPartnerWorkspaceJson(
            user: $user,
            path: '/api/partner/workspace/finance/deposit-intents/'.$intent->id.'/crypto-proof',
            payload: [
                'tx_hash' => $txHash,
                'asset' => 'USDT',
            ],
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('proof.external_reference', $txHash)
            ->assertJsonPath('proof.source', 'evm_deposit_proof')
            ->assertJsonPath('intent.crypto_deposit.deposit_address', $depositAddress)
            ->assertJsonPath('intent.status', MerchantDepositIntent::STATUS_CREDITED);

        $this->assertSame(180.00, (float) $entity->refresh()->available_balance);
    }

    public function test_partner_cannot_submit_crypto_proof_for_foreign_intent(): void
    {
        [$owner] = $this->merchantEntity();
        $owner->assignRole(User::ROLE_MERCHANT_NODE);

        [, $foreignEntity] = $this->merchantEntity(name: 'Foreign Merchant');
        $intent = app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $foreignEntity,
            createdBy: $owner,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 90.00,
            options: ['idempotency_key' => 'foreign-crypto-proof'],
        );

        $this->postPartnerWorkspaceJson(
            user: $owner,
            path: '/api/partner/workspace/finance/deposit-intents/'.$intent->id.'/crypto-proof',
            payload: [
                'tx_hash' => '0x'.str_repeat('f', 64),
                'asset' => 'USDC',
            ],
        )
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postPartnerWorkspaceJson(User $user, string $path, array $payload)
    {
        return $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \App\Http\Middleware\SovereignPlaneGuard::class,
        ])
            ->withServerVariables(['HTTP_HOST' => (string) config('app.domain', 'meanly.one')])
            ->actingAs($user)
            ->postJson($path, $payload);
    }

    /**
     * @return array{0: User, 1: LegalEntity}
     */
    private function merchantEntity(string $name = 'Partner Crypto Merchant', float $available = 0.00): array
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
        Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        return [$user, $entity];
    }
}
