<?php

namespace App\Filament\Kernel\Resources\ProviderBrandMappings\Pages;

use App\Filament\Kernel\Resources\ProviderBrandMappings\ProviderBrandMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderBrandMapping extends EditRecord
{
    protected static string $resource = ProviderBrandMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
