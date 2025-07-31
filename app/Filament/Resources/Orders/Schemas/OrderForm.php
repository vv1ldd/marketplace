<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ToggleColumn;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $order = $schema->getRecord();
        $is_create = !$order;
        $is_update = !$is_create;

        return $schema
            ->components([
                Section::make('Заказ')->schema([
                    Grid::make(3)->schema([
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
                            ->label('Юзер'),
                        Select::make('progress_id')
                            ->relationship('progress', 'name')
                            ->required()
                            ->searchable()
                            ->label('Прогресс по заказу')
                            ->preload(),
                        Textarea::make('comment')
                            ->label('Комментарий')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])

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
                                ->mask('+79999999999')
                                ->label('Телефон'),
                        ])


                ])->columnSpanFull(),

                Section::make('Товары в заказе')->schema([
                    Repeater::make('Товары в заказе')
                        ->relationship('items')
                        ->collapsible()
                        ->maxItems(100)
                        ->addActionLabel('Добавить товар')
                        ->minItems(1)
                        ->columns(2)
                        ->schema([

                            Grid::make(3)->schema([
                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->required(),
                                TextInput::make('count')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->default(1)
                                    ->label('Количество'),
                                Select::make('typeForm.id')
                                    ->relationship('typeForm', 'name')
                                    ->label('Тип формы'),
                            ]),

                            Grid::make(2)->schema([
                                Toggle::make('is_redeemed')
                                    ->default(false)
                                    ->inline(false)
                                    ->label('Код введен'),
                                Toggle::make('is_activated')
                                    ->inline(false)
                                    ->default(false)
                                    ->label('Активирован'),
                            ]),

                            DateTimePicker::make('activated_at')
                                ->label('Дата активации')
                                ->required(),


                            Grid::make(4)->schema([
                                TextInput::make('client_info.first_name')
                                    ->required()
                                    ->label('Имя'),
                                TextInput::make('client_info.last_name')
                                    ->required()
                                    ->label('Фамилия'),
                                TextInput::make('client_info.email')
                                    ->required()
                                    ->email()
                                    ->label('Email'),
                                TextInput::make('client_info.phone')
                                    ->required()
                                    ->mask('+79999999999')
                                    ->label('Телефон'),
                            ])->columnSpanFull(),

                            Section::make('Опция')
                                ->compact()
                                ->schema([
                                    Select::make('type_id')
                                        ->relationship('type', 'name')
                                        ->label('Тип заказа')
                                        ->live()
                                        ->required()
                                        ->afterStateUpdated(function (Get $get, Set $set) {
                                            $set('client_info.option.type_id', $get('type_id'));
                                        })
                                        ->default(1)
                                        ->preload()
                                        ->searchable(),
                                    DatePicker::make('client_info.option.ps_birthday')
                                        ->disabled(fn(Get $get) => $get('type_id') != 2)
                                        ->hidden(fn(Get $get) => $get('type_id') != 2)
                                        ->required(fn(Get $get) => $get('type_id') == 2)
                                        ->label('Дата рождения'),

                                    TextInput::make('client_info.option.ps_network_id')
                                        ->disabled(fn(Get $get) => $get('type_id') != 3)
                                        ->live()
                                        ->hidden(fn(Get $get) => $get('type_id') != 3)
                                        ->required(fn(Get $get) => $get('type_id') == 3)
                                        ->label('PS Network ID'),
                                    TextInput::make('client_info.option.ps_network_password')
                                        ->disabled(fn(Get $get) => $get('type_id') != 3)
                                        ->live(onBlur: true)
                                        ->hidden(fn(Get $get) => $get('type_id') != 3)
                                        ->required(fn(Get $get) => $get('type_id') == 3)
                                        ->label('PS Network Password'),
                                    TextInput::make('client_info.option.ps_2fa_code')
                                        ->live()
                                        ->disabled(fn(Get $get) => $get('type_id') != 3)
                                        ->hidden(fn(Get $get) => $get('type_id') != 3)
                                        ->label('Код 2FA'),

                                ])->columnSpanFull()
                        ])
                        ->hiddenLabel()
                ])->columnSpanFull(),


            ]);
    }
}
