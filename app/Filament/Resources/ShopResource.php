<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShopResource\Pages\CreateShop;
use App\Filament\Resources\ShopResource\Pages\EditShop;
use App\Filament\Resources\ShopResource\Pages\ListShops;
use App\Filament\Resources\ShopResource\Schemas\ShopForm;
use App\Filament\Resources\ShopResource\Tables\ShopsTable;
use App\Models\Shop;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationLabel(): string
    {
        return __('admin.shops.shops');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 20;

    public static function getLabel(): ?string
    {
        return __('admin.shops.shop');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.shops.shops');
    }

    public static function form(Schema $schema): Schema
    {
        return ShopForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShopsTable::configure($table);
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
            'index' => ListShops::route('/'),
            'create' => CreateShop::route('/create'),
            'edit' => EditShop::route('/{record}/edit'),
        ];
    }
}
