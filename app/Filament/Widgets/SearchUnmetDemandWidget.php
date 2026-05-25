<?php

namespace App\Filament\Widgets;

use App\Models\CatalogSearchLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class SearchUnmetDemandWidget extends BaseWidget
{
    protected static ?string $heading = '🔍 Упущенный спрос (0 результатов)';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        // Query to find searches that yielded exactly 0 results, grouped by normalized query
        $sub = CatalogSearchLog::query()
            ->select([
                DB::raw("MIN(id) as id"),
                'normalized_query',
                DB::raw("COUNT(*) as search_count"),
                DB::raw("MAX(created_at) as last_searched_at"),
            ])
            ->where('results_count', 0)
            ->groupBy('normalized_query')
            ->orderByDesc('search_count')
            ->limit(10);

        $query = CatalogSearchLog::query()->fromSub($sub, 'catalog_search_logs');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('normalized_query')
                    ->label('Поисковый запрос (0 совпадений)')
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('search_count')
                    ->label('Кол-во поисков')
                    ->badge()
                    ->color('danger')
                    ->alignment('center'),

                Tables\Columns\TextColumn::make('last_searched_at')
                    ->label('Последний поиск')
                    ->dateTime()
                    ->color('gray'),
            ])
            ->emptyStateHeading('Упущенного спроса нет!')
            ->emptyStateDescription('Все поисковые запросы вернули хотя бы один канонический товар.')
            ->paginated(false);
    }
}
