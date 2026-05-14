<?php

namespace App\Filament\Kernel\Resources\SystemSettings\Pages;

use App\Filament\Kernel\Resources\SystemSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSystemSettings extends ManageRecords
{
    protected static string $resource = SystemSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
