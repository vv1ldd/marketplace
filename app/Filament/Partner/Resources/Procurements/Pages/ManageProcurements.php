<?php

namespace App\Filament\Partner\Resources\Procurements\Pages;

use App\Filament\Partner\Resources\Procurements\ProcurementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProcurements extends ManageRecords
{
    protected static string $resource = ProcurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
