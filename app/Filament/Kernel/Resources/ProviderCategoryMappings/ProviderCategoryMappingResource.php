<?php

namespace App\Filament\Kernel\Resources\ProviderCategoryMappings;

use App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages\CreateProviderCategoryMapping;
use App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages\EditProviderCategoryMapping;
use App\Filament\Kernel\Resources\ProviderCategoryMappings\Pages\ListProviderCategoryMappings;
use App\Filament\Kernel\Resources\ProviderCategoryMappings\Schemas\ProviderCategoryMappingForm;
use App\Filament\Kernel\Resources\ProviderCategoryMappings\Tables\ProviderCategoryMappingsTable;
use App\Models\ProviderCategoryMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderCategoryMappingResource extends Resource
{
    protected static ?string $model = ProviderCategoryMapping::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;


    public static function getNavigationGroup(): ?string
    {
        return __('sovereign.navigation.groups.liquidity');
    }

    public static function getNavigationLabel(): string
    {
        return __('sovereign.navigation.mappings');
    }

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'provider_category_name';

    public static function form(Schema $schema): Schema
    {
        return ProviderCategoryMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderCategoryMappingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderCategoryMappings::route('/'),
            'create' => CreateProviderCategoryMapping::route('/create'),
            'edit' => EditProviderCategoryMapping::route('/{record}/edit'),
        ];
    }
}
