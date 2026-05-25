<?php

namespace App\Filament\Resources\B2B\Pages;

use App\Filament\Resources\B2B\SellerTerminalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSellerTerminal extends EditRecord
{
    protected static string $resource = SellerTerminalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
