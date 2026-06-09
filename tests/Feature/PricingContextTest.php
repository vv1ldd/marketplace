<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'en',
            'app.supported_locales' => ['ru', 'en', 'es', 'de'],
        ]);
    }

    public function test_global_market_resolves_pricing_context(): void
    {
        $this->get('https://meanly.one/')
            ->assertOk()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('X-Pricing-Scope', 'global')
            ->assertHeader('X-Display-Currency', 'USD');

        $this->assertSame('global', pricing()->pricingScope);
        $this->assertSame('USD', pricing()->displayCurrency);
        $this->assertSame('RUB', pricing()->settlementCurrency);
        $this->assertSame('RUB', pricing()->storageCurrency);
    }

    public function test_russian_market_resolves_pricing_context(): void
    {
        $this->get('https://meanly.ru/')
            ->assertOk()
            ->assertHeader('X-Market', 'ru')
            ->assertHeader('X-Pricing-Scope', 'ru')
            ->assertHeader('X-Display-Currency', 'RUB');

        $this->assertSame('ru', pricing()->pricingScope);
        $this->assertSame('RUB', pricing()->displayCurrency);
        $this->assertSame('RUB', pricing()->settlementCurrency);
        $this->assertSame('RUB', pricing()->storageCurrency);
    }

    public function test_locale_override_does_not_change_global_pricing_context(): void
    {
        foreach (['ru', 'de', 'es'] as $locale) {
            $this->get("https://meanly.one/?locale={$locale}")
                ->assertOk()
                ->assertHeader('X-Market', 'global')
                ->assertHeader('X-Pricing-Scope', 'global')
                ->assertHeader('X-Display-Currency', 'USD')
                ->assertHeader('Content-Language', 'en');

            $this->assertSame('global', pricing()->pricingScope);
            $this->assertSame('USD', pricing()->displayCurrency);
            $this->assertSame('RUB', pricing()->settlementCurrency);
            $this->assertSame('RUB', pricing()->storageCurrency);
        }
    }

    public function test_locale_override_does_not_change_russian_pricing_context(): void
    {
        foreach (['en', 'de', 'es'] as $locale) {
            $this->get("https://meanly.ru/?locale={$locale}")
                ->assertOk()
                ->assertHeader('X-Market', 'ru')
                ->assertHeader('X-Pricing-Scope', 'ru')
                ->assertHeader('X-Display-Currency', 'RUB')
                ->assertHeader('Content-Language', $locale);

            $this->assertSame('ru', pricing()->pricingScope);
            $this->assertSame('RUB', pricing()->displayCurrency);
            $this->assertSame('RUB', pricing()->settlementCurrency);
            $this->assertSame('RUB', pricing()->storageCurrency);
        }
    }
}
