<?php

namespace App\Services;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Models\TokenMeteringEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TokenMeteringService
{
    public function meter(
        LegalEntity $legalEntity,
        string $eventType,
        ?Model $source = null,
        float $quantity = 1.0,
        ?Shop $shop = null,
        array $metadata = [],
        ?float $estimatedValueRub = null,
        ?Carbon $occurredAt = null,
    ): TokenMeteringEvent {
        $tariff = $this->tariff($eventType);
        $tariffKey = $metadata['tariff_key'] ?? $eventType;
        $idempotencyKey = $metadata['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existing = TokenMeteringEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $quantity = max(0.0, $quantity);
        $sl1Amount = round(
            array_key_exists('sl1_amount', $metadata)
                ? (float) $metadata['sl1_amount']
                : (float) ($tariff['sl1_per_unit'] ?? 0) * $quantity,
            4
        );
        $rubEquivalent = round($sl1Amount * $this->rubRate(), 2);
        $layer = (string) ($tariff['layer'] ?? 'usage');
        $unit = (string) ($tariff['unit'] ?? 'event');
        $occurredAt ??= now();

        return DB::transaction(function () use (
            $legalEntity,
            $eventType,
            $source,
            $quantity,
            $shop,
            $metadata,
            $estimatedValueRub,
            $occurredAt,
            $tariff,
            $tariffKey,
            $idempotencyKey,
            $sl1Amount,
            $rubEquivalent,
            $layer,
            $unit
        ) {
            $event = TokenMeteringEvent::create([
                'legal_entity_id' => $legalEntity->id,
                'shop_id' => $shop?->id,
                'event_type' => $eventType,
                'layer' => $layer,
                'tariff_key' => $tariffKey,
                'tariff_version' => $this->tariffVersion(),
                'idempotency_key' => $idempotencyKey,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'quantity' => $quantity,
                'unit' => $unit,
                'sl1_amount' => $sl1Amount,
                'rub_equivalent' => $rubEquivalent,
                'estimated_value_rub' => $estimatedValueRub,
                'metadata' => array_merge($metadata, [
                    'tariff' => $tariff,
                    'currency' => $this->currency(),
                ]),
                'occurred_at' => $occurredAt,
            ]);

            $ledger = app(LedgerService::class)->record(
                $shop,
                $layer === 'commerce' ? 'TOKEN_COMMERCE_METERED' : 'TOKEN_USAGE_METERED',
                $source ?? $event,
                [
                    'token_metering_event_id' => $event->id,
                    'event_type' => $eventType,
                    'layer' => $layer,
                    'tariff_key' => $tariffKey,
                    'tariff_version' => $this->tariffVersion(),
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'sl1_amount' => $sl1Amount,
                    'rub_equivalent' => $rubEquivalent,
                    'estimated_value_rub' => $estimatedValueRub,
                    'metadata' => $metadata,
                ],
                $legalEntity,
                'SYSTEM:SL1_METERING',
                [
                    'source_type' => $source ? $source::class : null,
                    'source_id' => $source?->getKey(),
                ],
                [
                    'sl1_amount' => $sl1Amount,
                    'rub_equivalent' => $rubEquivalent,
                ]
            );

            $event->update(['sovereign_ledger_id' => $ledger->id]);

            return $event->refresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function summaryForLegalEntity(LegalEntity $legalEntity, int $days = 30): array
    {
        $query = TokenMeteringEvent::query()
            ->where('legal_entity_id', $legalEntity->id)
            ->where('occurred_at', '>=', now()->subDays($days));

        $usageSl1 = (float) (clone $query)->where('layer', 'usage')->sum('sl1_amount');
        $commerceSl1 = (float) (clone $query)->where('layer', 'commerce')->sum('sl1_amount');
        $estimatedValueRub = (float) (clone $query)->sum('estimated_value_rub');
        $totalSl1 = $usageSl1 + $commerceSl1;
        $totalRub = $totalSl1 * $this->rubRate();

        return [
            'period_days' => $days,
            'usage_sl1' => round($usageSl1, 4),
            'commerce_sl1' => round($commerceSl1, 4),
            'ai_audit_sl1' => round((float) (clone $query)->whereIn('event_type', ['ai_audit_run', 'ai_audit_object'])->sum('sl1_amount'), 4),
            'fee_load_sl1' => round((float) (clone $query)->whereIn('event_type', ['marketplace_usage_fee', 'marketplace_fixed_fee', 'marketplace_success_fee', 'channel_publish_fee'])->sum('sl1_amount'), 4),
            'total_sl1' => round($totalSl1, 4),
            'total_rub_equivalent' => round($totalRub, 2),
            'estimated_value_rub' => round($estimatedValueRub, 2),
            'roi' => $totalRub > 0 ? round($estimatedValueRub / $totalRub, 2) : 0.0,
            'events_count' => (clone $query)->count(),
            'by_event_type' => $this->byEventType($query),
            'recommendations' => $this->recommendationMetrics($legalEntity, $days),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function globalSummary(int $days = 30): array
    {
        $query = TokenMeteringEvent::query()
            ->where('occurred_at', '>=', now()->subDays($days));

        $totalSl1 = (float) (clone $query)->sum('sl1_amount');
        $estimatedValueRub = (float) (clone $query)->sum('estimated_value_rub');
        $totalRub = $totalSl1 * $this->rubRate();

        return [
            'period_days' => $days,
            'total_sl1' => round($totalSl1, 4),
            'total_rub_equivalent' => round($totalRub, 2),
            'usage_sl1' => round((float) (clone $query)->where('layer', 'usage')->sum('sl1_amount'), 4),
            'commerce_sl1' => round((float) (clone $query)->where('layer', 'commerce')->sum('sl1_amount'), 4),
            'estimated_value_rub' => round($estimatedValueRub, 2),
            'roi' => $totalRub > 0 ? round($estimatedValueRub / $totalRub, 2) : 0.0,
            'events_count' => (clone $query)->count(),
            'legal_entities_count' => (clone $query)->distinct('legal_entity_id')->count('legal_entity_id'),
            'by_event_type' => $this->byEventType($query),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recommendationMetrics(LegalEntity $legalEntity, int $days = 30): array
    {
        $query = TokenMeteringEvent::query()
            ->where('legal_entity_id', $legalEntity->id)
            ->where('occurred_at', '>=', now()->subDays($days))
            ->whereIn('event_type', ['recommendation_generated', 'recommendation_used', 'recommendation_hit']);

        $generated = (clone $query)->where('event_type', 'recommendation_generated')->count();
        $used = (clone $query)->where('event_type', 'recommendation_used')->count();
        $hits = (clone $query)->where('event_type', 'recommendation_hit')->count();
        $costSl1 = (float) (clone $query)->sum('sl1_amount');
        $valueRub = (float) (clone $query)->sum('estimated_value_rub');
        $costRub = $costSl1 * $this->rubRate();

        return [
            'generated_count' => $generated,
            'used_count' => $used,
            'hit_count' => $hits,
            'hit_rate' => $used > 0 ? round($hits / $used, 4) : 0.0,
            'sl1_cost' => round($costSl1, 4),
            'rub_equivalent' => round($costRub, 2),
            'estimated_value_rub' => round($valueRub, 2),
            'roi' => $costRub > 0 ? round($valueRub / $costRub, 2) : 0.0,
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<TokenMeteringEvent> $query
     * @return array<int, array<string, mixed>>
     */
    private function byEventType($query): array
    {
        return (clone $query)
            ->select('event_type', DB::raw('COUNT(*) as events_count'), DB::raw('SUM(sl1_amount) as sl1_amount'), DB::raw('SUM(estimated_value_rub) as estimated_value_rub'))
            ->groupBy('event_type')
            ->orderByDesc('sl1_amount')
            ->get()
            ->map(fn (TokenMeteringEvent $row) => [
                'event_type' => $row->event_type,
                'events_count' => (int) $row->events_count,
                'sl1_amount' => round((float) $row->sl1_amount, 4),
                'estimated_value_rub' => round((float) $row->estimated_value_rub, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function tariff(string $eventType): array
    {
        return config("sl1_tokenomics.tariffs.{$eventType}", [
            'layer' => 'usage',
            'unit' => 'event',
            'sl1_per_unit' => 0.0000,
        ]);
    }

    private function tariffVersion(): string
    {
        return (string) config('sl1_tokenomics.tariff_version', 'dev');
    }

    private function currency(): string
    {
        return (string) config('sl1_tokenomics.currency', 'SL1');
    }

    private function rubRate(): float
    {
        return (float) config('sl1_tokenomics.rub_rate', 100.0);
    }
}
