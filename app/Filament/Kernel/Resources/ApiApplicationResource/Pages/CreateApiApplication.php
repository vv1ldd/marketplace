<?php

namespace App\Filament\Kernel\Resources\ApiApplicationResource\Pages;

use App\Filament\Kernel\Resources\ApiApplicationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateApiApplication extends CreateRecord
{
    protected static string $resource = ApiApplicationResource::class;
}
