<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VaultIdentity;
use App\Services\MarketplaceIdentityResolver;
use App\Services\WalletBindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketplaceIdentityRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_authority_restores_partner_and_ops_access_after_entity_rotation(): void
    {
        $previousEntity = 'sl1e_'.str_repeat('a', 39);
        $nextEntity = 'sl1e_'.str_repeat('b', 39);

        $user = User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => $previousEntity,
        ]);

        Role::findOrCreate(User::ROLE_SOVEREIGN_VALIDATOR, 'web');
        Role::findOrCreate(User::ROLE_MERCHANT_NODE, 'web');
        $user->assignRole(User::ROLE_SOVEREIGN_VALIDATOR, User::ROLE_MERCHANT_NODE);

        $this->withSession([
            'simple_l1_identity' => [
                'entity_l1_address' => $nextEntity,
                'username' => 'selim_dev',
            ],
        ])->getJson(route('storefront.identity.navigation-authority'))
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('can_access_ops', true)
            ->assertJsonPath('can_access_partner', true)
            ->assertJsonPath('vault_label', '@selim_dev');

        $user->refresh();
        $this->assertSame($nextEntity, strtolower((string) $user->entity_l1_address));
    }

    public function test_connect_flow_reuses_existing_username_and_migrates_polygon_binding(): void
    {
        $previousEntity = 'sl1e_'.str_repeat('c', 39);
        $nextEntity = 'sl1e_'.str_repeat('d', 39);

        $user = User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => $previousEntity,
        ]);

        $vault = VaultIdentity::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $user->id,
            'anchor_address' => $previousEntity,
            'vault_kind' => VaultIdentity::KIND_PERSONAL,
        ]);

        app(WalletBindingService::class)->createVerifiedWalletBinding(
            $vault,
            'polygon',
            '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            \App\Models\IdentityBinding::METHOD_SIGNATURE,
        );

        config([
            'simple_l1.identity_provider_url' => 'https://meanly.test',
            'simple_l1.runtime_url' => 'https://meanly.test',
            'simple_l1.proof_introspection_path' => '/api/sl1e/proofs/introspect',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://meanly.test/api/sl1e/proofs/introspect' => \Illuminate\Support\Facades\Http::response([
                'success' => true,
                'active' => true,
                'proof_token' => 'proof-token-rotation',
                'proof' => [
                    'type' => 'sl1e.login.proof.v1',
                    'clientId' => config('simple_l1.client_id'),
                    'redirectUri' => route('meanly.simple_l1.callback').'?popup=1',
                    'state' => 'rotation-state',
                    'nonce' => 'rotation-nonce',
                    'mode' => 'login',
                    'entityAddress' => $nextEntity,
                    'keyAddress' => 'sl1_'.str_repeat('e', 40),
                    'username' => 'selim_dev',
                    'expiresAt' => now()->addMinutes(5)->toIso8601String(),
                ],
                'identity' => [
                    'username' => 'selim_dev',
                ],
            ]),
        ]);

        $this->withSession([
            'simple_l1_connect.state' => 'rotation-state',
            'simple_l1_connect.nonce' => 'rotation-nonce',
            'simple_l1_connect.client_id' => config('simple_l1.client_id'),
            'simple_l1_connect.redirect_uri' => route('meanly.simple_l1.callback').'?popup=1',
            'simple_l1_connect.mode' => 'login',
            'simple_l1_connect.flow' => 'connect',
            'simple_l1_connect.return_to' => '/vault',
            'simple_l1_connect.popup' => true,
        ])->get('/simple-l1/callback?popup=1&mode=login&state=rotation-state&proof_token=proof-token-rotation')
            ->assertOk();

        $this->assertSame(1, User::query()->where('username_key', 'selim_dev')->count());
        $user->refresh();
        $this->assertSame($previousEntity, strtolower((string) $user->entity_l1_address));
        $this->assertAuthenticatedAs($user);

        $migratedVault = VaultIdentity::query()->where('anchor_address', $previousEntity)->first();
        $this->assertNotNull($migratedVault);
        $this->assertSame(
            '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            strtolower((string) $migratedVault->bindings()->where('binding_key', 'polygon')->value('binding_value_normalized')),
        );

        $this->assertDatabaseHas('simple_l1_identity_keys', [
            'user_id' => $user->id,
            'entity_l1_address' => $previousEntity,
            'key_l1_address' => 'sl1_'.str_repeat('e', 40),
        ]);
    }

    public function test_resolver_migrates_vault_anchor_when_username_matches_rotated_entity(): void
    {
        $previousEntity = 'sl1e_'.str_repeat('f', 39);
        $nextEntity = 'sl1e_'.str_repeat('9', 39);

        $user = User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => $previousEntity,
        ]);

        $vault = VaultIdentity::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'owner_user_id' => $user->id,
            'anchor_address' => $previousEntity,
            'vault_kind' => VaultIdentity::KIND_PERSONAL,
        ]);

        app(WalletBindingService::class)->createVerifiedWalletBinding(
            $vault,
            'polygon',
            '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            \App\Models\IdentityBinding::METHOD_SIGNATURE,
        );

        $resolved = app(MarketplaceIdentityResolver::class)->resolveFromIdentity([
            'entity_l1_address' => $nextEntity,
            'username' => 'selim_dev',
        ]);

        $this->assertInstanceOf(User::class, $resolved);
        $this->assertSame($nextEntity, strtolower((string) $resolved->entity_l1_address));
        $this->assertSame(
            '0xdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef',
            strtolower((string) VaultIdentity::query()->where('anchor_address', $nextEntity)->first()
                ?->bindings()->where('binding_key', 'polygon')->value('binding_value_normalized')),
        );
    }

    public function test_resolver_matches_display_alias_when_username_missing_from_identity(): void
    {
        $previousEntity = 'sl1e_'.str_repeat('a', 39);
        $nextEntity = 'sl1e_'.str_repeat('b', 39);

        User::factory()->create([
            'username' => 'selim_dev',
            'username_key' => 'selim_dev',
            'entity_l1_address' => $previousEntity,
        ]);

        $resolved = app(MarketplaceIdentityResolver::class)->resolveFromIdentity([
            'entity_l1_address' => $nextEntity,
            'display_alias' => '@selim_dev',
        ]);

        $this->assertInstanceOf(User::class, $resolved);
        $this->assertSame('selim_dev', $resolved->username);
        $this->assertSame($nextEntity, strtolower((string) $resolved->entity_l1_address));
    }
}
