<?php

namespace App\Filament\Widgets;

use App\Models\MeanlyAnalyticsEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MeanlyAnalyticsErrorsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Где ошибки';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MeanlyAnalyticsEvent::query()
                    ->whereIn('severity', ['error', 'critical'])
                    ->latest('occurred_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('Когда')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('surface')
                    ->label('Surface')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_name')
                    ->label('Событие')
                    ->searchable()
                    ->limit(44),

                Tables\Columns\TextColumn::make('route_name')
                    ->label('Route')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('status_code')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn ($state): string => (int) $state >= 500 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('error_class')
                    ->label('Class')
                    ->searchable()
                    ->limit(42),

                Tables\Columns\TextColumn::make('error_message')
                    ->label('Message')
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
            ])
            ->defaultPaginationPageOption(10);
    }
}
