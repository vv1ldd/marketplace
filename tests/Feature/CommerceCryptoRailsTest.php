<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\MerchantDepositIntent;
use App\Models\User;
use App\Services\MerchantSettlementService;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceCryptoRailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_crypto_rails_are_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('blockchain_networks.crypto_rails_enabled'));
    }

    public function test_wallet_binding_rejects_polygon_when_crypto_rails_disabled(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('8', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['binding_key']);
    }

    public function test_merchant_crypto_deposit_intent_is_rejected_when_crypto_rails_disabled(): void
    {
        [$user, $entity] = $this->merchantPair();

        $this->expectException(ValidationException::class);

        app(MerchantSettlementService::class)->issueIntent(
            legalEntity: $entity,
            createdBy: $user,
            rail: MerchantDepositIntent::RAIL_CRYPTO_USDT_USDC,
            amount: 100.00,
            options: ['idempotency_key' => 'disabled-crypto-rail'],
        );
    }

    /**
     * @return array{0: User, 1: LegalEntity}
     */
    private function merchantPair(): array
    {
        $user = User::factory()->create();
        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Commerce Crypto Test '.substr(hash('sha256', uniqid('', true)), 0, 6),
            'short_name' => 'Commerce Crypto Test',
            'inn' => (string) random_int(1000000000, 9999999999),
            'status' => 'active',
            'is_active' => true,
            'balance' => 0,
            'available_balance' => 0,
            'reserved_balance' => 0,
            'currency' => 'RUB',
            'tariff_type' => 'privileged',
            'markup_percent' => 0,
        ]);

        return [$user, $entity];
    }
}
