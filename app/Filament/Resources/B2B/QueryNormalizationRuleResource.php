<?php

namespace App\Filament\Resources\B2B;

use App\Models\QueryNormalizationRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;

class QueryNormalizationRuleResource extends Resource
{
    protected static ?string $model = QueryNormalizationRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Нормализация запросов';

    public static function getLabel(): ?string
    {
        return 'Правило нормализации';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Нормализация запросов';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make('Детали правила нормализации')
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('match_type')
                                ->label('Тип преобразования')
                                ->options([
                                    'transliteration' => 'Транслитерация (стим -> steam)',
                                    'alias' => 'Синоним / Алиас (псн -> psn)',
                                    'abbreviation' => 'Аббревиатура (пс+ -> playstation plus)',
                                    'slang' => 'Жаргонизм / Сленг (плойка -> playstation)',
                                    'synonym' => 'Синоним',
                                ])
                                ->required(),

                            TextInput::make('priority')
                                ->label('Приоритет')
                                ->numeric()
                                ->default(10)
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('source')
                                ->label('Исходный текст (откуда)')
                                ->placeholder('стим')
                                ->required()
                                ->unique(ignorable: fn ($record) => $record)
                                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim($state))),

                            TextInput::make('target')
                                ->label('Канонический текст (куда)')
                                ->placeholder('steam')
                                ->required()
                                ->dehydrateStateUsing(fn ($state) => mb_strtolower(trim($state))),
                        ]),

                        Toggle::make('is_active')
                            ->label('Активно')
                            ->default(true),
                    ]),
            ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->label('Исходное выражение')
                    ->searchable()
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('target')
                    ->label('Каноническое выражение')
                    ->searchable()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('match_type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'transliteration' => 'info',
                        'alias' => 'success',
                        'abbreviation' => 'warning',
                        'slang' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Активно')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\B2B\Pages\ListQueryNormalizationRules::route('/'),
        ];
    }
}
