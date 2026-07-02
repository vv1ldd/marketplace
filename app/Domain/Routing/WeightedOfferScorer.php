<?php

namespace App\Domain\Routing;

use Illuminate\Support\Collection;

class WeightedOfferScorer
{
    public function __construct(
        private readonly RoutingCircuitBreaker $circuitBreaker,
        private readonly ProviderMetricsProviderInterface $metricsProvider,
    ) {}

    /**
     * @param  array<string, mixed>  $offer
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @param  array<int, int>  $excludeProviderIds
     */
    public function score(
        array $offer,
        Collection $candidates,
        RoutingPolicy $policy,
        array $excludeProviderIds = [],
    ): float {
        $providerId = $this->providerId($offer);

        if ($providerId > 0 && (
            in_array($providerId, $excludeProviderIds, true)
            || $this->circuitBreaker->isTripped($providerId, $policy)
        )) {
            return 0.0;
        }

        $signals = $this->rawSignals($offer, $candidates, $providerId);
        $normalized = $this->normalizeSignals($signals, $candidates, $policy);

        return round(
            ($policy->weight('margin') * $normalized['margin'])
            + ($policy->weight('success_rate') * $normalized['success_rate'])
            + ($policy->weight('latency') * $normalized['latency'])
            + ($policy->weight('stock') * $normalized['stock']),
            6,
        );
    }

    public function providerId(array $offer): int
    {
        $providerId = (int) data_get($offer, 'provider_id', 0);
        if ($providerId > 0) {
            return $providerId;
        }

        return (int) data_get($offer, 'seller.shop_id', 0);
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array{margin: float, success_rate: float, latency: float, stock: float}
     */
    private function rawSignals(array $offer, Collection $candidates, int $providerId): array
    {
        $metrics = (array) data_get($offer, 'ranking.metrics', []);

        $buyerCents = (int) data_get($offer, 'margin.buyer_price_cents', 0);
        $purchaseCents = (int) data_get($offer, 'margin.purchase_price_cents', 0);
        if ($buyerCents > 0 && $purchaseCents > 0 && $buyerCents > $purchaseCents) {
            $margin = ($buyerCents - $purchaseCents) / $buyerCents;
        } else {
            $margin = $this->inversePriceSignal($offer, $candidates);
        }

        $providerSignals = $this->metricsProvider->getSignalsForProvider($providerId);
        $successRate = $providerSignals->successRate;
        $latencyMs = (float) $providerSignals->p50LatencyMs;
        $stock = (float) ($metrics['stock_count'] ?? 0);

        return [
            'margin' => max(0.0, min(1.0, $margin)),
            'success_rate' => max(0.0, min(1.0, $successRate)),
            'latency' => max(1.0, $latencyMs),
            'stock' => max(0.0, $stock),
        ];
    }

    /**
     * @param  array{margin: float, success_rate: float, latency: float, stock: float}  $signals
     * @param  Collection<int, array<string, mixed>>  $candidates
     * @return array{margin: float, success_rate: float, latency: float, stock: float}
     */
    private function normalizeSignals(array $signals, Collection $candidates, RoutingPolicy $policy): array
    {
        $marginValues = $candidates
            ->map(fn (array $candidate): float => $this->rawSignals($candidate, $candidates, $this->providerId($candidate))['margin'])
            ->all();
        $stockValues = $candidates
            ->map(fn (array $candidate): float => $this->rawSignals($candidate, $candidates, $this->providerId($candidate))['stock'])
            ->all();

        $maxLatencyMs = max(1000, (int) config('routing.metrics.max_latency_ms', 30000));
        $latencyScore = 1.0 - (min($signals['latency'], (float) $maxLatencyMs) / (float) $maxLatencyMs);

        return [
            'margin' => $this->normalizeHigherIsBetter($signals['margin'], $marginValues),
            'success_rate' => max(0.0, min(1.0, $signals['success_rate'])),
            'latency' => max(0.0, min(1.0, $latencyScore)),
            'stock' => $this->normalizeHigherIsBetter($signals['stock'], $stockValues),
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function normalizeHigherIsBetter(float $value, array $values): float
    {
        $min = min($values);
        $max = max($values);

        if ($max <= $min) {
            return 1.0;
        }

        return ($value - $min) / ($max - $min);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function normalizeLowerIsBetter(float $value, array $values): float
    {
        $min = min($values);
        $max = max($values);

        if ($max <= $min) {
            return 1.0;
        }

        return 1.0 - (($value - $min) / ($max - $min));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $candidates
     */
    private function inversePriceSignal(array $offer, Collection $candidates): float
    {
        $price = (float) data_get($offer, 'price.amount', data_get($offer, 'ranking.metrics.price_rub', 0));
        if ($price <= 0) {
            return 0.0;
        }

        $prices = $candidates
            ->map(fn (array $candidate): float => (float) data_get(
                $candidate,
                'price.amount',
                data_get($candidate, 'ranking.metrics.price_rub', PHP_FLOAT_MAX),
            ))
            ->filter(fn (float $candidatePrice): bool => $candidatePrice > 0)
            ->values()
            ->all();

        if ($prices === []) {
            return 0.0;
        }

        $min = min($prices);
        $max = max($prices);
        if ($max <= $min) {
            return 1.0;
        }

        return 1.0 - (($price - $min) / ($max - $min));
    }
}
