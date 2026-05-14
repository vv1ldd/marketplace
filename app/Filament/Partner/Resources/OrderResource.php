<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Partner\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Partner\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\OrderCommentsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order\Order;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        
        return parent::getEloquentQuery()
            ->whereHas('shop', function ($query) use ($tenant) {
                $query->where('legal_entity_id', $tenant->id);
            });
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.orders');
    }

    public static function getLabel(): string
    {
        return __('admin.orders.order');
    }

    public static function getPluralLabel(): string
    {
        return __('admin.navigation.orders');
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table)
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }

    public static function getRelations(): array
    {
        return [
            OrderCommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
