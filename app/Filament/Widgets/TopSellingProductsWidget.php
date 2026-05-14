<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopSellingProductsWidget extends BaseWidget
{
    protected static ?string $heading = '📊 Топ Лидеров Продаж (Частота)';
    
    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        $sub = DB::table('order_items')
            ->select([
                DB::raw('MIN(order_items.id) as id'),
                'order_items.sku',
                DB::raw('SUM(order_items.count) as total_sold'),
                DB::raw('COALESCE(MAX(products.name), MAX(wildflow_catalogs.sku), "Unknown Product") as product_name'),
            ])
            ->leftJoin('wildflow_catalogs', 'order_items.sku', '=', 'wildflow_catalogs.sku')
            ->leftJoin('products', 'order_items.sku', '=', 'products.sku')
            ->groupBy('order_items.sku')
            ->orderByDesc('total_sold')
            ->limit(10);

        $query = \App\Models\Order\OrderItems::query()->fromSub($sub, 'order_items');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Название товара')
                    ->weight('bold')
                    ->wrap(),
                
                Tables\Columns\TextColumn::make('sku')
                    ->label('Артикул (SKU)')
                    ->copyable()
                    ->fontFamily('mono'),
                
                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Продано (шт.)')
                    ->badge()
                    ->color('success')
                    ->alignment('right'),
            ])
            ->paginated(false);
    }
}
