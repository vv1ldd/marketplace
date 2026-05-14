<?php

namespace App\Filament\Audit\Widgets;

use App\Models\SovereignLedger;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class LedgerBreakdownWidget extends ChartWidget
{

    protected ?string $heading = 'Анализ событий MDK (Sovereign Ledger)';

    protected function getData(): array
    {
        $data = SovereignLedger::select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->get();

        $colors = [
            'ORDER_RECEIVE' => '#3b82f6', // Blue
            'FINANCE_HOLD' => '#f59e0b',  // Amber
            'STOCK_REPLENISH' => '#10b981', // Green
            'STOCK_RESERVE' => '#6366f1',  // Indigo
            'FINANCE_CAPTURE' => '#059669', // Emerald
            'FINANCE_RELEASE' => '#ef4444', // Red
            'STOCK_LIQUIDATE' => '#b91c1c', // Dark Red
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Количество событий',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => $data->pluck('event_type')->map(fn($type) => $colors[$type] ?? '#9ca3af')->toArray(),
                ],
            ],
            'labels' => $data->pluck('event_type')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
