<?php

namespace App\Filament\Kernel\Resources\ProviderProducts\Pages;

use App\Filament\Kernel\Resources\ProviderProducts\ProviderProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProviderProducts extends ListRecords
{
    protected static string $resource = ProviderProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
