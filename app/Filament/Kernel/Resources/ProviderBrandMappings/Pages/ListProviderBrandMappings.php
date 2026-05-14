<?php

namespace App\Filament\Kernel\Resources\ProviderBrandMappings\Pages;

use App\Filament\Kernel\Resources\ProviderBrandMappings\ProviderBrandMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderBrandMappings extends ListRecords
{
    protected static string $resource = ProviderBrandMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
