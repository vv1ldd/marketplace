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
        $response->assertRedirect('/login');

        // 2. Partner Panel
        $response = $this->get('/partner');
        $response->assertRedirect('/login');

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

    public function test_passkey_login_without_saved_options_returns_friendly_error(): void
    {
        $response = $this
            ->from('/login')
            ->post(route('passkeys.login'), [
                'start_authentication_response' => json_encode($this->passkeyAssertionForChallenge('missing-session')),
            ]);

        $response->assertRedirect('/login');
        $response->assertSessionHas('authenticatePasskey::message', 'Контекст входа устарел. Нажмите «Войти» еще раз и подтвердите Passkey.');
    }

    public function test_passkey_authentication_options_are_cached_by_challenge(): void
    {
        $options = $this->getJson(route('passkeys.authentication_options'))
            ->assertOk()
            ->assertJsonStructure(['challenge'])
            ->json();

        $this->assertIsString(Session::get('passkey-authentication-options'));
        $this->assertIsString(Cache::get('passkeys:authentication-options:'.sha1($options['challenge'])));
    }

    public function test_successful_passkey_login_records_auth_login_intent(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'email' => 'auth-intent@example.test',
            'meta' => [
                'entity_l1_address' => 'sl1e_'.str_repeat('a', 39),
                'key_l1_address' => 'sl1_'.str_repeat('b', 40),
            ],
        ]);
        $user->assignRole('customer');
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);
        FakeFindPasskeyForLoginIntent::$passkey = $passkey;

        config(['passkeys.actions.find_passkey' => FakeFindPasskeyForLoginIntent::class]);

        $challenge = 'auth-login-intent-challenge';
        Session::put('passkey-authentication-options', json_encode(['challenge' => $challenge]));

        $this->post(route('passkeys.login'), [
            'start_authentication_response' => json_encode($this->passkeyAssertionForChallenge($challenge)),
        ])->assertRedirect('/cabinet');

        $this->assertAuthenticatedAs($user);

        $event = SovereignLedger::query()
            ->where('event_type', 'AUTH_LOGIN_INTENT')
            ->where('entity_type', User::class)
            ->where('entity_id', $user->id)
            ->firstOrFail();

        $this->assertSame('auth.login', data_get($event->payload, 'intent_type'));
        $this->assertSame('auth.session', data_get($event->payload, 'scope'));
        $this->assertSame(hash('sha256', $challenge), data_get($event->payload, 'challenge_hash'));
        $this->assertSame('/cabinet', data_get($event->payload, 'redirect_target'));
        $this->assertSame($event->fingerprint, session('auth_login_fingerprint'));
    }

    /**
     * Test custom Passkey controller redirects Super Admin to /ops.
     */
    public function test_passkey_redirects_super_admin_to_ops()
    {
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
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
     * Test custom Passkey controller redirects Manager without ops identity to /cabinet.
     */
    public function test_passkey_redirects_manager_without_ops_identity_to_cabinet()
    {
        $role = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
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
        $this->assertStringEndsWith('/cabinet', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Partner to /partner.
     */
    public function test_passkey_redirects_partner_to_partner()
    {
        $role = Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'B2B',
            'last_name' => 'Partner',
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
        $this->assertStringEndsWith('/partner', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects regular customers to /cabinet.
     */
    public function test_passkey_redirects_customer_to_cabinet()
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'Customer',
            'email' => 'customer@meanly.test',
        ]);
        $user->assignRole('customer');

        $this->actingAs($user);
        Session::put('passkeys.redirect', '/partner');

        $controller = new PasskeyAuthenticateController();
        $request = Request::create('/passkeys/authenticate', 'POST');

        $reflection = new \ReflectionClass(PasskeyAuthenticateController::class);
        $method = $reflection->getMethod('validPasskeyResponse');
        $method->setAccessible(true);

        $response = $method->invoke($controller, $request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/cabinet', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Treasurer to /cabinet after panel consolidation.
     */
    public function test_passkey_redirects_treasurer_to_cabinet_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => 'treasurer', 'guard_name' => 'web']);
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
        $this->assertStringEndsWith('/cabinet', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Auditor to /cabinet after panel consolidation.
     */
    public function test_passkey_redirects_auditor_to_cabinet_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
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
        $this->assertStringEndsWith('/cabinet', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Telemetry Monitor to /cabinet after panel consolidation.
     */
    public function test_passkey_redirects_telemetry_to_cabinet_after_panel_consolidation()
    {
        $role = Role::firstOrCreate(['name' => 'telemetry_monitor', 'guard_name' => 'web']);
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
        $this->assertStringEndsWith('/cabinet', $response->headers->get('Location'));
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
