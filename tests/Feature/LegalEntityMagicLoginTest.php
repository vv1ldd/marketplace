<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegalEntityMagicLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
    }

    public function test_ops_partners_data_contains_migration_pill_issue_url_for_all_legal_entities(): void
    {
        $admin = User::factory()->create(['email' => 'admin@admin.com']);
        $admin->assignRole('super_admin');
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $admin->id,
        ]);

        $entity = LegalEntity::create([
            'name' => 'Magic Link Entity',
            'short_name' => 'Magic Entity',
            'inn' => '770000000001',
            'email' => 'magic-entity@example.test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('http://meanly.test/ops/dashboard/partners/data');

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $entity->id);
        $response->assertJsonPath('data.0.name', 'Magic Link Entity');

        $url = $response->json('data.0.migration_pill_issue_url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/migration-pill/legal-entities/' . $entity->id . '/issue', $url);
        $this->assertNull($response->json('data.0.magic_login_url'));
        $this->assertNull($response->json('data.0.dev_magic_login_url'));
    }

    public function test_dev_magic_login_by_inn_issues_migration_pill(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Inn Magic Entity',
            'short_name' => 'Inn Magic',
            'inn' => '770000000004',
            'email' => 'inn-magic@example.test',
            'is_active' => true,
        ]);

        $response = $this->get(route('legal-entities.magic-login.inn', ['inn' => '770000000004']));

        $response->assertRedirect();
        $this->assertStringContainsString('/migration-pill/', $response->headers->get('Location'));
        $this->assertGuest();
        $this->assertDatabaseHas('legal_entity_migration_pills', [
            'legal_entity_id' => $entity->id,
            'used_at' => null,
        ]);
    }

    public function test_query_magic_login_by_inn_issues_migration_pill(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Query Magic Entity',
            'short_name' => 'Query Magic',
            'inn' => '770000000005',
            'email' => 'query-magic@example.test',
            'is_active' => true,
        ]);

        $response = $this->get('/magic-login?magic-login=770000000005');

        $response->assertRedirect();
        $this->assertStringContainsString('/migration-pill/', $response->headers->get('Location'));
        $this->assertGuest();
        $this->assertDatabaseHas('legal_entity_migration_pills', [
            'legal_entity_id' => $entity->id,
            'used_at' => null,
        ]);
    }

    public function test_magic_login_is_not_available_in_production(): void
    {
        config(['app.env' => 'production']);

        $entity = LegalEntity::create([
            'name' => 'Production Blocked Entity',
            'short_name' => 'Production Blocked',
            'inn' => '770000000006',
            'email' => 'prod-blocked@example.test',
            'is_active' => true,
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'legal-entities.magic-login',
            now()->addMinutes(15),
            ['legalEntity' => $entity->id]
        );

        $this->get(route('legal-entities.magic-login.inn', ['inn' => '770000000006']))->assertNotFound();
        $this->get('/magic-login?magic-login=770000000006')->assertNotFound();
        $this->get($signedUrl)->assertNotFound();
    }

    public function test_signed_magic_link_issues_pill_for_existing_legal_entity_owner(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.test',
            'first_name' => 'Owner',
            'last_name' => 'User',
        ]);
        $owner->assignRole('b2b_partner');

        $entity = LegalEntity::create([
            'user_id' => $owner->id,
            'name' => 'Owner Entity',
            'short_name' => 'Owner Entity',
            'inn' => '770000000002',
            'email' => 'owner@example.test',
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'legal-entities.magic-login',
            now()->addMinutes(15),
            ['legalEntity' => $entity->id]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('/migration-pill/', $response->headers->get('Location'));
        $this->assertGuest();
        $this->assertDatabaseHas('legal_entity_migration_pills', [
            'legal_entity_id' => $entity->id,
            'user_id' => $owner->id,
            'used_at' => null,
        ]);
    }

    public function test_magic_link_provisions_principal_and_issues_pill_for_legal_entity_without_user(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Provisioned Entity',
            'short_name' => 'Provisioned',
            'inn' => '770000000003',
            'email' => 'provisioned@example.test',
            'is_active' => true,
        ]);

        $url = URL::temporarySignedRoute(
            'legal-entities.magic-login',
            now()->addMinutes(15),
            ['legalEntity' => $entity->id]
        );

        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString('/migration-pill/', $response->headers->get('Location'));

        $entity->refresh();
        $user = User::find($entity->user_id);
        $seller = Seller::find($entity->seller_id);

        $this->assertNotNull($user);
        $this->assertNotNull($seller);
        $this->assertGuest();
        $this->assertTrue($user->hasRole('b2b_partner'));
        $this->assertTrue($user->managedLegalEntities()->where('legal_entities.id', $entity->id)->exists());
        $this->assertTrue($seller->managedLegalEntities()->where('legal_entities.id', $entity->id)->exists());
        $this->assertDatabaseHas('legal_entity_migration_pills', [
            'legal_entity_id' => $entity->id,
            'user_id' => $user->id,
            'used_at' => null,
        ]);
    }

    public function test_migration_pill_is_single_use(): void
    {
        $owner = User::factory()->create(['email' => 'single-use@example.test']);
        $owner->assignRole('b2b_partner');

        $entity = LegalEntity::create([
            'user_id' => $owner->id,
            'name' => 'Single Use Entity',
            'short_name' => 'Single Use',
            'inn' => '770000000007',
            'email' => 'single-use@example.test',
            'is_active' => true,
        ]);

        [$pill, $token] = app(\App\Services\LegalEntityMigrationPillService::class)->issueForOwner($entity, 'meanly.test');

        $this->assertNotNull(app(\App\Services\LegalEntityMigrationPillService::class)->findConsumableByToken($token));

        app(\App\Services\LegalEntityMigrationPillService::class)->consume($token, 123);

        $this->assertNull(app(\App\Services\LegalEntityMigrationPillService::class)->findConsumableByToken($token));
        $this->assertNotNull($pill->refresh()->used_at);
        $this->assertSame(123, $pill->used_by_passkey_id);
    }

    public function test_options_can_update_email_and_name_on_demand(): void
    {
        $owner = User::factory()->create([
            'email' => 'partner@meanly.ru',
            'first_name' => 'Владелец',
            'last_name' => 'Meanly',
        ]);
        $owner->assignRole('b2b_partner');

        $entity = LegalEntity::create([
            'user_id' => $owner->id,
            'name' => 'Owner Entity',
            'short_name' => 'Owner Entity',
            'inn' => '770000000002',
            'email' => null,
            'is_active' => true,
        ]);

        [$pill, $token] = app(\App\Services\LegalEntityMigrationPillService::class)->issueForOwner($entity, parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');

        $response = $this->postJson(route('migration-pill.options', ['token' => $token]), [
            'email' => 'custom-owner@example.test',
            'first_name' => 'Selim',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['options', 'new_csrf']);

        $owner->refresh();
        $this->assertSame('custom-owner@example.test', $owner->email);
        $this->assertSame('Selim', $owner->first_name);

        $entity->refresh();
        $this->assertSame('custom-owner@example.test', $entity->email);
    }

    public function test_migration_pill_accept_associates_passkey_and_updates_legal_entity_metadata(): void
    {
        $owner = User::factory()->create([
            'email' => 'partner-accept@meanly.ru',
            'first_name' => 'AcceptOwner',
        ]);
        $owner->assignRole('b2b_partner');

        $entity = LegalEntity::create([
            'user_id' => $owner->id,
            'name' => 'Accept Entity',
            'short_name' => 'Accept Entity',
            'inn' => '770000000003',
            'email' => 'partner-accept@meanly.ru',
            'is_active' => false,
            'status' => 'pending_signature',
        ]);

        [$pill, $token] = app(\App\Services\LegalEntityMigrationPillService::class)->issueForOwner($entity, parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');

        // Mock StorePasskeyAction using safely created Passkey via factory
        $passkey = \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $owner->id,
        ]);

        $this->mock(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class, function ($mock) use ($passkey) {
            $mock->shouldReceive('execute')->andReturn($passkey);
        });

        // Mock WildflowService to avoid external API calls during sync
        $this->mock(\App\Services\WildflowService::class, function ($mock) {
            $mock->shouldReceive('syncPartner')->andReturn(true);
        });

        $response = $this->withSession([
            'migration_pill_token_hash' => app(\App\Services\LegalEntityMigrationPillService::class)->hashToken($token),
            'migration_pill_passkey_options' => json_encode(['mocked' => 'options']),
        ])->postJson(route('migration-pill.accept', ['token' => $token]), [
            'passkey_attestation' => 'mocked-attestation',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['success', 'l1_address', 'redirect']);

        $entity->refresh();
        $this->assertTrue($entity->is_active);
        $this->assertSame('active', $entity->status);
        $this->assertNotNull($entity->agreement_signed_at);
        $this->assertSame('mocked-attestation', $entity->agreement_signature);

        $this->assertIsArray($entity->agreement_metadata);
        $this->assertSame('ceo', $entity->agreement_metadata['signer_role']);
        $this->assertSame('AcceptOwner', $entity->agreement_metadata['signer_name']);
        $this->assertSame($passkey->id, $entity->agreement_metadata['passkey_id']);
        $this->assertStringStartsWith('sl1_', $entity->agreement_metadata['l1_address']);

        // Check if user is logged in
        $this->assertAuthenticatedAs($owner);
    }
}
