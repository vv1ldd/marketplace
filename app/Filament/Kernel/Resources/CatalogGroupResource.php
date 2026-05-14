<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\CatalogGroupResource\Pages;
use App\Filament\Resources\CatalogGroups\Schemas\CatalogGroupForm;
use App\Filament\Resources\CatalogGroups\Tables\CatalogGroupsTable;
use App\Models\CatalogGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CatalogGroupResource extends Resource
{
    protected static ?string $model = CatalogGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог и Контент';
    }

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return CatalogGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatalogGroups::route('/'),
            'create' => Pages\CreateCatalogGroup::route('/create'),
            'edit' => Pages\EditCatalogGroup::route('/{record}/edit'),
        ];
    }
}
