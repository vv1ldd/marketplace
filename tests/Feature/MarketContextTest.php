<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'en',
            'app.supported_locales' => ['ru', 'en', 'es'],
        ]);
    }

    public function test_argentina_domain_resolves_market_context_and_locale(): void
    {
        foreach (['ar.marketplace.test', 'meanly.ar'] as $host) {
            $this->get("https://{$host}/theme/consortium")
                ->assertRedirect()
                ->assertHeader('X-Market', 'latam_ar')
                ->assertHeader('X-Pricing-Scope', 'latam_ar')
                ->assertHeader('X-Display-Currency', 'ARS')
                ->assertHeader('Content-Language', 'es');

            $context = market();
            $this->assertSame('latam_ar', $context->market);
            $this->assertSame($host, $context->host);
            $this->assertSame('es', $context->locale);
            $this->assertSame('ARS', $context->currency);
            $this->assertSame('latam_ar', $context->catalogScope);
            $this->assertSame('latam_ar', $context->pricingScope);
            $this->assertSame('AR', $context->demandRegion);
            $this->assertSame(['AR', 'US', 'TR'], $context->preferredProductRegions);
        }
    }

    public function test_russia_domain_resolves_market_context_and_locale(): void
    {
        foreach (['ru.marketplace.test', 'meanly.ru'] as $host) {
            $this->get("https://{$host}/theme/consortium")
                ->assertRedirect()
                ->assertHeader('X-Market', 'ru')
                ->assertHeader('X-Pricing-Scope', 'ru')
                ->assertHeader('X-Display-Currency', 'RUB')
                ->assertHeader('Content-Language', 'ru');

            $context = market();
            $this->assertSame('ru', $context->market);
            $this->assertSame($host, $context->host);
            $this->assertSame('RUB', $context->currency);
            $this->assertSame('ru', $context->catalogScope);
            $this->assertSame('ru', $context->pricingScope);
            $this->assertSame('RU', $context->demandRegion);
        }
    }

    public function test_unknown_domain_falls_back_to_global_market(): void
    {
        $this->get('https://unknown.marketplace.test/theme/consortium')
            ->assertRedirect()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('Content-Language', 'en');

        $context = market();
        $this->assertSame('global', $context->market);
        $this->assertFalse($context->matchedDomain);
    }

    public function test_meanly_domains_resolve_to_global_english_market(): void
    {
        foreach (['meanly.test', 'meanly.one', 'www.meanly.one'] as $host) {
            $this->withHeader('Accept-Language', 'ru,en;q=0.8')
                ->withSession(['locale' => 'ru'])
                ->get("https://{$host}/theme/consortium")
                ->assertRedirect()
                ->assertHeader('X-Market', 'global')
                ->assertHeader('X-Pricing-Scope', 'global')
                ->assertHeader('X-Display-Currency', 'USD')
                ->assertHeader('Content-Language', 'en');

            $context = market();
            $this->assertSame('global', $context->market);
            $this->assertSame('en', $context->locale);
            $this->assertSame('USD', $context->currency);
            $this->assertSame('global', $context->catalogScope);
            $this->assertSame('global', $context->pricingScope);
        }
    }

    public function test_meanly_one_storefront_uses_english_copy(): void
    {
        $this->withHeader('Accept-Language', 'ru,en;q=0.8')
            ->withSession(['locale' => 'ru'])
            ->get('https://meanly.one/')
            ->assertOk()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('Content-Language', 'en')
            ->assertSee('Meanly helps you find digital goods fast.')
            ->assertDontSee('Meanly помогает быстро найти цифровой товар.');
    }

    public function test_explicit_locale_can_override_global_market_ui_language(): void
    {
        $this->withHeader('Accept-Language', 'en,ru;q=0.8')
            ->get('https://meanly.one/?locale=ru')
            ->assertOk()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('X-Pricing-Scope', 'global')
            ->assertHeader('X-Display-Currency', 'USD')
            ->assertHeader('Content-Language', 'ru')
            ->assertSee('Meanly помогает быстро найти цифровой товар.');

        $context = market();
        $this->assertSame('global', $context->market);
        $this->assertSame('en', $context->locale);
        $this->assertSame('USD', $context->currency);
        $this->assertSame('global', $context->catalogScope);
        $this->assertSame('global', $context->pricingScope);
        $this->assertSame('global', pricing()->pricingScope);
        $this->assertSame('USD', pricing()->displayCurrency);
        $this->assertSame('RUBT', pricing()->settlementCurrency);
        $this->assertSame('RUB', pricing()->storageCurrency);
    }

    public function test_explicit_english_locale_does_not_change_russian_market_commerce(): void
    {
        $this->withHeader('Accept-Language', 'ru,en;q=0.8')
            ->get('https://meanly.ru/?locale=en')
            ->assertOk()
            ->assertHeader('X-Market', 'ru')
            ->assertHeader('X-Pricing-Scope', 'ru')
            ->assertHeader('X-Display-Currency', 'RUB')
            ->assertHeader('Content-Language', 'en')
            ->assertSee('Meanly helps you find digital goods fast.');

        $context = market();
        $this->assertSame('ru', $context->market);
        $this->assertSame('ru', $context->locale);
        $this->assertSame('RUB', $context->currency);
        $this->assertSame('ru', $context->catalogScope);
        $this->assertSame('ru', $context->pricingScope);
        $this->assertSame('RU', $context->demandRegion);
        $this->assertSame('ru', pricing()->pricingScope);
        $this->assertSame('RUB', pricing()->displayCurrency);
        $this->assertSame('RUBT', pricing()->settlementCurrency);
        $this->assertSame('RUB', pricing()->storageCurrency);
    }

    public function test_future_supported_locale_does_not_change_global_market_commerce(): void
    {
        config(['app.supported_locales' => ['ru', 'en', 'es', 'de']]);

        $this->withHeader('Accept-Language', 'en,de;q=0.8')
            ->get('https://meanly.one/?locale=de')
            ->assertOk()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('X-Pricing-Scope', 'global')
            ->assertHeader('X-Display-Currency', 'USD')
            ->assertHeader('Content-Language', 'de');

        $context = market();
        $this->assertSame('global', $context->market);
        $this->assertSame('en', $context->locale);
        $this->assertSame('USD', $context->currency);
        $this->assertSame('global', $context->catalogScope);
        $this->assertSame('global', $context->pricingScope);
        $this->assertNull($context->demandRegion);
    }

    public function test_meanly_ru_storefront_uses_russian_copy(): void
    {
        $this->withHeader('Accept-Language', 'en,ru;q=0.8')
            ->withSession(['locale' => 'en'])
            ->get('https://meanly.ru/')
            ->assertOk()
            ->assertHeader('X-Market', 'ru')
            ->assertHeader('Content-Language', 'ru')
            ->assertSee('Meanly помогает быстро найти цифровой товар.');
    }
}
