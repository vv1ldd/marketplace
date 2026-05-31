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
                ->assertHeader('Content-Language', 'es');

            $context = market();
            $this->assertSame('latam_ar', $context->market);
            $this->assertSame($host, $context->host);
            $this->assertSame('es', $context->locale);
            $this->assertSame('ARS', $context->currency);
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
                ->assertHeader('Content-Language', 'ru');

            $context = market();
            $this->assertSame('ru', $context->market);
            $this->assertSame($host, $context->host);
            $this->assertSame('RUB', $context->currency);
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
            $this->get("https://{$host}/theme/consortium")
                ->assertRedirect()
                ->assertHeader('X-Market', 'global')
                ->assertHeader('Content-Language', 'en');

            $context = market();
            $this->assertSame('global', $context->market);
            $this->assertSame('en', $context->locale);
            $this->assertSame('USD', $context->currency);
        }
    }
}
