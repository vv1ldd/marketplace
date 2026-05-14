<?php

namespace App\Filament\Kernel\Resources\CatalogGroupResource\Pages;

use App\Filament\Kernel\Resources\CatalogGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCatalogGroups extends ListRecords
{
    protected static string $resource = CatalogGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
