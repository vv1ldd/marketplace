<?php

namespace App\Filament\Widgets;

use App\Models\CurrencyHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CurrencyTruthChart extends ChartWidget
{

    protected ?string $heading = 'Sovereign Truth Layers (4-Tier)';
    
    public ?\App\Models\Currency $record = null;
    public ?string $currencyId = null;

    protected function getData(): array
    {
        $id = $this->currencyId ?: ($this->record ? $this->record->id : null);
        
        $query = CurrencyHistory::query()
            ->when($id, fn($q) => $q->where('currency_id', $id))
            ->orderBy('record_date', 'asc')
            ->limit(30);

        $data = $query->get();

        return [
            'datasets' => [
                [
                    'label' => 'Official (CB)',
                    'data' => $data->pluck('official_rate')->toArray(),
                    'borderColor' => '#94a3b8', // Gray
                    'fill' => false,
                ],
                [
                    'label' => 'TradFi (Forex)',
                    'data' => $data->pluck('tradfi_rate')->toArray(),
                    'borderColor' => '#3b82f6', // Blue
                    'fill' => false,
                ],
                [
                    'label' => 'Spot (USDT)',
                    'data' => $data->pluck('spot_rate')->toArray(),
                    'borderColor' => '#6366f1', // Indigo
                    'fill' => false,
                ],
                [
                    'label' => 'P2P (Shadow)',
                    'data' => $data->pluck('p2p_rate')->toArray(),
                    'borderColor' => '#ef4444', // Red
                    'fill' => 'origin',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
            'labels' => $data->pluck('record_date')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
