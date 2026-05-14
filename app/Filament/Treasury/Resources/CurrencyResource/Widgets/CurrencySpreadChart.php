<?php

namespace App\Filament\Treasury\Resources\CurrencyResource\Widgets;

use App\Models\Currency;
use App\Models\CurrencyHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CurrencySpreadChart extends ChartWidget
{
    protected ?string $heading = 'Волатильность спреда (%)';
    
    // Делаем виджет на всю ширину
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        // Берем USD как основной эталон спреда
        $usd = Currency::where('code', 'USD')->first();
        
        if (!$usd) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Получаем историю за последние 30 записей
        $history = CurrencyHistory::where('currency_id', $usd->id)
            ->orderBy('record_date', 'asc')
            ->limit(30)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Спред USD к ЦБ РФ (%)',
                    'data' => $history->pluck('spread_percent')->toArray(),
                    'fill' => 'start',
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                ],
            ],
            'labels' => $history->pluck('record_date')->map(fn($date) => $date->format('d.m'))->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
