<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\Seller;
use App\Models\SovereignLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordlessLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.domain' => null]);
    }

    public function test_public_login_page_is_passkey_only(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Войти с помощью Passkey')
            ->assertDontSee('Войти по паролю');
    }

    public function test_logout_routes_return_to_home(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $partner = User::factory()->create();
        $partner->assignRole('b2b_partner');
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $partner->id,
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->actingAs($user)
            ->post(route('cabinet.logout'))
            ->assertRedirect(route('home'));

        $this->actingAs($partner)
            ->post(route('partner.logout'))
            ->assertRedirect(route('home'));

        $this->assertGreaterThanOrEqual(2, SovereignLedger::where('event_type', 'AUTH_LOGOUT_INTENT')->where('entity_id', $user->id)->count());
        $this->assertGreaterThanOrEqual(1, SovereignLedger::where('event_type', 'AUTH_LOGOUT_INTENT')->where('entity_id', $partner->id)->count());
    }

    public function test_passkey_delete_records_remove_intent(): void
    {
        $user = User::factory()->create();
        $passkey = \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->actingAs($user);

        (new \App\Livewire\PasskeysComponent())->deletePasskey($passkey->id);

        $event = SovereignLedger::where('event_type', 'PASSKEY_REMOVE_INTENT')->firstOrFail();

        $this->assertSame('passkey.remove', data_get($event->payload, 'intent_type'));
        $this->assertSame(hash('sha256', (string) $passkey->credential_id), data_get($event->payload, 'credential_id_hash'));
        $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
    }

    public function test_b2b_registration_assigns_seller_role_with_sellers_guard(): void
    {
        Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);

        $user = User::factory()->create(['email' => 'b2b-role-user@example.test']);
        $seller = Seller::create([
            'first_name' => 'B2B',
            'email' => 'b2b-role-user@example.test',
            'password' => 'secret',
            'is_active' => true,
        ]);

        $controller = new \App\Http\Controllers\PartnerRegistrationController();
        $method = new \ReflectionMethod($controller, 'assignB2BRoles');
        $method->setAccessible(true);
        $method->invoke($controller, $user, $seller);

        $this->assertTrue($user->fresh()->hasRole('b2b_partner'));
        $this->assertDatabaseHas('roles', ['name' => 'b2b_partner', 'guard_name' => 'sellers']);
        $this->assertTrue($seller->fresh()->hasRole('b2b_partner', 'sellers'));
    }

    public function test_pending_moderation_partner_sees_onboarding_instead_of_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'email_verified_at' => null,
        ]);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Pending Business LLC',
            'inn' => '770000000001',
            'email' => 'company@example.test',
            'status' => 'pending_moderation',
            'is_active' => false,
            'agreement_signed_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('partner.dashboard'))
            ->assertRedirect(route('partner.onboarding'));

        $this->actingAs($user)
            ->get(route('partner.onboarding'))
            ->assertOk()
            ->assertSee('Мы проверяем компанию')
            ->assertSee('Все в порядке, сейчас ничего делать не нужно')
            ->assertSee('подтвержденный email компании')
            ->assertSee('company@example.test')
            ->assertSee('Заявка отправлена')
            ->assertSee('проверяем компанию')
            ->assertSee('Pending Business LLC')
            ->assertDontSee('Meanly Support')
            ->assertDontSee('Напишите, пожалуйста');
    }

    public function test_business_registration_validates_representative_full_name(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->mock(\App\Services\DaDataService::class, function ($mock): void {
            $mock->shouldReceive('findByInn')->andReturn([
                'type' => 'LEGAL',
                'inn' => '7700000000',
                'name' => ['short_with_opf' => 'ООО Ромашка'],
                'ogrn' => '1234567890123',
                'address' => ['value' => 'Москва'],
                'management' => ['name' => 'Иванов Иван Иванович'],
            ]);
        });

        $this->actingAs($user)
            ->withSession(['business_registration_verified_email' => 'company@example.test'])
            ->post(route('business.register.submit'), [
                'inn' => '7700000000',
                'jurisdiction' => 'RU',
                'signer_role' => 'representative',
                'signer_name' => 'Иван',
                'business_email' => 'company@example.test',
            ])
            ->assertSessionHasErrors('signer_name');
    }

    public function test_business_registration_validates_ip_full_name_from_dadata(): void
    {
        $user = User::factory()->create();
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $this->mock(\App\Services\DaDataService::class, function ($mock): void {
            $mock->shouldReceive('findByInn')->andReturn([
                'type' => 'INDIVIDUAL',
                'inn' => '526216895584',
                'fio' => [
                    'surname' => 'Иванов1',
                    'name' => 'Иван',
                    'patronymic' => 'Иванович',
                ],
                'ogrnip' => '325527500033290',
                'address' => ['value' => 'Нижний Новгород'],
            ]);
        });

        $this->actingAs($user)
            ->withSession(['business_registration_verified_email' => 'ip@example.test'])
            ->post(route('business.register.submit'), [
                'inn' => '526216895584',
                'jurisdiction' => 'RU',
                'signer_role' => 'ceo',
                'address' => 'Нижний Новгород',
                'business_email' => 'ip@example.test',
            ])
            ->assertSessionHasErrors('director_name');
    }

    public function test_business_registration_has_dedicated_simple_l1_target(): void
    {
        $this->get('/business/register')
            ->assertOk()
            ->assertSee('Регистрация бизнеса')
            ->assertSee('Сначала подтвердим рабочий email')
            ->assertSee('Рабочий email')
            ->assertSee('Получить код')
            ->assertSee('ИНН организации')
            ->assertSee('Найдена организация')
            ->assertDontSee('Имя владельца профиля')
            ->assertDontSee('Телефон для связи')
            ->assertSee('name="registration_target"', false)
            ->assertSee('value="legal_entity"', false);
    }

    public function test_business_registration_verifies_email_before_inn_flow(): void
    {
        Mail::fake();
        config(['app.redeem_local_verification_code' => 'TRUSTED_USER']);

        $this->postJson(route('business.register.email.send'), [
            'email' => 'founder@example.test',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSent(\App\Mail\VerificationCodeMail::class);

        $this->postJson(route('business.register.email.verify'), [
            'email' => 'founder@example.test',
            'code' => 'TRUSTED_USER',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('email', 'founder@example.test');

        $this->assertSame('founder@example.test', session('business_registration_verified_email'));
    }

    public function test_cabinet_registration_creates_simple_l1_identity_without_email(): void
    {
        $this->get('http://meanly.test/cabinet/register')
            ->assertOk()
            ->assertSee('Создание аккаунта')
            ->assertSee('Как вас называть?')
            ->assertSee('Например, Selim')
            ->assertSee('required', false)
            ->assertSee('Создайте профиль без почты')
            ->assertSee('Профиль входа')
            ->assertSee('Создать профиль')
            ->assertDontSee('Ваш Email')
            ->assertDontSee('mail@example.com')
            ->assertDontSee('Simple L1 identity')
            ->assertDontSee('trusted device');
    }

    public function test_registration_options_create_email_less_simple_l1_user_with_display_name(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $response = $this->postJson('/business/register/options', [
            'registration_target' => 'profile',
            'display_name' => 'selim',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['options', 'identity' => ['label', 'user_id'], 'new_csrf'])
            ->assertJsonPath('identity.label', 'Selim');

        $user = User::findOrFail($response->json('identity.user_id'));

        $this->assertNull($user->email);
        $this->assertSame('simple_l1_identity', $user->meta['registration_source'] ?? null);
        $this->assertSame('Selim', $user->meta['display_name'] ?? null);
        $this->assertSame('Selim', $user->getPassKeyDisplayName());
        $this->assertAuthenticatedAs($user);

        Auth::logout();
        $this->flushSession();
    }

    public function test_business_registration_options_keep_user_email_less_before_inn_registration(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $response = $this->withSession(['business_registration_verified_email' => 'founder@example.test'])
            ->postJson('/business/register/options', [
            'registration_target' => 'legal_entity',
            'display_name' => 'selim',
            'business_email' => 'founder@example.test',
        ]);

        $response->assertOk()
            ->assertJsonPath('identity.label', 'Selim');

        $user = User::findOrFail($response->json('identity.user_id'));
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->phone);

        Auth::logout();
        $this->flushSession();
    }

    public function test_business_registration_options_require_display_name_for_business_profile(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->withSession(['business_registration_verified_email' => 'founder@example.test'])
            ->postJson('/business/register/options', [
                'registration_target' => 'legal_entity',
                'business_email' => 'founder@example.test',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Введите имя владельца профиля.');
    }

    public function test_registration_options_require_display_name_for_personal_profile(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $this->postJson('/business/register/options', [
            'registration_target' => 'profile',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Введите имя владельца профиля.');
    }

    public function test_l1_identity_service_keeps_entity_address_separate_from_device_key(): void
    {
        $service = app(\App\Services\L1IdentityService::class);

        $entityAddress = $service->newEntityAddress();
        $keyAddress = $service->keyAddressFromPublicKey('test-public-key');

        $this->assertStringStartsWith('sl1e_', $entityAddress);
        $this->assertStringStartsWith('sl1_', $keyAddress);
        $this->assertNotSame($entityAddress, $keyAddress);
    }

    public function test_business_landing_is_public_and_links_to_registration(): void
    {
        $this->get('/business')
            ->assertOk()
            ->assertSee('Meanly для бизнеса')
            ->assertSee('Подключить бизнес')
            ->assertSee(route('business.register'));
    }

    public function test_connected_business_user_sees_console_cta_instead_of_connect_business(): void
    {
        $role = Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
        $user = User::factory()->create([
            'first_name' => 'Connected',
            'email' => 'connected-business@example.test',
        ]);
        $user->assignRole($role);
        \Spatie\LaravelPasskeys\Models\Passkey::factory()->create([
            'authenticatable_id' => $user->id,
        ]);

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Connected Business LLC',
            'available_balance' => 0,
            'currency' => 'RUB',
            'status' => 'active',
            'is_active' => true,
            'inn' => '1234567890',
            'kpp' => '123456789',
            'ogrn' => '1234567890123',
            'bank_account' => '12345678901234567890',
            'bank_correspondent_account' => '12345678901234567890',
            'bank_name' => 'Connected Bank',
            'legal_address' => 'Connected Street 1',
            'postal_address' => 'Connected Street 1',
            'director_name' => 'Connected Director',
        ]);
        $user->managedLegalEntities()->attach($legalEntity->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('B2B Консоль', false)
            ->assertDontSee('Подключить бизнес', false);

        $this->actingAs($user)
            ->get('/business')
            ->assertOk()
            ->assertSee('Открыть B2B консоль', false)
            ->assertDontSee('Подключить бизнес', false);
    }

    public function test_legacy_partner_landing_redirects_to_business_landing(): void
    {
        $this->get('/partner-landing')
            ->assertRedirect(route('business.landing'));
    }

    public function test_legacy_legal_entities_registration_redirects_to_business_route(): void
    {
        $this->get('/legal-entities/register')
            ->assertRedirect(route('business.register'));
    }
}
