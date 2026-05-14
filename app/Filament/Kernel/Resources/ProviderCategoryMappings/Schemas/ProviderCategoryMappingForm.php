<?php

namespace App\Filament\Kernel\Resources\ProviderCategoryMappings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\Provider;
use App\Models\CatalogGroup;
use App\Models\ProviderProduct;

class ProviderCategoryMappingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('provider_id')
                    ->label('Провайдер')
                    ->relationship('provider', 'name')
                    ->required()
                    ->reactive(),
                
                Select::make('provider_category_name')
                    ->label('Категория провайдера')
                    ->options(function (callable $get) {
                        $providerId = $get('provider_id');
                        if (!$providerId) return [];
                        
                        return ProviderProduct::where('provider_id', $providerId)
                            ->select('category')
                            ->distinct()
                            ->pluck('category', 'category')
                            ->toArray();
                    })
                    ->searchable()
                    ->required(),

                Select::make('catalog_group_id')
                    ->label('Наша Группа')
                    ->relationship('catalogGroup', 'name')
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->lazy()
                            ->afterStateUpdated(fn ($set, $state) => $set('slug', \Illuminate\Support\Str::slug($state))),
                        TextInput::make('slug')
                            ->required(),
                        TextInput::make('icon')
                            ->placeholder('🎮'),
                    ]),
            ]);
    }
}
