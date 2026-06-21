<?php

namespace Tests\Feature;

use App\Models\CanonicalProductIdentity;
use App\Services\CanonicalStorefrontHomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalProductPageSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'en',
            'app.supported_locales' => ['en', 'ru', 'es', 'ka'],
            'session.domain' => null,
        ]);
    }

    public function test_global_domain_canonical_product_uses_global_market_and_hreflang_alternates(): void
    {
        $identity = $this->canonicalIdentity();

        $this->get("https://meanly.one/catalog/products/{$identity->identity_slug}")
            ->assertRedirect("/catalog/products/{$identity->identity_slug}")
            ->assertHeader('X-Market', 'global')
            ->assertHeader('Content-Language', 'en');

        $this->getJson("https://meanly.one/llms/catalog/products/{$identity->identity_slug}.json")
            ->assertOk()
            ->assertJsonPath('market_context.market', 'global')
            ->assertJsonPath('market_context.locale', 'en')
            ->assertJsonPath('market_context.domain', 'meanly.one')
            ->assertJsonPath('canonical_url', 'https://meanly.one/catalog/products/xbox-us-25-usd-seo');
    }

    public function test_russian_domain_canonical_product_uses_ru_market_even_with_english_locale_override(): void
    {
        $identity = $this->canonicalIdentity();

        $this->get("https://meanly.ru/catalog/products/{$identity->identity_slug}?locale=en")
            ->assertRedirect("/catalog/products/{$identity->identity_slug}?locale=en")
            ->assertHeader('X-Market', 'ru')
            ->assertHeader('X-Display-Currency', 'RUB')
            ->assertHeader('Content-Language', 'en');

        $this->getJson("https://meanly.ru/llms/catalog/products/{$identity->identity_slug}.json?locale=en")
            ->assertOk()
            ->assertJsonPath('market_context.market', 'ru')
            ->assertJsonPath('market_context.currency', 'RUB')
            ->assertJsonPath('locale', 'en')
            ->assertJsonPath('canonical_url', 'https://meanly.ru/catalog/products/xbox-us-25-usd-seo');
    }

    public function test_locale_override_on_global_domain_is_ignored_for_english_only_global_surface(): void
    {
        $identity = $this->canonicalIdentity();

        $this->get("https://meanly.one/catalog/products/{$identity->identity_slug}?locale=ru")
            ->assertRedirect("/catalog/products/{$identity->identity_slug}?locale=ru")
            ->assertHeader('X-Market', 'global')
            ->assertHeader('X-Display-Currency', 'USD')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_global_domain_ignores_browser_russian_without_explicit_locale_override(): void
    {
        $identity = $this->canonicalIdentity();

        $this
            ->withHeader('Accept-Language', 'ru-RU,ru;q=0.9,en;q=0.8')
            ->get("https://meanly.one/catalog/products/{$identity->identity_slug}")
            ->assertRedirect("/catalog/products/{$identity->identity_slug}")
            ->assertHeader('X-Market', 'global')
            ->assertHeader('X-Display-Currency', 'USD')
            ->assertHeader('Content-Language', 'en');
    }

    public function test_global_catalog_category_summaries_use_english_labels_by_default(): void
    {
        $this->canonicalIdentity();
        app()->setLocale('en');

        $category = app(CanonicalStorefrontHomepageService::class)
            ->publicCategorySummaries()
            ->firstWhere('slug', 'console_payment_cards');

        $this->assertNotNull($category);
        $this->assertSame('Console payment cards', $category['name']);
        $this->assertSame('Карты оплаты для игровых приставок', $category['label_ru']);
    }

    private function canonicalIdentity(): CanonicalProductIdentity
    {
        return CanonicalProductIdentity::firstOrCreate(
            ['identity_slug' => 'xbox-us-25-usd-seo'],
            [
                'fingerprint' => hash('sha256', 'xbox-us-25-usd-seo'),
                'canonical_category' => 'console_payment_cards',
                'brand' => 'Xbox',
                'product_family' => 'Xbox Gift Card',
                'face_value' => 25,
                'face_value_currency' => 'USD',
                'region' => 'US',
                'platform' => 'global',
                'confidence' => 'high',
                'signals' => [],
                'provider_candidates_count' => 0,
                'seller_offers_count' => 0,
                'last_seen_at' => now(),
            ],
        );
    }
}
