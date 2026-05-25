<?php

namespace Tests\Feature;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'session.domain' => null,
            'app.supported_themes' => ['consortium', 'partner', 'retro', 'nordic', 'synthwave', 'carbon'],
            'app.theme_fallback' => 'consortium',
        ]);
    }

    public function test_query_theme_beats_fallback_and_sets_cookie(): void
    {
        $this->get('/login?theme=retro')
            ->assertOk()
            ->assertHeader('X-Theme', 'retro')
            ->assertPlainCookie('theme', 'retro');
    }

    public function test_profile_theme_beats_region(): void
    {
        $user = User::factory()->create([
            'theme' => 'carbon',
        ]);

        $entity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => 'Nordic Entity',
            'short_name' => 'Nordic',
            'inn' => '770000000010',
            'email' => 'nordic@example.test',
            'country_code' => 'SE',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['active_legal_entity_id' => $entity->id])
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('X-Theme', 'carbon');
    }

    public function test_region_can_pick_theme_when_no_profile_choice_exists(): void
    {
        $entity = LegalEntity::create([
            'name' => 'Nordic Entity',
            'short_name' => 'Nordic',
            'inn' => '770000000011',
            'email' => 'nordic2@example.test',
            'country_code' => 'SE',
            'is_active' => true,
        ]);

        $this->withSession(['active_legal_entity_id' => $entity->id])
            ->get('/login')
            ->assertOk()
            ->assertHeader('X-Theme', 'nordic');
    }

    public function test_theme_switch_persists_to_authenticated_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/login')
            ->get('/theme/synthwave')
            ->assertRedirect('/login');

        $user->refresh();

        $this->assertSame('synthwave', $user->theme);
        $this->assertSame('synthwave', $user->meta['preferred_theme'] ?? null);

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect()
            ->assertHeader('X-Theme', 'synthwave');
    }
}
