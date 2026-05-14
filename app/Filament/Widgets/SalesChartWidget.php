<?php

namespace App\Filament\Widgets;

use App\Models\SovereignLedger;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesChartWidget extends ChartWidget
{

    protected ?string $heading = 'Динамика продаж (Sovereign Ledger)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected function getData(): array
    {
        // Выбираем продажи за последние 30 дней на основе FINANCE_CAPTURE
        $data = SovereignLedger::where('event_type', 'FINANCE_CAPTURE')
            ->where('entity_type', 'App\Models\Order\Order')
            ->join('order_items', 'sovereign_ledger.entity_id', '=', 'order_items.order_id')
            ->select(
                DB::raw('DATE(sovereign_ledger.created_at) as date'),
                DB::raw('SUM(order_items.price_rub * order_items.count) / 100 as total')
            )
            ->where('sovereign_ledger.created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Выручка (₽)',
                    'data' => $data->pluck('total')->toArray(),
                    'fill' => 'start',
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
            ],
            'labels' => $data->pluck('date')->map(fn($date) => \Carbon\Carbon::parse($date)->format('d.m'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        return 'Объем подтвержденных транзакций за последние 30 дней.';
    }
}
