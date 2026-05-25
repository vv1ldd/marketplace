<?php

namespace App\Filament\Resources\B2B;

use App\Filament\Resources\B2B\Pages\ListCatalogSearchLogs;
use App\Models\CatalogSearchLog;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class CatalogSearchLogResource extends Resource
{
    protected static ?string $model = CatalogSearchLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'Поисковые запросы';

    public static function getLabel(): ?string
    {
        return 'Поисковый запрос';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Поисковые запросы';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make('Детали поискового запроса')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('created_at')
                            ->label('Дата и время')
                            ->disabled(),
                        TextInput::make('source')
                            ->label('Источник')
                            ->disabled(),
                        TextInput::make('query')
                            ->label('Поисковый запрос')
                            ->disabled(),
                        TextInput::make('normalized_query')
                            ->label('Нормализованный запрос')
                            ->disabled(),
                    ]),

                Section::make('Анализ интента и фильтров')
                    ->columnSpan(1)
                    ->schema([
                        TextInput::make('intent')
                            ->label('Распознанный интент')
                            ->disabled(),
                        TextInput::make('confidence')
                            ->label('Уверенность распознавания')
                            ->disabled(),
                        TextInput::make('results_count')
                            ->label('Кол-во результатов')
                            ->disabled(),
                        KeyValue::make('filters')
                            ->label('Извлеченные фильтры')
                            ->disabled(),
                    ]),

                Section::make('Атрибутированные продажи (Commerce GMV)')
                    ->columnSpan('full')
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('orders')
                            ->relationship('orders')
                            ->schema([
                                TextInput::make('order_id')
                                    ->label('Номер заказа')
                                    ->disabled(),
                                TextInput::make('total_amount')
                                    ->label('Сумма (RUB)')
                                    ->disabled(),
                                TextInput::make('status')
                                    ->label('Статус')
                                    ->disabled(),
                            ])
                            ->columns(3)
                            ->disabled()
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                    ])
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Дата/Время')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('query')
                    ->label('Оригинальный запрос')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('normalized_query')
                    ->label('Нормализованный запрос')
                    ->searchable()
                    ->wrap()
                    ->color('gray'),

                TextColumn::make('source')
                    ->label('Источник')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'storefront' => 'success',
                        'llm_retrieval' => 'info',
                        'llm_understanding' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('intent')
                    ->label('Интент')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('confidence')
                    ->label('Уверенность')
                    ->numeric(2)
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('results_count')
                    ->label('Найдено результатов')
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'success')
                    ->sortable(),

                TextColumn::make('orders_count')
                    ->label('Продажи')
                    ->badge()
                    ->counts('orders')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('Источник')
                    ->options([
                        'storefront' => 'Storefront',
                        'llm_retrieval' => 'LLM Retrieval',
                        'llm_understanding' => 'LLM Understanding',
                    ]),

                TernaryFilter::make('zero_results')
                    ->label('Результаты')
                    ->placeholder('Все запросы')
                    ->trueLabel('Только без результатов (0)')
                    ->falseLabel('Только с результатами (>0)')
                    ->queries(
                        true: fn ($query) => $query->where('results_count', 0),
                        false: fn ($query) => $query->where('results_count', '>', 0),
                    ),
            ])
            ->actions([
                \Filament\Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCatalogSearchLogs::route('/'),
        ];
    }
}
