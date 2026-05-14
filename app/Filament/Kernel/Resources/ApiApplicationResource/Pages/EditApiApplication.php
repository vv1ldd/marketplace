<?php

namespace App\Filament\Kernel\Resources\ApiApplicationResource\Pages;

use App\Filament\Kernel\Resources\ApiApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiApplication extends EditRecord
{
    protected static string $resource = ApiApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
