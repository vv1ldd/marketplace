<?php

namespace App\Filament\Treasury\Resources\MappingCountryResource\Pages;

use App\Filament\Treasury\Resources\MappingCountryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMappingCountries extends ListRecords
{
    protected static string $resource = MappingCountryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
