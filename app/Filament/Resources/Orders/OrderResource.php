<?php

namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\RelationManagers\OrderCommentsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShoppingBag;
    protected static ?string $navigationLabel = 'Заказы';
    protected static ?int $navigationSort = 1;

    protected static ?string $label = 'Заказа';
    protected static ?string $pluralLabel = 'Заказы';
    protected static bool $hasTitleCaseModelLabel = false;

//    protected static ?string $navigationBadgeTooltip = 'Кол-во не обработанных заказов';

    public static function getNavigationBadge(): ?string
    {
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');

        $query = static::$model::where('progress_id', '<>', 4);

        if ($is_executor) {
            $query->where('assigned_user_id', auth()->user()->id);
        }

        if($is_support) {
            $query->where('is_problem', true)->where('progress_id', '<>', 4);
        }

        return $query->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            'comments' => OrderCommentsRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
