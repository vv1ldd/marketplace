<?php

namespace App\Filament\Treasury\Resources\CurrencyPairResource\Pages;

use App\Filament\Treasury\Resources\CurrencyPairResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCurrencyPairs extends ListRecords
{
    protected static string $resource = CurrencyPairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
