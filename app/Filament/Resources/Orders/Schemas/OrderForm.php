<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ToggleColumn;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $is_create = !$schema->getRecord();
        $is_update = !$is_create;

        return $schema
            ->components([
                Section::make('Заказ')->schema([
                    TextInput::make('id')
                        ->label('Номер заказа')
                        ->disabled($is_update)
                        ->hidden($is_create)
                        ->required($is_create),
                    Select::make('user_id')
                        ->relationship('user', 'email')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->optionsLimit(50)
                        ->label('Юзер')
                ])->columnSpanFull(),

                Section::make('YM')->schema([
                    TextInput::make('order_id')
                        ->label('Заказ YM')
                        ->required(),
                    TextInput::make('status')
                        ->label('Статус YM')
                        ->required(),
                    TextInput::make('sub_status')
                        ->label('Подстатус YM')
                        ->required(),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('client_info.lastName')
                                ->label('Фамилия'),
                            TextInput::make('client_info.firstName')
                                ->label('Имя'),
                            TextInput::make('client_info.middleName')
                                ->label('Отчество'),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('client_info.email'),
                            TextInput::make('client_info.phone')
                                ->label('Телефон'),
                        ])


                ])->columnSpanFull(),

                Section::make('Товары в заказе')->schema([
                    Repeater::make('Товары в заказе')
                        ->relationship('items')
                        ->columns(3)
                        ->schema([
                            Grid::make(4)->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->columnSpan(3),
                                TextInput::make('count')
                                    ->label('Количество'),
                            ]),
                            
                            Grid::make()->schema([
                                Toggle::make('is_activated')
                                    ->inline(false)
                                    ->label('Активирован'),
                                Toggle::make('is_redeemed')
                                    ->inline(false)
                                    ->label('Код введен'),
                            ]),

                            Grid::make(4)->schema([
                                TextInput::make('client_info.first_name')
                                    ->label('Имя'),
                                TextInput::make('client_info.last_name')
                                    ->label('Фамилия'),
                                TextInput::make('client_info.email')
                                    ->label('Email'),
                                TextInput::make('client_info.phone')
                                    ->label('Телефон'),
                            ])->columnSpanFull()


                        ])
                        ->hiddenLabel(true)
                ])->columnSpanFull(),


            ]);
    }
}
