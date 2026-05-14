<?php

namespace App\Filament\Treasury\Resources\LiquidityCorridors\Pages;

use App\Filament\Treasury\Resources\LiquidityCorridors\LiquidityCorridorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLiquidityCorridors extends ManageRecords
{
    protected static string $resource = LiquidityCorridorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
