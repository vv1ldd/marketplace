<?php

namespace App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages;

use App\Filament\Kernel\Resources\ProviderCategoryMappings\ProviderCategoryMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProviderCategoryMapping extends CreateRecord
{
    protected static string $resource = ProviderCategoryMappingResource::class;
}
