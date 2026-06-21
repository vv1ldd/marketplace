<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\SettlementAuditEvent;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\SettlementAdapterRegistry;
use App\Services\SettlementAuditEventTypes;
use App\Services\StorefrontTokenService;
use App\Support\EvmPersonalSignVerifier;
use Elliptic\EC;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase D — adapter-boundary observation replay.
 *
 * observe → audit event → clear projection → observe again → same settlement state
 */
final class SettlementAdapterObservationReplayTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PRIVATE_KEY = 'ac0974bec39a17e36ba4a6b4d5bf038c971d058074a521d8f985e51f0e0b08161b63';

    private const TEST_WALLET_ADDRESS = '0x9926a054657433dc4181886c9877ba2c96001b0a';

    private const SIMULATED_USDC_MINOR = 24_180_000;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCommerceCryptoRailsEnabled();
        $this->withSettlementAdapterEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.replay.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        Http::fake(function ($request) {
            if (! str_contains($request->url(), 'polygon-rpc.replay.test')) {
                return null;
            }

            $body = json_decode($request->body(), true);
            $method = (string) ($body['method'] ?? '');

            if ($method === 'eth_chainId') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x89',
                ]);
            }

            $to = strtolower((string) ($body['params'][0]['to'] ?? ''));

            if ($to !== '0x3c499c542cef5e3811e1192ce70d8cc03d5c3359') {
                return Http::response([
                    'jsonrpc' => '2.0',
                    'id' => 1,
                    'result' => '0x0',
                ]);
            }

            return Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => '0x'.dechex(self::SIMULATED_USDC_MINOR),
            ]);
        });
    }

    #[Test]
    public function phase_d_observation_replay_reconstructs_settlement_state_after_projection_clear(): void
    {
        $entityAddress = 'sl1e_'.str_repeat('d', 39);
        User::factory()->create(['entity_l1_address' => $entityAddress]);
        $token = $this->vaultToken($entityAddress);
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', strtolower($entityAddress))->firstOrFail();
        $this->assertTrue($this->bindPolygonWallet($token));

        $binding = IdentityBinding::query()
            ->where('vault_id', $vault->id)
            ->where('binding_key', 'polygon')
            ->firstOrFail();

        $adapter = app(SettlementAdapterRegistry::class)->adapter('polygon');

        $firstObservation = $adapter->observeBalance($vault, $binding);
        $this->assertTrue($firstObservation['observed']);
        $this->assertSame('24.18 USDC', $this->usdcDisplayAmount($firstObservation));

        $auditEventsAfterFirstObserve = SettlementAuditEvent::query()
            ->where('event_type', SettlementAuditEventTypes::BALANCE_READ)
            ->count();
        $this->assertSame(1, $auditEventsAfterFirstObserve);

        Cache::flush();

        $secondObservation = $adapter->observeBalance($vault, $binding);
        $this->assertTrue($secondObservation['observed']);
        $this->assertSame('24.18 USDC', $this->usdcDisplayAmount($secondObservation));
        $this->assertSame(
            $this->usdcDisplayAmount($firstObservation),
            $this->usdcDisplayAmount($secondObservation),
            'Settlement state must replay identically after projection clear.',
        );

        $this->assertSame(2, SettlementAuditEvent::query()
            ->where('event_type', SettlementAuditEventTypes::BALANCE_READ)
            ->count());

        $postCacheToken = $this->vaultToken($entityAddress);
        $apiRead = $this->readUsdcBalance($postCacheToken);
        $this->assertSame('24.18 USDC', $apiRead);
    }

    /**
     * @param array<string, mixed> $observation
     */
    private function usdcDisplayAmount(array $observation): ?string
    {
        foreach ($observation['coins'] ?? [] as $coin) {
            if (($coin['symbol'] ?? '') === 'USDC') {
                return $coin['display_amount'] ?? null;
            }
        }

        return null;
    }

    private function bindPolygonWallet(string $token): bool
    {
        $challengeResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/challenge', [
                'binding_type' => 'wallet',
                'binding_key' => 'polygon',
                'binding_value' => self::TEST_WALLET_ADDRESS,
                'verification_method' => 'signature',
            ]);

        if ($challengeResponse->getStatusCode() !== 201) {
            return false;
        }

        $nonce = $challengeResponse->json('challenge.nonce');
        $message = $challengeResponse->json('challenge.message');
        $signature = $this->signPersonalMessage(self::TEST_PRIVATE_KEY, $message);

        $verifyResponse = $this->withToken($token)
            ->postJson('/api/storefront/v1/wallet/bindings/verify', [
                'nonce' => $nonce,
                'signature' => $signature,
            ]);

        return $verifyResponse->getStatusCode() === 200
            && $verifyResponse->json('binding.verification_state') === IdentityBinding::STATE_VERIFIED;
    }

    private function readUsdcBalance(string $token): ?string
    {
        $response = $this->withToken($token)->getJson('/api/storefront/v1/wallet/assets');

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        foreach ($response->json('network_wallets.0.coins') ?? [] as $coin) {
            if (($coin['symbol'] ?? '') === 'USDC') {
                return $coin['display_amount'] ?? null;
            }
        }

        return null;
    }

    private function signPersonalMessage(string $privateKeyHex, string $message): string
    {
        $hash = app(EvmPersonalSignVerifier::class)->hashPersonalMessage($message);
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKeyHex, 'hex');
        $signature = $key->sign($hash, 'hex', ['canonical' => true]);
        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + 27);

        return '0x'.$r.$s.$v;
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
