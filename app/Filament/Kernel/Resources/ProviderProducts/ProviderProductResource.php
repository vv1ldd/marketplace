<?php

namespace App\Filament\Kernel\Resources\ProviderProducts;

use App\Filament\Kernel\Resources\ProviderProducts\Pages\CreateProviderProduct;
use App\Filament\Kernel\Resources\ProviderProducts\Pages\EditProviderProduct;
use App\Filament\Kernel\Resources\ProviderProducts\Pages\ListProviderProducts;
use App\Filament\Kernel\Resources\ProviderProducts\Schemas\ProviderProductForm;
use App\Filament\Kernel\Resources\ProviderProducts\Tables\ProviderProductsTable;
use App\Models\ProviderProduct;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderProductResource extends Resource
{
    protected static ?string $model = ProviderProduct::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getLabel(): ?string
    {
        return __('admin.products.provider_product');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.products.provider_products');
    }

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ProviderProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderProductsTable::configure($table);
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
            'index' => ListProviderProducts::route('/'),
            'create' => CreateProviderProduct::route('/create'),
            'edit' => EditProviderProduct::route('/{record}/edit'),
        ];
    }
}
