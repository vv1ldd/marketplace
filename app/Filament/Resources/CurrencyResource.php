<?php

namespace App\Filament\Resources;

use App\Models\Currency;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Валюты';
    protected static ?string $modelLabel = 'Валюту';
    protected static ?string $pluralModelLabel = 'Валюты';
    protected static string|null|\UnitEnum $navigationGroup = 'Настройки';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Настройки валюты')
                    ->schema([
                        TextInput::make('code')
                            ->label('Код (ISO)')
                            ->required()
                            ->maxLength(3)
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('Название')
                            ->maxLength(255),
                        TextInput::make('rate_to_rub')
                            ->label('Авто-курс (к рублю)')
                            ->numeric()
                            ->disabled()
                            ->helperText('Обновляется автоматически через Binance'),
                        TextInput::make('manual_rate')
                            ->label('Ручной курс (если задан, приоритетнее)')
                            ->numeric()
                            ->helperText('Оставьте пустым для авто-курса'),
                        Toggle::make('is_auto_update')
                            ->label('Авто-обновление')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Schema $table): Schema
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Код')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Название'),
                TextColumn::make('rate_to_rub')
                    ->label('Авто-курс')
                    ->numeric(decimalPlaces: 4)
                    ->color('gray'),
                TextColumn::make('manual_rate')
                    ->label('Ручной курс')
                    ->numeric(decimalPlaces: 4)
                    ->color('warning'),
                TextColumn::make('effective_rate')
                    ->label('Итоговый курс')
                    ->numeric(decimalPlaces: 4)
                    ->weight('bold')
                    ->color('success'),
                IconColumn::make('is_auto_update')
                    ->label('Авто')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CurrencyResource\Pages\ListCurrencies::route('/'),
        ];
    }
}
