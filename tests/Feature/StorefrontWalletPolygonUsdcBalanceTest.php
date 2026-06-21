<?php

namespace Tests\Feature;

use App\Models\IdentityBinding;
use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\StorefrontTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StorefrontWalletPolygonUsdcBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_assets_include_live_usdc_balance_for_bound_polygon_wallet(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => true,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('d', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', $entityAddress)->firstOrFail();
        $walletAddress = '0xabcdefabcdefabcdefabcdefabcdefabcdefabcd';
        Http::fake(function ($request) {
            if ($request->url() !== 'https://polygon-rpc.test') {
                return null;
            }

            $body = json_decode($request->body(), true);
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
                'result' => '0x'.dechex(24_180_000),
            ]);
        });

        IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_original' => $walletAddress,
            'binding_value_normalized' => strtolower($walletAddress),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'metadata' => [
                'network_label' => 'Polygon',
                'protocol' => 'evm',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('network_wallets.0.network.key', 'polygon')
            ->assertJsonPath('network_wallets.0.address', strtolower($walletAddress))
            ->assertJsonPath('network_wallets.0.capabilities.next_action', 'VIEW_BOUND_WALLET')
            ->assertJsonPath('network_wallets.0.coins.2.symbol', 'USDC')
            ->assertJsonPath('network_wallets.0.coins.2.amount', '24.180000')
            ->assertJsonPath('network_wallets.0.coins.2.display_amount', '24.18 USDC')
            ->assertJsonPath('network_wallets.0.coins.2.status', 'live');
    }

    public function test_wallet_assets_mark_usdc_balance_unavailable_when_polygon_rpc_disabled(): void
    {
        $this->withCommerceCryptoRailsEnabled();

        config([
            'blockchain_networks.networks.polygon.rpc_url' => 'https://polygon-rpc.test',
            'blockchain_networks.networks.polygon.rpc_enabled' => false,
        ]);

        $entityAddress = 'sl1e_'.str_repeat('e', 39);
        User::factory()->create([
            'entity_l1_address' => $entityAddress,
        ]);
        $token = $this->vaultToken($entityAddress);
        $this->withToken($token)->getJson('/api/storefront/v1/wallet')->assertOk();

        $vault = VaultIdentity::query()->where('anchor_address', $entityAddress)->firstOrFail();
        $walletAddress = '0x1234567890123456789012345678901234567890';

        IdentityBinding::query()->create([
            'vault_id' => $vault->id,
            'binding_type' => IdentityBinding::TYPE_WALLET,
            'binding_key' => 'polygon',
            'binding_value_original' => $walletAddress,
            'binding_value_normalized' => strtolower($walletAddress),
            'verification_state' => IdentityBinding::STATE_VERIFIED,
            'verification_method' => IdentityBinding::METHOD_SIGNATURE,
            'metadata' => [
                'network_label' => 'Polygon',
                'protocol' => 'evm',
            ],
            'bound_at' => now(),
            'verified_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/storefront/v1/wallet/assets')
            ->assertOk()
            ->assertJsonPath('network_wallets.0.capabilities.next_action', 'NETWORK_RPC_REQUIRED')
            ->assertJsonPath('network_wallets.0.coins.2.symbol', 'USDC')
            ->assertJsonPath('network_wallets.0.coins.2.status', 'balance_unavailable');
    }

    private function vaultToken(string $entityAddress): string
    {
        return app(StorefrontTokenService::class)->issue([
            'entity_l1_address' => $entityAddress,
            'proof_token_hash' => hash('sha256', $entityAddress),
        ], ['storefront:read', 'storefront:vault'])['access_token'];
    }
}
