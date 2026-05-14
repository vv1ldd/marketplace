<?php

namespace App\Filament\Kernel\Resources\ProviderBrandMappings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProviderBrandMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('provider_id')
                    ->required()
                    ->numeric(),
                TextInput::make('external_name')
                    ->required(),
                TextInput::make('brand_id')
                    ->numeric(),
            ]);
    }
}
