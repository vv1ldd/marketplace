<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\SellerTerminalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSellerTerminals extends ListRecords
{
    protected static string $resource = SellerTerminalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
