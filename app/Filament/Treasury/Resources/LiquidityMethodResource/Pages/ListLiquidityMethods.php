<?php

namespace App\Filament\Treasury\Resources\LiquidityMethodResource\Pages;

use App\Filament\Treasury\Resources\LiquidityMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLiquidityMethods extends ListRecords
{
    protected static string $resource = LiquidityMethodResource::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
