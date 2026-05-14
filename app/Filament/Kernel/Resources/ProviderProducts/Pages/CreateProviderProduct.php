<?php

namespace App\Filament\Kernel\Resources\ProviderProducts\Pages;

use App\Filament\Kernel\Resources\ProviderProducts\ProviderProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProviderProduct extends CreateRecord
{
    protected static string $resource = ProviderProductResource::class;
}
