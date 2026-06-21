<?php

namespace Tests\Integration;

use App\Models\User;
use App\Models\VaultSettlementProof;
use App\Models\VerificationEvent;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Live Polygon USDC transfer proof validation.
 *
 * Enable with:
 *   POLYGON_RPC_ENABLED=true
 *   POLYGON_RPC_URL=https://polygon-mainnet.g.alchemy.com/v2/...
 *   POLYGON_PROOF_E2E_ENABLED=true
 *   POLYGON_PROOF_E2E_TX_HASH=0x...
 *   POLYGON_PROOF_E2E_RECIPIENT=0x...
 *   POLYGON_PROOF_E2E_MINIMUM_AMOUNT=0.01
 *   POLYGON_PROOF_E2E_SENDER=0x...            (optional)
 *   POLYGON_PROOF_E2E_ENTITY_ADDRESS=sl1e_... (optional; auto-generated if omitted)
 *
 * Run:
 *   php artisan test tests/Integration/PolygonUsdcTransferProofLiveTest.php
 */
#[Group('live-polygon')]
class PolygonUsdcTransferProofLiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('POLYGON_PROOF_E2E_ENABLED', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set POLYGON_PROOF_E2E_ENABLED=true to run live Polygon proof validation.');
        }

        foreach (['POLYGON_RPC_URL', 'POLYGON_PROOF_E2E_TX_HASH', 'POLYGON_PROOF_E2E_RECIPIENT', 'POLYGON_PROOF_E2E_MINIMUM_AMOUNT'] as $key) {
            if (trim((string) env($key, '')) === '') {
                $this->markTestSkipped("Missing {$key} for live Polygon proof validation.");
            }
        }

        config([
            'blockchain_networks.networks.polygon.rpc_url' => env('POLYGON_RPC_URL'),
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);
    }

    public function test_live_polygon_usdc_transfer_proof_end_to_end(): void
    {
        $entityAddress = trim((string) env('POLYGON_PROOF_E2E_ENTITY_ADDRESS', ''));
        if ($entityAddress === '') {
            $entityAddress = 'sl1e_'.str_repeat('4', 39);
        }

        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];

        $txHash = strtolower(trim((string) env('POLYGON_PROOF_E2E_TX_HASH')));
        $payload = [
            'binding_key' => 'polygon',
            'transaction_hash' => $txHash,
            'recipient' => strtolower(trim((string) env('POLYGON_PROOF_E2E_RECIPIENT'))),
            'minimum_amount' => (string) env('POLYGON_PROOF_E2E_MINIMUM_AMOUNT'),
        ];

        $sender = trim((string) env('POLYGON_PROOF_E2E_SENDER', ''));
        if ($sender !== '') {
            $payload['sender'] = strtolower($sender);
        }

        $response = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/proofs/usdc-transfer', $payload)
            ->assertOk()
            ->assertJsonPath('settlement_proof.proof_kind', VaultSettlementProof::KIND_USDC_TRANSFER)
            ->assertJsonPath('settlement_proof.status', VaultSettlementProof::STATUS_VERIFIED)
            ->assertJsonPath('settlement_proof.evidence.transaction_hash', $txHash);

        $vaultId = VaultIdentity::query()->where('anchor_address', $entityAddress)->value('id');

        $this->assertDatabaseHas('vault_settlement_proofs', [
            'vault_id' => $vaultId,
            'external_reference' => VaultSettlementProof::externalReferenceFor(VaultSettlementProof::KIND_USDC_TRANSFER, $txHash),
            'status' => VaultSettlementProof::STATUS_VERIFIED,
        ]);

        $this->assertDatabaseHas('verification_events', [
            'vault_id' => $vaultId,
            'event_type' => VerificationEvent::TYPE_PROOF_VERIFIED,
        ]);

        $proofId = (int) $response->json('settlement_proof.id');
        $event = VerificationEvent::query()
            ->where('event_type', VerificationEvent::TYPE_PROOF_VERIFIED)
            ->where('vault_settlement_proof_id', $proofId)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($txHash, data_get($event->payload, 'evidence.transaction_hash'));
    }
}
