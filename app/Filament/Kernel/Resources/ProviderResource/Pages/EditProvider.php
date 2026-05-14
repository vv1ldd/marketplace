<?php

namespace App\Filament\Kernel\Resources\ProviderResource\Pages;

use App\Filament\Kernel\Resources\ProviderResource;
use Filament\Resources\Pages\EditRecord;

class EditProvider extends EditRecord
{
    protected static string $resource = ProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\DeleteAction::make(),
        ];
    }
}
