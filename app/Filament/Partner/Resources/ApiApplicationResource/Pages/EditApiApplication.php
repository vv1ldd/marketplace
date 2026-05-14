<?php

namespace App\Filament\Partner\Resources\ApiApplicationResource\Pages;

use App\Filament\Partner\Resources\ApiApplicationResource;
use Filament\Resources\Pages\EditRecord;

class EditApiApplication extends EditRecord
{
    protected static string $resource = ApiApplicationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
