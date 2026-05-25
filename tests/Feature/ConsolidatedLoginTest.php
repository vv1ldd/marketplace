<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\PasskeyAuthenticateController;

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
     * Test guest redirects from protected panels to the unified /login.
     */
    public function test_guest_redirects_to_unified_login()
    {
        // 1. Ops Panel
        $response = $this->get('/ops');
        $response->assertRedirect('/login');

        // 2. Partner Panel
        $response = $this->get('/partner');
        $response->assertRedirect('/login');

        // 3. Treasury Panel
        $response = $this->get('/treasury');
        $response->assertRedirect('/login');

        // 4. Tribunal Panel
        $response = $this->get('/tribunal');
        $response->assertRedirect('/login');

        // 5. Kernel Panel
        $response = $this->get('/kernel');
        $response->assertRedirect('/login');
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
     * Test custom Passkey controller redirects Manager to /ops.
     */
    public function test_passkey_redirects_manager_to_ops()
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
        $this->assertStringEndsWith('/ops', $response->headers->get('Location'));
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
     * Test custom Passkey controller redirects Treasurer to /treasury.
     */
    public function test_passkey_redirects_treasurer_to_treasury()
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
        $this->assertStringEndsWith('/treasury', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Auditor to /tribunal.
     */
    public function test_passkey_redirects_auditor_to_tribunal()
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
        $this->assertStringEndsWith('/tribunal', $response->headers->get('Location'));
    }

    /**
     * Test custom Passkey controller redirects Telemetry Monitor to /kernel.
     */
    public function test_passkey_redirects_telemetry_to_kernel()
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
        $this->assertStringEndsWith('/kernel', $response->headers->get('Location'));
    }
}
