<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class MarketplaceDiscoveryExplanationService
{
    /**
     * This hook is intentionally constrained to products already selected by
     * deterministic ranking. A future LLM adapter can enrich this text, but it
     * must not receive provider SKU, voucher, ledger or private integration data.
     *
     * @param array<string, mixed> $intent
     * @param Collection<int, array{product: Product, score: int, reasons: array<int, string>}> $matches
     */
    public function explain(array $intent, Collection $matches): ?string
    {
        if (($intent['raw'] ?? '') === '' || $matches->isEmpty()) {
            return null;
        }

        $safeMatches = $matches->take(3)->map(fn (array $match) => [
            'name' => $match['product']->name,
            'category' => $match['product']->category,
            'price_rub' => ((float) $match['product']->price_rub) / 100,
            'reasons' => $match['reasons'],
        ]);

        $names = $safeMatches->pluck('name')->implode(', ');

        return "Мы подобрали товары по совпадению платформы, региона, типа продукта и близости цены. Лучшие варианты: {$names}.";
    }
}
