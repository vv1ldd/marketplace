<?php

namespace App\Filament\Kernel\Resources\DirectChannelResource\Pages;

use App\Filament\Kernel\Resources\DirectChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDirectChannel extends EditRecord
{
    protected static string $resource = DirectChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
