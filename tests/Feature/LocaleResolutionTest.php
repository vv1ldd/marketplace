<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.domain' => null,
            'app.locale' => 'en',
            'app.supported_locales' => ['en', 'ru'],
        ]);
    }

    public function test_global_market_domain_pins_english_before_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.8,en;q=0.5')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_global_market_domain_pins_english_before_legal_entity_region(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Russia Entity',
            'short_name' => 'RU Entity',
            'inn' => '123456789012',
            'email' => 'ru@example.test',
            'country_code' => 'RU',
            'is_active' => true,
        ]);

        $this->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_unsupported_argentina_region_falls_back_to_english_until_spanish_is_enabled(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Argentina Entity',
            'short_name' => 'AR Entity',
            'inn' => '30712345678',
            'email' => 'ar@example.test',
            'country_code' => 'AR',
            'is_active' => true,
        ]);

        $this->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_future_spanish_locale_does_not_override_global_market_domain(): void
    {
        config(['app.supported_locales' => ['en', 'ru', 'es']]);

        $entity = LegalEntity::create([
            'name' => 'Argentina Entity',
            'short_name' => 'AR Entity',
            'inn' => '30712345678',
            'email' => 'ar@example.test',
            'country_code' => 'AR',
            'is_active' => true,
        ]);

        $this->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_unsupported_browser_language_falls_back_to_english(): void
    {
        $this->withHeader('Accept-Language', 'es-AR,es;q=0.9,en;q=0.5')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_global_market_domain_pins_english_before_profile_locale(): void
    {
        $user = User::factory()->create([
            'meta' => ['preferred_locale' => 'ru'],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Argentina Entity',
            'short_name' => 'AR Entity',
            'inn' => '30712345678',
            'email' => 'ar@example.test',
            'country_code' => 'AR',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('Content-Language', 'en');
    }

    public function test_language_switch_persists_to_authenticated_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/login')
            ->get('/lang/ru')
            ->assertRedirect('/login');

        $this->assertSame('ru', $user->refresh()->meta['preferred_locale'] ?? null);

        $this->actingAs($user)
            ->withServerVariables(['HTTP_HOST' => 'localhost'])
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('Content-Language', 'en');
    }
}
