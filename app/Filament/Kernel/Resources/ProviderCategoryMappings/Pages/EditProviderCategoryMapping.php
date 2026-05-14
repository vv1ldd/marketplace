<?php

namespace App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages;

use App\Filament\Kernel\Resources\ProviderCategoryMappings\ProviderCategoryMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderCategoryMapping extends EditRecord
{
    protected static string $resource = ProviderCategoryMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
