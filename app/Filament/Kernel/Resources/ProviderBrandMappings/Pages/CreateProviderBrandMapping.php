<?php

namespace App\Filament\Kernel\Resources\ProviderBrandMappings\Pages;

use App\Filament\Kernel\Resources\ProviderBrandMappings\ProviderBrandMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProviderBrandMapping extends CreateRecord
{
    protected static string $resource = ProviderBrandMappingResource::class;
}
