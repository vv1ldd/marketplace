<?php

namespace App\Filament\Widgets;

use App\Models\MeanlyAnalyticsEvent;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MeanlyAnalyticsOverviewWidget extends BaseWidget
{
    protected ?string $heading = 'Meanly Analytics';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = now()->subHours(24);

        $base = MeanlyAnalyticsEvent::query()->where('occurred_at', '>=', $since);
        $requests = (clone $base)->where('event_type', 'http');
        $sessionCount = (clone $base)->whereNotNull('session_hash')->distinct('session_hash')->count('session_hash');
        $visitorCount = (clone $base)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash');
        $errorsCount = (clone $base)->whereIn('severity', ['error', 'critical'])->count();
        $slowCount = (clone $base)->where('is_slow', true)->count();
        $avgLatency = (int) round((float) ((clone $requests)->whereNotNull('duration_ms')->avg('duration_ms') ?? 0));

        return [
            Stat::make('Активные сессии 24ч', (string) max($sessionCount, $visitorCount))
                ->description('Хэшированные session/browser ids без PII')
                ->color('info'),

            Stat::make('Ошибки 24ч', (string) $errorsCount)
                ->description('HTTP 5xx, JS errors, AI/provider exceptions')
                ->color($errorsCount > 0 ? 'danger' : 'success'),

            Stat::make('Медленные места 24ч', (string) $slowCount)
                ->description('Запросы и операции дольше 1200 ms')
                ->color($slowCount > 0 ? 'warning' : 'success'),

            Stat::make('Средняя latency', $avgLatency.' ms')
                ->description('По серверным HTTP событиям')
                ->color($avgLatency >= 1200 ? 'warning' : 'gray'),
        ];
    }
}
