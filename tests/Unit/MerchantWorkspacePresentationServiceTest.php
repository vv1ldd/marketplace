<?php

namespace Tests\Unit;

use App\Services\MarketContextResolver;
use App\Services\MerchantWorkspacePresentationService;
use App\Support\MarketContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantWorkspacePresentationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_market_formats_balances_in_usd_without_ruble_symbol(): void
    {
        $this->bindMarket('global', 'en', 'USD');

        $service = app(MerchantWorkspacePresentationService::class);
        $formatted = $service->financeBalances(8625.54, 50.00, 8675.54);
        $zeroFormatted = $service->formatMoneyLabel(0);

        $this->assertSame('USD', $formatted['currency']);
        $this->assertSame('RUB', $formatted['storage_currency']);
        $this->assertStringNotContainsString('₽', $formatted['available_formatted']);
        $this->assertStringContainsString('USD', $formatted['available_formatted']);
        $this->assertStringNotContainsString('₽', $zeroFormatted);
        $this->assertStringContainsString('USD', $zeroFormatted);
        $this->assertSame('Amount (USD)', $service->depositAmountLabel());
    }

    public function test_ru_market_keeps_ruble_formatting(): void
    {
        $this->bindMarket('ru', 'ru', 'RUB');

        $formatted = app(MerchantWorkspacePresentationService::class)->financeBalances(8625.54, 50.00, 8675.54);

        $this->assertSame('RUB', $formatted['currency']);
        $this->assertStringContainsString('₽', $formatted['available_formatted']);
        $this->assertSame('Сумма (RUB)', app(MerchantWorkspacePresentationService::class)->depositAmountLabel());
    }

    public function test_global_category_cards_use_english_copy(): void
    {
        $this->bindMarket('global', 'en', 'USD');

        $service = app(MerchantWorkspacePresentationService::class);

        $this->assertSame('All products', $service->categoryCardAllProducts()['name']);
        $this->assertSame('Gift cards', $service->categoryCardFromTaxonomy('gift_cards', [
            'label_en' => 'Gift cards',
            'label_ru' => 'Подарочные карты',
        ])['name']);
    }

    private function bindMarket(string $market, string $locale, string $currency): void
    {
        app()->instance(
            MarketContext::class,
            app(MarketContextResolver::class)->resolveForMarketKey($market),
        );
        config(["markets.markets.{$market}.locale" => $locale, "markets.markets.{$market}.currency" => $currency]);
    }
}
