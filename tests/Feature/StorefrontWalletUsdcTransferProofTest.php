<?php

namespace Tests\Feature;

use App\Models\BindingProof;
use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Models\VaultSettlementProof;
use App\Models\VerificationEvent;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontWalletUsdcTransferProofTest extends TestCase
{
    use RefreshDatabase;

    private const USDC_CONTRACT = '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359';

    private const SENDER = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    private const RECIPIENT = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';

    public function test_usdc_transfer_proof_is_verified_and_recorded(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('9', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $vault = app(\App\Services\VaultIdentityService::class)->resolveForStorefront([
            'entity_l1_address' => $entityAddress,
        ], User::query()->where('entity_l1_address', $entityAddress)->firstOrFail());
        $this->seedVerifiedPolygonBinding($vault);

        $txHash = '0x'.str_repeat('a', 64);
        $this->fakeSuccessfulTransferReceipt($txHash, self::SENDER, self::RECIPIENT, '989680');

        $response = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => $txHash,
                'recipient' => self::RECIPIENT,
                'minimum_amount' => '10',
                'sender' => self::SENDER,
            ])
            ->assertOk()
            ->assertJsonPath('settlement_proof.proof_kind', VaultSettlementProof::KIND_USDC_TRANSFER)
            ->assertJsonPath('settlement_proof.rail', 'polygon')
            ->assertJsonPath('settlement_proof.status', VaultSettlementProof::STATUS_VERIFIED)
            ->assertJsonPath('settlement_proof.evidence.chain_id', 137)
            ->assertJsonPath('settlement_proof.evidence.token_contract', strtolower(self::USDC_CONTRACT))
            ->assertJsonPath('settlement_proof.evidence.transaction_hash', $txHash)
            ->assertJsonPath('settlement_proof.evidence.sender', self::SENDER)
            ->assertJsonPath('settlement_proof.evidence.recipient', self::RECIPIENT)
            ->assertJsonPath('settlement_proof.evidence.amount', '10000000')
            ->assertJsonPath('settlement_proof.evidence.block_number', 16)
            ->assertJsonPath('settlement_proof.transaction_hash', $txHash)
            ->assertJsonPath('proof.status', VaultSettlementProof::STATUS_VERIFIED)
            ->assertJsonPath('credit_decision.status', \App\Models\CreditDecision::STATUS_APPROVED)
            ->assertJsonPath('value_entry.value_entry.amount', '10');

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('vault_settlement_proofs', [
            'vault_id' => $vaultId,
            'proof_kind' => VaultSettlementProof::KIND_USDC_TRANSFER,
            'external_reference' => VaultSettlementProof::externalReferenceFor(
                VaultSettlementProof::KIND_USDC_TRANSFER,
                $txHash,
            ),
            'status' => VaultSettlementProof::STATUS_VERIFIED,
        ]);

        $this->assertDatabaseCount('vault_settlement_proofs', 1);
        $this->assertDatabaseCount('binding_proofs', 0);

        $this->assertDatabaseHas('verification_events', [
            'vault_id' => $vaultId,
            'proof_type' => VaultSettlementProof::KIND_USDC_TRANSFER,
            'binding_key' => 'polygon',
            'event_type' => VerificationEvent::TYPE_PROOF_VERIFIED,
        ]);

        $proofId = (int) $response->json('settlement_proof.id');
        $event = VerificationEvent::query()->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)->first();
        $this->assertSame($proofId, (int) $event?->vault_settlement_proof_id);
        $this->assertNull($event?->binding_proof_id);
        $this->assertSame($txHash, data_get($event?->payload, 'evidence.transaction_hash'));
    }

    public function test_usdc_transfer_proof_rejects_recipient_mismatch(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('8', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $txHash = '0x'.str_repeat('b', 64);
        $this->fakeSuccessfulTransferReceipt(
            $txHash,
            self::SENDER,
            '0x1111111111111111111111111111111111111111',
            '989680',
        );

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => $txHash,
                'recipient' => self::RECIPIENT,
                'minimum_amount' => '10',
                'sender' => self::SENDER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proof']);

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('vault_settlement_proofs', [
            'vault_id' => $vaultId,
            'status' => VaultSettlementProof::STATUS_FAILED,
        ]);
        $this->assertDatabaseCount('binding_proofs', 0);
        $this->assertDatabaseHas('verification_events', [
            'vault_id' => $vaultId,
            'event_type' => VerificationEvent::TYPE_PROOF_VERIFICATION_FAILED,
        ]);
        $this->assertSame('transfer_not_found', data_get(
            VerificationEvent::query()->first()?->payload,
            'error.code',
        ));
    }

    public function test_usdc_transfer_proof_rejects_amount_below_minimum(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('7', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        $txHash = '0x'.str_repeat('c', 64);
        $this->fakeSuccessfulTransferReceipt($txHash, self::SENDER, self::RECIPIENT, 'f4240');

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => $txHash,
                'recipient' => self::RECIPIENT,
                'minimum_amount' => '10',
                'sender' => self::SENDER,
            ])
            ->assertUnprocessable();

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('vault_settlement_proofs', [
            'vault_id' => $vaultId,
            'status' => VaultSettlementProof::STATUS_FAILED,
        ]);
        $this->assertDatabaseCount('binding_proofs', 0);
    }

    public function test_usdc_transfer_proof_rejects_failed_transaction(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('6', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);

        Http::fake([
            'https://polygon-rpc.test' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'status' => '0x0',
                    'blockNumber' => '0x10',
                    'logs' => [],
                ],
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', [
                'binding_key' => 'polygon',
                'transaction_hash' => '0x'.str_repeat('d', 64),
                'recipient' => self::RECIPIENT,
                'minimum_amount' => '1',
                'sender' => self::SENDER,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['proof']);
    }

    public function test_usdc_transfer_proof_rejects_duplicate_transaction_for_vault(): void
    {
        config([
            'blockchain_networks.crypto_rails_enabled' => true,
            'settlement_adapters.polygon.enabled' => true,
            'settlement_adapters.polygon.mode' => 'full',
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('5', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $txHash = '0x'.str_repeat('e', 64);

        $this->fakeSuccessfulTransferReceipt($txHash, self::SENDER, self::RECIPIENT, '989680');

        $payload = [
            'binding_key' => 'polygon',
            'transaction_hash' => $txHash,
            'recipient' => self::RECIPIENT,
            'minimum_amount' => '10',
            'sender' => self::SENDER,
        ];

        $this->withToken($token)->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', $payload)->assertOk();

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_hash']);

        $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_hash']);

        $this->assertSame(1, VaultSettlementProof::query()->count());
        $this->assertSame(0, BindingProof::query()->count());
        $this->assertSame(1, VerificationEvent::query()
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)
            ->count());
        $this->assertSame(0, VerificationEvent::query()
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFICATION_FAILED)
            ->count());
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
}
