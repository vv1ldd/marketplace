<?php

namespace App\Services;

use App\Models\Shop;
use App\Support\MarketContext;
use App\Support\PricingContext;
use Carbon\CarbonInterface;

class MerchantWorkspacePresentationService
{
    public function __construct(
        private readonly MarketContextResolver $markets,
        private readonly PricingContextResolver $pricingContext,
        private readonly PricingProjectionService $pricing,
    ) {}

    public function prefersEnglish(?MarketContext $market = null): bool
    {
        $market ??= $this->market();

        return ($market->locale ?? 'en') === 'en'
            || $market->market === 'global';
    }

    public function market(): MarketContext
    {
        return market();
    }

    public function pricing(): PricingContext
    {
        return $this->pricingContext->resolve($this->market());
    }

    public function displayCurrency(): string
    {
        return $this->pricing()->displayCurrency;
    }

    /**
     * @return array{amount: float, currency: string, label: string, storage_amount: float, storage_currency: string, pricing_scope: string}
     */
    public function formatMoney(float $rubAmount): array
    {
        $price = $this->pricing->publicPriceForStorageAmount(round($rubAmount, 2), $this->pricing());

        if ($this->prefersEnglish()) {
            $price['currency'] = $this->displayCurrency();
            $price['label'] = $this->pricing->format($price);
        }

        return $price;
    }

    public function formatMoneyLabel(float $rubAmount): string
    {
        return $this->formatMoney($rubAmount)['label'];
    }

    public function formatDate(?CarbonInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        return $this->prefersEnglish()
            ? $date->timezone(config('app.timezone'))->format('M j, Y g:i A')
            : $date->format('d.m.Y H:i');
    }

    /**
     * @return array{
     *     available: float,
     *     reserved: float,
     *     total: float,
     *     currency: string,
     *     storage_currency: string,
     *     available_formatted: string,
     *     reserved_formatted: string,
     *     total_formatted: string
     * }
     */
    public function financeBalances(float $available, float $reserved, ?float $total = null): array
    {
        $total ??= $available + $reserved;

        return [
            'available' => $available,
            'reserved' => $reserved,
            'total' => $total,
            'currency' => $this->displayCurrency(),
            'storage_currency' => $this->pricing()->storageCurrency,
            'available_formatted' => $this->formatMoneyLabel($available),
            'reserved_formatted' => $this->formatMoneyLabel($reserved),
            'total_formatted' => $this->formatMoneyLabel($total),
        ];
    }

    public function shopRegionLabel(Shop $shop): string
    {
        if ($this->prefersEnglish()) {
            return strtoupper((string) (config('markets.markets.global.demand_region') ?: 'GLOBAL'));
        }

        return strtoupper((string) ($shop->shop_region ?: 'RU'));
    }

    public function sovereignRequestTypeLabel(string $type): string
    {
        if ($this->prefersEnglish()) {
            return $type === 'top_up' ? 'Balance top-up' : 'Credit line';
        }

        return $type === 'top_up' ? 'Пополнение баланса' : 'Кредитная линия';
    }

    public function sovereignRequestStatusLabel(string $status): string
    {
        if ($this->prefersEnglish()) {
            return match ($status) {
                'pending' => 'Waiting for admin signature',
                'approved' => 'Executed successfully',
                'rejected' => 'Rejected',
                default => $status,
            };
        }

        return match ($status) {
            'pending' => 'Ожидает подписи админа',
            'approved' => 'Успешно исполнен ✅',
            'rejected' => 'Отклонен ❌',
            default => $status,
        };
    }

    /**
     * @return array{name: string, description: string}
     */
    public function categoryCardAllProducts(): array
    {
        return $this->prefersEnglish()
            ? [
                'name' => 'All products',
                'description' => 'All provider listings you can add to your assortment.',
            ]
            : [
                'name' => 'Все товары',
                'description' => 'Все доступные позиции поставщиков, которые можно взять в продажу.',
            ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{name: string, description: string}
     */
    public function categoryCardFromTaxonomy(string $slug, array $meta): array
    {
        if ($this->prefersEnglish()) {
            return [
                'name' => (string) ($meta['label_en'] ?? $meta['label_ru'] ?? $slug),
                'description' => (string) ($meta['description_en'] ?? $meta['description_ru'] ?? 'Browse providers and available denominations in this category.'),
            ];
        }

        return [
            'name' => (string) ($meta['label_ru'] ?? $meta['label_en'] ?? $slug),
            'description' => (string) ($meta['description_ru'] ?? 'Открыть поставщиков и доступные номиналы в этой категории.'),
        ];
    }

    /**
     * @return array{name: string, description: string}
     */
    public function categoryCardUnmapped(): array
    {
        return $this->prefersEnglish()
            ? [
                'name' => 'Unmapped',
                'description' => 'Products without a canonical category yet.',
            ]
            : [
                'name' => 'Неразобранное',
                'description' => 'Товары без canonical category. Их надо постепенно разнести маппингами.',
            ];
    }

    public function depositAmountLabel(): string
    {
        return $this->prefersEnglish()
            ? 'Amount ('.$this->displayCurrency().')'
            : 'Сумма (RUB)';
    }

    public function ledgerCaption(): string
    {
        return $this->prefersEnglish()
            ? 'Merchant '.$this->displayCurrency().' ledger'
            : 'Merchant RUB ledger';
    }

    public function convertDisplayAmountToRub(float $displayAmount): float
    {
        $displayCurrency = $this->displayCurrency();

        if ($displayCurrency === $this->pricing()->storageCurrency) {
            return round($displayAmount, 2);
        }

        return round(app(FinanceService::class)->convert($displayAmount, $displayCurrency, 'RUB'), 2);
    }
}
