<?php

namespace App\Services;

use App\Models\Product;
use App\Support\PricingContext;

class PricingProjectionService
{
    public function __construct(
        private readonly FinanceService $finance,
    ) {}

    /**
     * @return array{amount: float, currency: string, label: string, storage_amount: float, storage_currency: string, pricing_scope: string}
     */
    public function publicPriceForProduct(Product $product, ?PricingContext $context = null): array
    {
        $context ??= pricing();
        $storageAmount = round(((float) ($product->price_rub ?? 0)) / 100, 2);

        return $this->publicPriceForStorageAmount($storageAmount, $context);
    }

    /**
     * @return array{amount: float, currency: string, label: string, storage_amount: float, storage_currency: string, pricing_scope: string}
     */
    public function publicPriceForStorageAmount(float $storageAmount, ?PricingContext $context = null): array
    {
        $context ??= pricing();
        $storageAmount = round($storageAmount, 2);
        $displayAmount = $storageAmount;

        if ($storageAmount > 0 && $context->displayCurrency !== $context->storageCurrency) {
            $converted = $this->finance->convert($storageAmount, $context->storageCurrency, $context->displayCurrency);
            $displayAmount = $converted > 0 ? round($converted, 2) : $storageAmount;
        }

        $currency = $displayAmount === $storageAmount && $context->displayCurrency !== $context->storageCurrency
            ? $context->storageCurrency
            : $context->displayCurrency;
        $price = [
            'amount' => $displayAmount,
            'currency' => $currency,
            'storage_amount' => $storageAmount,
            'storage_currency' => $context->storageCurrency,
            'pricing_scope' => $context->pricingScope,
        ];

        return $price + ['label' => $this->format($price)];
    }

    public function format(array $price): string
    {
        $amount = (float) ($price['amount'] ?? 0);
        $currency = strtoupper((string) ($price['currency'] ?? ''));
        $formatted = number_format($amount, 2, '.', ' ');

        return $currency === 'RUB'
            ? $formatted.' ₽'
            : trim($formatted.' '.$currency);
    }
}
