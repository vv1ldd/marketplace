<?php

namespace App\Filament\Partner\Resources\ApiApplicationResource\Pages;

use App\Filament\Partner\Resources\ApiApplicationResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Facades\Filament;

class CreateApiApplication extends CreateRecord
{
    protected static string $resource = ApiApplicationResource::class;



    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
