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
            'app.supported_locales' => ['ru', 'en', 'es', 'ka'],
        ]);
    }

    public function test_argentina_domain_resolves_market_context_and_locale(): void
    {
        foreach (['ar.marketplace.test', 'meanly.ar', 'digitienda.ar'] as $host) {
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
            $this->assertNotContains('yandex_market', $context->salesChannels);
        }
    }

    public function test_market_login_keeps_auth_flow_on_current_domain(): void
    {
        $expectations = [
            'meanly.ru' => ['market' => 'ru', 'locale' => 'ru', 'copy' => 'Сейчас откроется Simple Layer One'],
            'digitienda.ar' => ['market' => 'latam_ar', 'locale' => 'es', 'copy' => 'Simple Layer One se abrirá ahora'],
            'tsipruli.ge' => ['market' => 'ge', 'locale' => 'ka', 'copy' => 'Simple Layer One ახლავე გაიხსნება'],
        ];

        foreach ($expectations as $host => $expected) {
            $this->get("https://{$host}/login")
                ->assertOk()
                ->assertHeader('X-Market', $expected['market'])
                ->assertHeader('Content-Language', $expected['locale'])
                ->assertSee('/simple-l1/connect?', false)
                ->assertSee($expected['copy'])
                ->assertDontSee('https://meanly.one/simple-l1/connect', false);
        }
    }

    public function test_market_homepage_keeps_header_auth_links_on_current_domain(): void
    {
        foreach (['meanly.ru', 'digitienda.ar', 'tsipruli.ge'] as $host) {
            $this->get("https://{$host}/")
                ->assertOk()
                ->assertSee('/simple-l1/connect?', false)
                ->assertDontSee('https://meanly.one/simple-l1/connect', false)
                ->assertDontSee('storefront.index.title');
        }
    }

    public function test_market_simple_l1_handoff_uses_market_locale_and_callback_host(): void
    {
        $expectations = [
            'meanly.ru' => ['market' => 'ru', 'locale' => 'ru', 'title' => 'Входим через Simple Layer One?'],
            'digitienda.ar' => ['market' => 'latam_ar', 'locale' => 'es', 'title' => '¿Entrar con Simple Layer One?'],
            'tsipruli.ge' => ['market' => 'ge', 'locale' => 'ka', 'title' => 'შევიდეთ Simple Layer One-ით?'],
        ];

        foreach ($expectations as $host => $expected) {
            $response = $this
                ->withHeader('Accept', 'application/json')
                ->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->get("https://{$host}/simple-l1/connect?return_to=/store&mode=connect");

            $response
                ->assertOk()
                ->assertHeader('X-Market', $expected['market'])
                ->assertHeader('Content-Language', $expected['locale'])
                ->assertJsonPath('show_handoff', true)
                ->assertJsonPath('handoff.title', $expected['title']);

            $this->assertStringContainsString(
                rawurlencode("https://{$host}/simple-l1/callback"),
                (string) $response->json('redirect_url')
            );
            $this->assertStringNotContainsString(
                rawurlencode('https://meanly.one/simple-l1/callback'),
                (string) $response->json('redirect_url')
            );
            $this->assertSame("https://{$host}/simple-l1/callback", session('simple_l1_connect.redirect_uri'));
            session()->flush();
        }
    }

    public function test_georgia_domain_resolves_market_context_and_locale(): void
    {
        foreach (['tsipruli.ge', 'www.tsipruli.ge'] as $host) {
            $this->get("https://{$host}/theme/consortium")
                ->assertRedirect()
                ->assertHeader('X-Market', 'ge')
                ->assertHeader('X-Pricing-Scope', 'ge')
                ->assertHeader('X-Display-Currency', 'GEL')
                ->assertHeader('Content-Language', 'ka');

            $context = market();
            $this->assertSame('ge', $context->market);
            $this->assertSame($host, $context->host);
            $this->assertSame('ka', $context->locale);
            $this->assertSame('GEL', $context->currency);
            $this->assertSame('ge', $context->catalogScope);
            $this->assertSame('ge', $context->pricingScope);
            $this->assertSame('GE', $context->demandRegion);
            $this->assertSame(['GE', 'TR', 'US'], $context->preferredProductRegions);
            $this->assertNotContains('yandex_market', $context->salesChannels);
        }
    }

    public function test_explicit_english_locale_does_not_change_georgia_market_commerce(): void
    {
        $this->get('https://tsipruli.ge/theme/consortium?locale=en')
            ->assertRedirect()
            ->assertHeader('X-Market', 'ge')
            ->assertHeader('X-Pricing-Scope', 'ge')
            ->assertHeader('X-Display-Currency', 'GEL')
            ->assertHeader('Content-Language', 'en');

        $context = market();
        $this->assertSame('ge', $context->market);
        $this->assertSame('ka', $context->locale);
        $this->assertSame('GEL', $context->currency);
        $this->assertSame('ge', $context->catalogScope);
        $this->assertSame('ge', $context->pricingScope);
        $this->assertSame('GE', $context->demandRegion);
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
            $this->assertContains('yandex_market', $context->salesChannels);
        }
    }

    public function test_yandex_market_channel_is_only_allowed_for_russia_market(): void
    {
        $expectations = [
            'meanly.ru' => true,
            'meanly.one' => false,
            'digitienda.ar' => false,
            'tsipruli.ge' => false,
        ];

        foreach ($expectations as $host => $allowed) {
            $this->get("https://{$host}/theme/consortium")->assertRedirect();

            $this->assertSame(
                $allowed,
                in_array('yandex_market', market()->salesChannels, true),
                "Unexpected yandex_market market eligibility for {$host}."
            );
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

    public function test_meanly_one_catalog_footer_uses_english_copy(): void
    {
        $this->withHeader('Accept-Language', 'ru,en;q=0.8')
            ->withSession(['locale' => 'ru'])
            ->get('https://meanly.one/catalog')
            ->assertOk()
            ->assertHeader('X-Market', 'global')
            ->assertHeader('Content-Language', 'en')
            ->assertSee('All digital goods.')
            ->assertSee('Catalog filters')
            ->assertSee('Digital cards, game keys, and subscriptions with fast code delivery')
            ->assertSee('Marketplace')
            ->assertSee('For business')
            ->assertDontSee('Все цифровые товары.')
            ->assertDontSee('Фильтры каталога')
            ->assertDontSee('Маркетплейс цифровых карт')
            ->assertDontSee('Для бизнеса');
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

    public function test_meanly_ru_catalog_uses_russian_copy(): void
    {
        $this->withHeader('Accept-Language', 'en,ru;q=0.8')
            ->withSession(['locale' => 'en'])
            ->get('https://meanly.ru/catalog')
            ->assertOk()
            ->assertHeader('X-Market', 'ru')
            ->assertHeader('Content-Language', 'ru')
            ->assertSee('Все цифровые товары.')
            ->assertSee('Фильтры каталога')
            ->assertSee('Маркетплейс цифровых карт')
            ->assertSee('Для бизнеса')
            ->assertDontSee('All digital goods.')
            ->assertDontSee('Catalog filters');
    }
}
