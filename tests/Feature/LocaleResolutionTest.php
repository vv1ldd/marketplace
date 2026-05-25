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
            'app.supported_locales' => ['ru', 'en', 'es', 'tk', 'uz', 'ka', 'hy', 'kk', 'tr'],
        ]);
    }

    public function test_browser_language_is_used_when_profile_is_unknown(): void
    {
        $this->withHeader('Accept-Language', 'kk-KZ,ru;q=0.8,en;q=0.5')
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'kk');
    }

    public function test_active_legal_entity_region_beats_browser_language(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Kazakhstan Entity',
            'short_name' => 'KZ Entity',
            'inn' => '123456789012',
            'email' => 'kz@example.test',
            'country_code' => 'KZ',
            'is_active' => true,
        ]);

        $this->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/login')
            ->assertOk()
            ->assertHeader('Content-Language', 'kk');
    }

    public function test_profile_locale_beats_region_and_browser_language(): void
    {
        $user = User::factory()->create([
            'meta' => ['preferred_locale' => 'uz'],
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Russia Entity',
            'short_name' => 'RU Entity',
            'inn' => '770000000001',
            'email' => 'ru@example.test',
            'country_code' => 'RU',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_legal_entity_id' => $entity->id])
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('Content-Language', 'uz');
    }

    public function test_language_switch_persists_to_authenticated_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/login')
            ->get('/lang/ka')
            ->assertRedirect('/login');

        $this->assertSame('ka', $user->refresh()->meta['preferred_locale'] ?? null);

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('Content-Language', 'ka');
    }
}
