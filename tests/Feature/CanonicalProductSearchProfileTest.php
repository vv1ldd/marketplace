<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Models\CanonicalProductSearchProfile;
use App\Services\CanonicalProductSearchProfileBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalProductSearchProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_is_deterministic_and_rebuild_is_idempotent(): void
    {
        $identity = $this->identity('playstation-5-us-20-usd', [
            'brand' => 'PlayStation',
            'product_family' => 'PlayStation 5',
            'face_value' => 20,
            'face_value_currency' => 'USD',
            'region' => 'US',
        ]);
        /** @var CanonicalProductSearchProfileBuilder $builder */
        $builder = app(CanonicalProductSearchProfileBuilder::class);

        $this->assertSame($builder->build($identity), $builder->build($identity));

        $first = $builder->rebuild($identity)->fresh();
        $second = $builder->rebuild($identity)->fresh();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CanonicalProductSearchProfile::count());
        $this->assertSame(CanonicalProductSearchProfileBuilder::PROFILE_VERSION, $second->profile_version);
        $this->assertNull($second->last_error);
        $this->assertContains('ps5', $second->search_aliases['product']);
        $this->assertContains('usa', $second->search_aliases['region']);
        $this->assertContains('ps5', $second->search_tokens);
        $this->assertSame('PlayStation', $second->search_metadata['brand']);
        $this->assertSame('US', $second->search_metadata['region']);
    }

    public function test_rebuild_command_supports_identity_and_stale_modes(): void
    {
        $first = $this->identity('steam-ar-10-usd', [
            'brand' => 'Steam',
            'product_family' => 'Steam Wallet',
            'face_value' => 10,
            'face_value_currency' => 'USD',
            'region' => 'AR',
        ]);
        $second = $this->identity('xbox-us-50-usd', [
            'brand' => 'Xbox',
            'product_family' => 'Xbox Gift Card',
            'face_value' => 50,
            'face_value_currency' => 'USD',
            'region' => 'US',
        ]);

        $this->artisan('search-profile:rebuild', ['--identity' => $first->id])
            ->expectsOutput('profiles_targeted: 1')
            ->assertExitCode(0);

        $this->assertSame(1, CanonicalProductSearchProfile::count());
        $this->assertNotNull($first->searchProfile()->first());
        $this->assertNull($second->searchProfile()->first());

        $this->artisan('search-profile:rebuild', ['--stale' => true])
            ->expectsOutput('profiles_targeted: 1')
            ->assertExitCode(0);

        $this->assertSame(2, CanonicalProductSearchProfile::count());
        $this->assertSame(0, CanonicalProductSearchProfile::whereNotNull('last_error')->count());

        $this->artisan('search-profile:rebuild', ['--stale' => true])
            ->expectsOutput('profiles_targeted: 0')
            ->assertExitCode(0);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function identity(string $slug, array $overrides = []): CanonicalProductIdentity
    {
        return CanonicalProductIdentity::create(array_merge([
            'fingerprint' => hash('sha256', $slug),
            'identity_slug' => $slug,
            'canonical_category' => 'gift_cards',
            'brand' => 'Test Brand',
            'product_family' => 'Test Family',
            'face_value' => null,
            'face_value_currency' => null,
            'region' => null,
            'platform' => 'global',
            'confidence' => 'high',
            'signals' => [],
            'provider_candidates_count' => 1,
            'seller_offers_count' => 0,
            'last_seen_at' => now(),
        ], $overrides));
    }
}
