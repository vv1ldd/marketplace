<?php

namespace App\Filament\Resources\ProviderResource\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основная информация')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required(),
                        Select::make('type')
                            ->label('Тип (Драйвер)')
                            ->options([
                                'wildflow' => 'Wildflow',
                                'playstation' => 'PlayStation Store',
                            ])
                            ->required()
                            ->live(),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->inline(false),
                    ]),
                ]),

            Section::make('Настройки подключения (Credentials)')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('credentials.api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->visible(fn (Get $get) => $get('type') === 'wildflow'),
                        TextInput::make('credentials.business_id')
                            ->label('Business ID')
                            ->visible(fn (Get $get) => $get('type') === 'wildflow'),
                        
                        // For PlayStation, maybe we store DB connection or something else here
                        TextInput::make('credentials.host')
                            ->label('Database Host')
                            ->visible(fn (Get $get) => $get('type') === 'playstation'),
                    ]),
                ]),

            Section::make('Настройки синхронизации')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('settings.tax')
                            ->label('Наценка (%)')
                            ->numeric()
                            ->default(35),
                        TextInput::make('settings.currency_rate')
                            ->label('Курс валюты (к рублю)')
                            ->numeric()
                            ->helperText('Если пусто, будет использоваться биржевой курс'),
                    ]),
                ]),
        ]);
    }
}
