<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Str;

class MerchantProductPresentationService
{
    public function __construct(
        private readonly CanonicalCategoryResolver $categoryResolver,
        private readonly MarketContextResolver $marketContextResolver,
    ) {}

    public function categoryLabel(Product $product): string
    {
        $slug = $this->categoryResolver->forProduct($product);
        $meta = (array) config("catalog_taxonomy.categories.{$slug}", []);
        $useEnglish = $this->prefersEnglish($product->shop);

        $label = $useEnglish
            ? ($meta['label_en'] ?? $meta['label_ru'] ?? null)
            : ($meta['label_ru'] ?? $meta['label_en'] ?? null);

        if (filled($label)) {
            return (string) $label;
        }

        return Str::headline(str_replace('_', ' ', $slug ?: 'other'));
    }

    /**
     * @return array{amount: float, currency: string, label: string}
     */
    public function listPrice(Product $product): array
    {
        $amount = (float) ($product->purchase_price ?: 0);
        $currency = strtoupper((string) ($product->purchase_currency ?: 'USD'));

        if ($amount <= 0) {
            $amount = round(((float) ($product->price_rub ?? 0)) / 100, 2);
            $currency = $this->prefersEnglish($product->shop) ? 'USD' : 'RUB';
        }

        $amount = round($amount, 2);

        return [
            'amount' => $amount,
            'currency' => $currency,
            'label' => number_format($amount, 2, '.', ' ').' '.$currency,
        ];
    }

    private function prefersEnglish(?\App\Models\Shop $shop = null): bool
    {
        $market = $shop
            ? $this->marketContextResolver->resolveForShop($shop)
            : market();

        return ($market->locale ?? 'en') === 'en'
            || ($market->market ?? '') === 'global';
    }
}
