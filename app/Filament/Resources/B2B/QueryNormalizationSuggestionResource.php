<?php

namespace App\Filament\Resources\B2B;

use App\Models\QueryNormalizationSuggestion;
use App\Models\QueryNormalizationRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class QueryNormalizationSuggestionResource extends Resource
{
    protected static ?string $model = QueryNormalizationSuggestion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static ?string $navigationLabel = 'Рекомендации нормализации';

    public static function getLabel(): ?string
    {
        return 'Рекомендация нормализации';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Рекомендации нормализации';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 17;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->label('Исходный текст')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Рекомендуемый каноник')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Уверенность')
                    ->numeric(2)
                    ->badge()
                    ->color(fn ($state) => $state >= 0.9 ? 'success' : ($state >= 0.7 ? 'warning' : 'gray'))
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Причина')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('approve')
                    ->label('Утвердить')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function (QueryNormalizationSuggestion $record) {
                        QueryNormalizationRule::create([
                            'match_type' => 'transliteration',
                            'source' => $record->source,
                            'target' => $record->target,
                            'priority' => 10,
                            'is_active' => true,
                        ]);
                        $record->update(['status' => QueryNormalizationSuggestion::STATUS_APPROVED]);
                    })
                    ->visible(fn ($record) => $record->status === QueryNormalizationSuggestion::STATUS_PENDING),

                Action::make('reject')
                    ->label('Отклонить')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (QueryNormalizationSuggestion $record) {
                        $record->update(['status' => QueryNormalizationSuggestion::STATUS_REJECTED]);
                    })
                    ->visible(fn ($record) => $record->status === QueryNormalizationSuggestion::STATUS_PENDING),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('confidence', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\B2B\Pages\ListQueryNormalizationSuggestions::route('/'),
        ];
    }
}
