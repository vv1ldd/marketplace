<?php

namespace App\Filament\Kernel\Resources\DirectChannelResource\Pages;

use App\Filament\Kernel\Resources\DirectChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDirectChannels extends ListRecords
{
    protected static string $resource = DirectChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
