<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\LegalEntity;
use App\Models\Provider;
use App\Models\SovereignBalanceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Livewire\Livewire;
use App\Filament\Resources\B2B\RelationManagers\SovereignBalanceRequestsRelationManager;

class SovereignBalanceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected LegalEntity $legalEntity;
    protected Provider $provider;
    protected \Spatie\LaravelPasskeys\Models\Passkey $passkey;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.domain' => 'localhost']);
        config(['session.domain' => null]);

        // 1. Create B2B Partner Role and User
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
        
        $this->user = User::factory()->create([
            'first_name' => 'Sovereign',
            'last_name' => 'Partner',
            'email' => 'partner@sovereign.l1',
        ]);
        $this->user->assignRole($role);

        // Seed a dummy passkey to pass EnsureUserHasPasskey middleware check
        $this->passkey = \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $this->user->id,
        ]);

        // 2. Create Legal Entity with balances
        $this->legalEntity = LegalEntity::create([
            'user_id' => $this->user->id,
            'name' => 'Sovereign Consortium Corp',
            'available_balance' => 50000.00,
            'balance' => 50000.00,
            'currency' => 'RUB',
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'bank_account' => '12345678901234567890',
            'bank_correspondent_account' => '12345678901234567890',
            'bank_name' => 'Sovereign Bank',
            'bank_bik' => '123456789',
            'legal_address' => 'Sovereign Street 1',
            'postal_address' => 'Sovereign Street 1',
            'director_name' => 'Director Name',
        ]);
        $this->user->managedLegalEntities()->attach($this->legalEntity->id, ['role' => 'owner']);

        // 3. Create wildflow Provider to prevent service construction errors
        $this->provider = Provider::updateOrCreate(
            ['type' => 'wildflow'],
            [
                'name' => 'Wildflow Provider',
                'is_active' => true,
                'credentials' => [
                    'base_url' => 'http://api.wildflow.test/api/v1/',
                    'api_key' => 'mocked-api-key',
                    'client_id' => 'mocked-client-id',
                    'financial_secret' => 'mocked-financial-secret',
                ],
            ]
        );
    }

    /**
     * Test options generation endpoint.
     */
    public function test_sovereign_request_options_endpoint()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.finance.sovereign_request.options'));

        $response->assertStatus(200)
            ->assertJsonStructure(['challenge', 'rpId']);

        $this->assertNotNull(session('sovereign_request_signing_options'));
    }

    /**
     * Test successful creation of sovereign request with signature.
     */
    public function test_create_sovereign_request_success()
    {
        // Mock FindPasskeyToAuthenticateAction to return seeded passkey
        $this->mock(FindPasskeyToAuthenticateAction::class, function ($mock) {
            $mock->shouldReceive('execute')->andReturn($this->passkey);
        });

        session(['sovereign_request_signing_options' => json_encode(['challenge' => 'dummy', 'rpId' => 'localhost'])]);

        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.finance.sovereign_request.create'), [
                'type' => 'top_up',
                'amount' => 15000.00,
                'comment' => 'Hardware signed replenishment contract',
                'assertion' => ['rawId' => 'mocked-raw-id']
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'request']);

        $publicKey = $this->passkey->data->credentialPublicKey ?? '';
        $expectedL1Address = 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);

        $this->assertDatabaseHas('sovereign_balance_requests', [
            'legal_entity_id' => $this->legalEntity->id,
            'type' => 'top_up',
            'amount' => 15000.00,
            'status' => 'pending',
            'l1_address' => $expectedL1Address,
            'comment' => 'Hardware signed replenishment contract',
        ]);
    }

    /**
     * Test creation fails if signing options context is missing.
     */
    public function test_create_sovereign_request_fails_if_context_missing()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('partner.dashboard.finance.sovereign_request.create'), [
                'type' => 'top_up',
                'amount' => 15000.00,
                'comment' => 'Context missing',
                'assertion' => ['rawId' => 'mocked-raw-id']
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Контекст подписи утерян. Пожалуйста, обновите страницу.');
    }

    /**
     * Test Filament Approve & Execute action.
     */
    public function test_filament_approve_and_execute_sovereign_request()
    {
        // 1. Create a pending request
        $publicKey = $this->passkey->data->credentialPublicKey ?? '';
        $expectedL1Address = 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
        $request = SovereignBalanceRequest::create([
            'legal_entity_id' => $this->legalEntity->id,
            'type' => 'top_up',
            'amount' => 20000.00,
            'currency' => 'RUB',
            'status' => 'pending',
            'l1_address' => $expectedL1Address,
            'passkey_id' => $this->passkey->id,
            'signature_assertion' => ['rawId' => 'mocked-raw-id'],
            'comment' => 'Top up me high tek style',
        ]);

        // Mock out external Aggregator Kernel Http endpoints
        Http::fake([
            'http://api.wildflow.test/api/v1/partners/top-up' => Http::response(['success' => true, 'balance' => 70000.00], 200),
        ]);

        // Login as super admin or user to execute the action
        $admin = User::factory()->create();
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole($adminRole);

        Livewire::actingAs($admin);

        Livewire::test(SovereignBalanceRequestsRelationManager::class, [
            'ownerRecord' => $this->legalEntity,
            'pageClass' => \App\Filament\Resources\B2B\LegalEntityResource\Pages\EditLegalEntity::class,
        ])
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request)
            ->assertHasNoTableActionErrors();

        // 2. Assert local and remote states are updated atomic
        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertEquals($admin->id, $request->approved_by);

        $this->legalEntity->refresh();
        $this->assertEquals(70000.00, (float)$this->legalEntity->balance);
        $this->assertEquals(70000.00, (float)$this->legalEntity->available_balance);

        // Assert record is logged inside local Sovereign Ledger
        $this->assertDatabaseHas('sovereign_ledger', [
            'legal_entity_id' => $this->legalEntity->id,
            'event_type' => 'FINANCE_DEPOSIT',
            'trigger_source' => "DID:PASSKEY:{$expectedL1Address}",
        ]);

        // Assert outward Http request was made
        Http::assertSent(function (\Illuminate\Http\Client\Request $httpRequest) use ($request) {
            return str_contains($httpRequest->url(), 'partners/top-up') &&
                $httpRequest['terminal_id'] === (string)$this->legalEntity->id &&
                (float)$httpRequest['amount'] === 20000.00;
        });
    }

    /**
     * Test Filament Reject action.
     */
    public function test_filament_reject_sovereign_request()
    {
        // 1. Create a pending request
        $publicKey = $this->passkey->data->credentialPublicKey ?? '';
        $expectedL1Address = 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
        $request = SovereignBalanceRequest::create([
            'legal_entity_id' => $this->legalEntity->id,
            'type' => 'grant_credit',
            'amount' => 30000.00,
            'currency' => 'RUB',
            'status' => 'pending',
            'l1_address' => $expectedL1Address,
            'passkey_id' => $this->passkey->id,
            'signature_assertion' => ['rawId' => 'mocked-raw-id'],
            'comment' => 'Requesting B2B JIT Credit Line',
        ]);

        // Login as super admin or user to execute the action
        $admin = User::factory()->create();
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole($adminRole);

        Livewire::actingAs($admin);

        Livewire::test(SovereignBalanceRequestsRelationManager::class, [
            'ownerRecord' => $this->legalEntity,
            'pageClass' => \App\Filament\Resources\B2B\LegalEntityResource\Pages\EditLegalEntity::class,
        ])
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('reject', $request, [
                'rejection_reason' => 'Invalid compliance documents.'
            ])
            ->assertHasNoTableActionErrors();

        // 2. Assert state
        $request->refresh();
        $this->assertEquals('rejected', $request->status);
        $this->assertStringContainsString('Отклонено: Invalid compliance documents.', $request->comment);

        // Assert local balance was untouched
        $this->legalEntity->refresh();
        $this->assertEquals(50000.00, (float)$this->legalEntity->balance);
        $this->assertEquals(50000.00, (float)$this->legalEntity->available_balance);
    }

    /**
     * Test Filament View Proof action and its blade view rendering.
     */
    public function test_filament_view_proof_action()
    {
        $publicKey = $this->passkey->data->credentialPublicKey ?? '';
        $expectedL1Address = 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
        
        $request = SovereignBalanceRequest::create([
            'legal_entity_id' => $this->legalEntity->id,
            'type' => 'top_up',
            'amount' => 10000.00,
            'currency' => 'RUB',
            'status' => 'pending',
            'l1_address' => $expectedL1Address,
            'passkey_id' => $this->passkey->id,
            'signature_assertion' => [
                'id' => 'dummy-credential-id',
                'response' => [
                    'clientDataJSON' => base64_encode('{"type":"webauthn.get","challenge":"dummy-challenge","origin":"http://localhost"}'),
                    'authenticatorData' => bin2hex(str_repeat('a', 33)), // index 32 flags byte is 'a' (0x61 = UP active)
                    'signature' => base64_encode('dummy-signature-bytes'),
                ]
            ],
            'comment' => 'Hardware signature verification test',
        ]);

        $admin = User::factory()->create();
        $adminRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin->assignRole($adminRole);

        Livewire::actingAs($admin);

        Livewire::test(SovereignBalanceRequestsRelationManager::class, [
            'ownerRecord' => $this->legalEntity,
            'pageClass' => \App\Filament\Resources\B2B\LegalEntityResource\Pages\EditLegalEntity::class,
        ])
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('viewProof', $request)
            ->assertHasNoTableActionErrors();
    }
}

