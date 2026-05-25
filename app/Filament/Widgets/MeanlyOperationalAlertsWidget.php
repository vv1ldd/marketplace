<?php

namespace App\Filament\Widgets;

use App\Models\MeanlyOperationalAlert;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class MeanlyOperationalAlertsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return 'Активные operational alerts';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                MeanlyOperationalAlert::query()
                    ->where('status', 'open')
                    ->orderByRaw("field(severity, 'critical', 'error', 'warning', 'info')")
                    ->latest('last_seen_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical', 'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('surface')
                    ->label('Surface')
                    ->badge(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Alert')
                    ->weight('bold')
                    ->searchable()
                    ->description(fn (MeanlyOperationalAlert $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('occurrence_count')
                    ->label('Count')
                    ->badge()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('Last seen')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('context_summary')
                    ->label('Sample')
                    ->state(function (MeanlyOperationalAlert $record): string {
                        $sample = collect((array) data_get($record->context, 'sample', []))
                            ->take(3)
                            ->map(fn (array $row): string => ($row['order_id'] ?? '-') . ' · ' . ($row['age_minutes'] ?? '?') . ' мин')
                            ->implode(', ');

                        return $sample !== '' ? $sample : (string) ($record->context['last_event_name'] ?? '-');
                    })
                    ->wrap()
                    ->limit(120),
            ])
            ->emptyStateHeading('Активных alert нет')
            ->emptyStateDescription('Operational rules не нашли текущих проблем.')
            ->defaultPaginationPageOption(10);
    }
}
