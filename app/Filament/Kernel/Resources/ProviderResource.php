<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\ProviderResource\Pages\CreateProvider;
use App\Filament\Kernel\Resources\ProviderResource\Pages\EditProvider;
use App\Filament\Kernel\Resources\ProviderResource\Pages\ListProviders;
use App\Filament\Kernel\Resources\ProviderResource\RelationManagers\ProviderProductsRelationManager;
use App\Filament\Kernel\Resources\ProviderResource\Schemas\ProviderForm;
use App\Filament\Kernel\Resources\ProviderResource\Tables\ProvidersTable;
use App\Models\Provider;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cloud-arrow-down';

    public static function getNavigationGroup(): ?string
    {
        return 'Каталог и Контент';
    }

    protected static ?int $navigationSort = 32;

    public static function getLabel(): ?string
    {
        return __('admin.products.provider');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.products.providers');
    }

    public static function form(Schema $schema): Schema
    {
        return ProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProvidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProviderProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviders::route('/'),
            'create' => CreateProvider::route('/create'),
            'edit' => EditProvider::route('/{record}/edit'),
        ];
    }
}
