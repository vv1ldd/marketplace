<?php

namespace App\Filament\Widgets;

use App\Models\CatalogSearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class PopularSearchQueriesWidget extends BaseWidget
{
    protected static ?string $heading = '🔥 Популярные поисковые запросы';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 11;

    public function table(Table $table): Table
    {
        // Query to find the most frequent search queries and their purchase attributions
        $sub = CatalogSearchLog::query()
            ->leftJoin('orders', 'catalog_search_logs.id', '=', 'orders.search_log_id')
            ->select([
                DB::raw("MIN(catalog_search_logs.id) as id"),
                'catalog_search_logs.normalized_query',
                DB::raw("COUNT(DISTINCT catalog_search_logs.id) as search_count"),
                DB::raw("COUNT(DISTINCT orders.id) as sales_count"),
                DB::raw("ROUND(COALESCE(SUM(orders.total_amount), 0), 2) as total_gmv"),
                DB::raw("ROUND(AVG(catalog_search_logs.results_count), 0) as avg_results"),
                DB::raw("MAX(catalog_search_logs.created_at) as last_searched_at"),
            ])
            ->groupBy('catalog_search_logs.normalized_query')
            ->orderByDesc('search_count')
            ->limit(10);

        $query = CatalogSearchLog::query()->fromSub($sub, 'catalog_search_logs');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('normalized_query')
                    ->label('Поисковый запрос')
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('search_count')
                    ->label('Поиски')
                    ->badge()
                    ->color('info')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('sales_count')
                    ->label('Продажи')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('total_gmv')
                    ->label('Выручка (GMV)')
                    ->money('RUB')
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('conversion_rate')
                    ->label('Конверсия')
                    ->state(fn ($record) => $record->search_count > 0 ? round(($record->sales_count / $record->search_count) * 100, 1) . '%' : '0%')
                    ->badge()
                    ->color(fn ($state) => (float)$state > 10.0 ? 'success' : ((float)$state > 0.0 ? 'warning' : 'gray'))
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('avg_results')
                    ->label('Ср. результатов')
                    ->numeric()
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('last_searched_at')
                    ->label('Последний поиск')
                    ->dateTime()
                    ->color('gray'),
            ])
            ->emptyStateHeading('Поисковых запросов нет')
            ->emptyStateDescription('Здесь будут отображаться самые частые запросы пользователей.')
            ->paginated(false);
    }
}
