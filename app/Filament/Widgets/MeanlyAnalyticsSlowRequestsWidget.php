<?php

namespace App\Filament\Widgets;

use App\Models\MeanlyAnalyticsEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MeanlyAnalyticsSlowRequestsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Где тормозит';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MeanlyAnalyticsEvent::query()
                    ->where('is_slow', true)
                    ->latest('occurred_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Когда')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_ms')
                    ->label('Latency')
                    ->formatStateUsing(fn ($state): string => ((int) $state).' ms')
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 3000 ? 'danger' : 'warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('surface')
                    ->label('Surface')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_name')
                    ->label('Событие')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('route_name')
                    ->label('Route')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->limit(56),

                Tables\Columns\TextColumn::make('status_code')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 500 ? 'danger' : ((int) $state >= 400 ? 'warning' : 'gray')),
            ])
            ->defaultPaginationPageOption(10);
    }
}
