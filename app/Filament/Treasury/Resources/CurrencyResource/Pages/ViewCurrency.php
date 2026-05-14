<?php

namespace App\Filament\Treasury\Resources\CurrencyResource\Pages;

use App\Filament\Treasury\Resources\CurrencyResource;
use App\Filament\Widgets\CurrencyTruthChart;
use Filament\Resources\Pages\ViewRecord;

class ViewCurrency extends ViewRecord
{
    protected static string $resource = CurrencyResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CurrencyTruthChart::class,
        ];
    }
}
