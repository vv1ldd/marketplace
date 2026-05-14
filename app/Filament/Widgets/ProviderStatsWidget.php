<?php

namespace App\Filament\Widgets;

use App\Models\Provider;
use App\Models\WildflowCatalog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class ProviderStatsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    public function getTableHeading(): string
    {
        return 'Отчет по провайдерам товаров';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Provider::query()->where('is_active', true)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Провайдер')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wildflow' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Товаров')
                    ->getStateUsing(function (Provider $record) {
                        if ($record->type === 'wildflow') {
                            return WildflowCatalog::where('is_active', true)->count();
                        }
                        return $record->products()->count();
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_retail')
                    ->label('Общая розница')
                    ->money('USD')
                    ->getStateUsing(function (Provider $record) {
                        if ($record->type === 'wildflow') {
                            return WildflowCatalog::where('is_active', true)->sum('retail_price');
                        }
                        return 0;
                    }),

                Tables\Columns\TextColumn::make('potential_profit')
                    ->label('Потенц. прибыль')
                    ->money('USD')
                    ->getStateUsing(function (Provider $record) {
                        if ($record->type === 'wildflow') {
                            $stats = WildflowCatalog::where('is_active', true)
                                ->select(DB::raw('SUM(retail_price - purchase_price) as profit'))
                                ->first();
                            return $stats->profit ?? 0;
                        }
                        return 0;
                    })
                    ->description('Разница розница/закупка')
                    ->color('success'),

                Tables\Columns\TextColumn::make('avg_margin')
                    ->label('Средняя маржа')
                    ->getStateUsing(function (Provider $record) {
                        if ($record->type === 'wildflow') {
                            $result = WildflowCatalog::where('is_active', true)
                                ->where('retail_price', '>', 0)
                                ->select(DB::raw('AVG(((retail_price - purchase_price) / retail_price) * 100) as avg_margin'))
                                ->first();
                            
                            return round($result->avg_margin ?? 0, 2) . '%';
                        }
                        return '-';
                    }),
            ])
            ->paginated(false);
    }
}
