<?php

namespace App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages;

use App\Filament\Kernel\Resources\ProviderCategoryMappings\ProviderCategoryMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderCategoryMappings extends ListRecords
{
    protected static string $resource = ProviderCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
