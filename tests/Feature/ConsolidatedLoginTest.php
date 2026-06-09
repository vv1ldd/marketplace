<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\SovereignLedger;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\PasskeyAuthenticateController;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Models\Passkey;

class ConsolidatedLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.domain' => 'localhost']);
        config(['session.domain' => null]);
    }

    /**
     * Test guest redirects from protected panels and legacy panel URLs.
     */
    public function test_guest_redirects_to_unified_login()
    {
        // 1. Ops Panel
        $response = $this->get('/ops');
        $this->assertStringEndsWith('/login', $response->headers->get('Location'));

        // 2. Merchant Panel
        $response = $this->get('/merchant');
        $this->assertStringEndsWith('/login', $response->headers->get('Location'));

        // 2b. Legacy Partner Panel
        $response = $this->get('/partner');
        $response->assertRedirect('/merchant');

        // 3. Legacy Treasury Panel
        $response = $this->get('/treasury');
        $response->assertRedirect('/ops');

        // 4. Legacy Tribunal Panel
        $response = $this->get('/tribunal');
        $response->assertRedirect('/ops');

        // 5. Legacy Kernel Panel
        $response = $this->get('/kernel');
        $response->assertRedirect('/ops');
    }

    public function test_local_passkey_login_route_is_retired(): void
    {
        $this->postJson(route('passkeys.login'), [
            'start_authentication_response' => json_encode($this->passkeyAssertionForChallenge('missing-session')),
        ])
            ->assertStatus(410)
            ->assertJsonFragment(['error' => 'Local passkey login was retired. Use Simple Layer Identity instead.']);
    }

    public function test_local_passkey_authentication_options_route_is_retired(): void
    {
        $this->getJson(route('passkeys.authentication_options'))
            ->assertStatus(410)
            ->assertJsonFragment(['error' => 'Local passkey login was retired. Use Simple Layer Identity instead.']);
    }

    public function test_email_invite_accept_flow_is_retired(): void
    {
        $this->getJson(route('invite.accept', ['token' => 'INV-retired']))
            ->assertStatus(410)
            ->assertJsonFragment(['error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.']);

        $this->postJson(route('invite.accept.options', ['token' => 'INV-retired']))
            ->assertStatus(410)
            ->assertJsonFragment(['error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.']);

        $this->postJson(route('invite.accept.submit', ['token' => 'INV-retired']))
            ->assertStatus(410)
            ->assertJsonFragment(['error' => 'Email/password invites were retired. Invite a verified SL1E wallet identity instead.']);
    }

    /**
     * @deprecated Local passkey login routes are disabled by default. Keep this as an opt-in legacy regression test.
     */
    public function test_successful_passkey_login_records_auth_login_intent(): void
    {
        $this->markTestSkipped('Local passkey login routes are retired; SL1E identity handles auth intents now.');

        Role::firstOrCreate(['name' => User::ROLE_WALLET_HOLDER, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'auth-intent@example.test',
            'meta' => [
                'entity_l1_address' => 'sl1e_'.str_repeat('a', 39),
                'key_l1_address' => 'sl1_'.str_repeat('b', 40),
            ],
        ]);
        $user->assignRole(User::ROLE_WALLET_HOLDER);
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);
        FakeFindPasskeyForLoginIntent::$passkey = $passkey;

        config(['passkeys.actions.find_passkey' => FakeFindPasskeyForLoginIntent::class]);

        $challenge = 'auth-login-intent-challenge';
        Session::put('passkey-authentication-options', json_encode(['challenge' => $challenge]));

        $this->post(route('passkeys.login'), [
            'start_authentication_response' => json_encode($this->passkeyAssertionForChallenge($challenge)),
        ])->assertRedirect('/vault');

        $this->assertAuthenticatedAs($user);

        $event = SovereignLedger::query()
            ->where('event_type', 'AUTH_LOGIN_INTENT')
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id)
            ->firstOrFail();

        $this->assertSame('auth.login', data_get($event->payload, 'intent_type'));
        $this->assertSame('auth.session', data_get($event->payload, 'scope'));
        $this->assertSame(hash('sha256', $challenge), data_get($event->payload, 'challenge_hash'));
        $this->assertSame('/vault', data_get($event->payload, 'redirect_target'));
        $this->assertSame($event->fingerprint, session('auth_login_fingerprint'));
    }

    /**
     * Test custom Passkey controller redirects Sovereign Validator to /ops.
     */
    public function test_passkey_redirects_sovereign_validator_to_ops()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_SOVEREIGN_VALIDATOR, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Sovereign',
            'last_name' => 'Validator',
            'email' => 'admin@sovereign.l1',
            'meta' => ['entity_l1_address' => 'sl1e_'.str_repeat('a', 39)],
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/ops', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Protocol Operator without ops identity to /vault.
     */
    public function test_passkey_redirects_protocol_operator_without_ops_identity_to_vault()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_PROTOCOL_OPERATOR, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Ops',
            'last_name' => 'Manager',
            'email' => 'manager@sovereign.l1',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/vault', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Merchant Node to /partner.
     */
    public function test_passkey_redirects_merchant_node_to_partner()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_MERCHANT_NODE, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Merchant',
            'last_name' => 'Node',
            'email' => 'partner@sovereign.l1',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/merchant', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects wallet holders to /vault.
     */
    public function test_passkey_redirects_wallet_holder_to_vault()
    {
        Role::firstOrCreate(['name' => User::ROLE_WALLET_HOLDER, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'Customer',
            'email' => 'customer@meanly.test',
        ]);
        $user->assignRole(User::ROLE_WALLET_HOLDER);

        $this->actingAs($user);
        Session::put('passkeys.redirect', '/merchant');

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');

        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);

        $response = $method->invoke($controller, $request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/vault', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Liquidity Steward to /vault after panel consolidation.
     */
    public function test_passkey_redirects_liquidity_steward_to_vault_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_LIQUIDITY_STEWARD, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Sovereign',
            'last_name' => 'Treasurer',
            'email' => 'treasurer@sovereign.l1',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/vault', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Ledger Auditor to /vault after panel consolidation.
     */
    public function test_passkey_redirects_ledger_auditor_to_vault_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_LEDGER_AUDITOR, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Sovereign',
            'last_name' => 'Auditor',
            'email' => 'auditor@sovereign.l1',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/vault', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Signal Watcher to /vault after panel consolidation.
     */
    public function test_passkey_redirects_telemetry_to_cabinet_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => User::ROLE_SIGNAL_WATCHER, 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'System',
            'last_name' => 'Engineer',
            'email' => 'monitor@sovereign.l1',
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');
        
        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($controller, $request);
        
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/vault', $response->headers->get('Location'));
    }

    private function passkeyAssertionForChallenge(string $challenge): array
    {
        return [
            'rawId' => 'mocked-raw-id',
            'response' => [
                'clientDataJSON' => $this->base64UrlEncode(json_encode([
                    'type' => 'webauthn.get',
                    'challenge' => $challenge,
                    'origin' => 'https://meanly.test',
                ], JSON_UNESCAPED_SLASHES)),
                'authenticatorData' => $this->base64UrlEncode('mock-authenticator-data'),
                'signature' => $this->base64UrlEncode('mock-signature'),
                'userHandle' => $this->base64UrlEncode('mock-user'),
            ],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

class FakeFindPasskeyForLoginIntent extends FindPasskeyToAuthenticateAction
{
    public static ?Passkey $passkey = null;

    public function execute(string $publicKeyCredentialJson, string $passkeyOptionsJson): ?Passkey
    {
        return self::$passkey;
    }
}
