<?php

namespace App\Filament\Kernel\Resources\ProviderBrandMappings;

use App\Filament\Kernel\Resources\ProviderBrandMappings\Pages\CreateProviderBrandMapping;
use App\Filament\Kernel\Resources\ProviderBrandMappings\Pages\EditProviderBrandMapping;
use App\Filament\Kernel\Resources\ProviderBrandMappings\Pages\ListProviderBrandMappings;
use App\Filament\Kernel\Resources\ProviderBrandMappings\Schemas\ProviderBrandMappingForm;
use App\Filament\Kernel\Resources\ProviderBrandMappings\Tables\ProviderBrandMappingsTable;
use App\Models\ProviderBrandMapping;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderBrandMappingResource extends Resource
{
    protected static ?string $model = ProviderBrandMapping::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'external_name';

    public static function getNavigationLabel(): string
    {
        return 'Маппинг брендов';
    }

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог и Контент';
    }

    protected static ?int $navigationSort = 32;

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereNull('brand_id')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return ProviderBrandMappingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderBrandMappingsTable::configure($table);
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
            'index' => ListProviderBrandMappings::route('/'),
            'create' => CreateProviderBrandMapping::route('/create'),
            'edit' => EditProviderBrandMapping::route('/{record}/edit'),
        ];
    }
}
