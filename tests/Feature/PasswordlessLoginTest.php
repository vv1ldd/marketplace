<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_migration_pill_page_rejects_unknown_token(): void
    {
        $this->get('/migration-pill/not-a-real-token')
            ->assertOk()
            ->assertSee('Ссылка недоступна')
            ->assertSee('Ссылка миграции недействительна');
    }

    public function test_business_registration_has_dedicated_simple_l1_target(): void
    {
        $this->get('/business/register')
            ->assertOk()
            ->assertSee('Создание профиля')
            ->assertSee('выделенный маршрут для подключения юрлица')
            ->assertSee('name="registration_target"', false)
            ->assertSee('value="legal_entity"', false);
    }

    public function test_business_landing_is_public_and_links_to_registration(): void
    {
        $this->get('/business')
            ->assertOk()
            ->assertSee('MEANLY BUSINESS')
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
