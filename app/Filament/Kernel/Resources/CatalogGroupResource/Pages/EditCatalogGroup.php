<?php

namespace App\Filament\Kernel\Resources\CatalogGroupResource\Pages;

use App\Filament\Kernel\Resources\CatalogGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCatalogGroup extends EditRecord
{
    protected static string $resource = CatalogGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
