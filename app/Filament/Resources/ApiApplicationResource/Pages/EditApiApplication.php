<?php

namespace App\Filament\Resources\ApiApplicationResource\Pages;

use App\Filament\Resources\ApiApplicationResource;
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
