<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Partner\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Partner\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|null|\UnitEnum $navigationIcon = Heroicon::ShoppingBag;

    protected static ?string $navigationLabel = 'Заказы';

    protected static ?string $label = 'Заказ';

    protected static ?string $pluralLabel = 'Заказы';

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            // Partners usually only VIEW orders, but maybe EDIT progress
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
