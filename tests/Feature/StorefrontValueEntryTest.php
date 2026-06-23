<?php

namespace Tests\Feature;

use App\Models\CreditDecision;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;
use App\Services\StorefrontTokenService;
use App\Services\VaultIdentityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontValueEntryTest extends TestCase
{
    use RefreshDatabase;

    private const USDC_CONTRACT = '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359';

    private const SENDER = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    private const RECIPIENT = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';

    public function test_value_entries_list_returns_verified_deposits_with_credit_decisions(): void
    {
        $this->configurePolygonProofRail();

        $entityAddress = 'sl1e_'.str_repeat('7', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $vault = app(VaultIdentityService::class)->resolveForStorefront([
            'entity_l1_address' => $entityAddress,
        ], User::query()->where('entity_l1_address', $entityAddress)->firstOrFail());

        $this->seedVerifiedPolygonBinding($vault);

        $txHash = '0x'.str_repeat('b', 64);
        $this->fakeSuccessfulTransferReceipt($txHash, self::SENDER, self::RECIPIENT, '989680');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => $txHash,
                'recipient' => self::RECIPIENT,
                'minimum_amount' => '10',
                'sender' => self::SENDER,
            ])
            ->assertOk()
            ->assertJsonPath('credit_decision.status', CreditDecision::STATUS_APPROVED)
            ->assertJsonPath('value_entry.activity_kind', 'value_entry')
            ->assertJsonPath('value_entry.value_entry.amount', '10');

        $response = $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/value-entries')
            ->assertOk()
            ->assertJsonPath('contract.name', 'value-entry-list')
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.activity_kind', 'value_entry')
            ->assertJsonPath('items.0.activity_direction', 'incoming')
            ->assertJsonPath('items.0.value_entry.amount', '10')
            ->assertJsonPath('items.0.value_entry.credit_approved', true)
            ->assertJsonPath('items.0.value_entry.transaction_hash', $txHash);

        $this->assertSame('Polygon', $response->json('items.0.value_entry.network_label'));
    }

    private function configurePolygonProofRail(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);
    }

    private function seedVerifiedPolygonBinding(VaultIdentity $vault): IdentityBinding
    {
        return IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_original' => self::RECIPIENT,
            'binding_value_normalized' => strtolower(self::RECIPIENT),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'bound_at' => now(),
            'verified_at' => now(),
        ]);
    }

    private function fakeSuccessfulTransferReceipt(
        string $txHash,
        string $sender,
        string $recipient,
        string $amountHex,
    ): void {
        Http::fake([
            'https://polygon-rpc.test' => Http::sequence()
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => [
                        'status' => '0x1',
                        'blockNumber' => '0x10',
                        'logs' => [
                            $this->erc20TransferLog(self::USDC_CONTRACT, $sender, $recipient, $amountHex),
                        ],
                    ],
                ])
                ->push([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x89',
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function erc20TransferLog(
        string $tokenContract,
        string $from,
        string $to,
        string $amountHex,
    ): array {
        return [
            'address' => $tokenContract,
            'topics' => [
                '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                '0x000000000000000000000000'.substr(strtolower($from), 2),
                '0x000000000000000000000000'.substr(strtolower($to), 2),
            ],
            'data' => '0x'.str_pad(strtolower(ltrim($amountHex, '0x')), 64, '0', STR_PAD_LEFT),
        ];
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
