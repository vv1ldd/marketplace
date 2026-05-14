<?php

namespace App\Filament\Kernel\Resources\ProviderProducts\Pages;

use App\Filament\Kernel\Resources\ProviderProducts\ProviderProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderProduct extends EditRecord
{
    protected static string $resource = ProviderProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
