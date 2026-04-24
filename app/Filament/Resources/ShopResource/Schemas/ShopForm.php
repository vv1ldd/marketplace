<?php

namespace App\Filament\Resources\ShopResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShopForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Основная информация')->schema([
                TextInput::make('name')
                    ->label('Название магазина')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Тип магазина')
                    ->options([
                        \App\Models\Shop::TYPE_VOUCHERS => 'Ваучеры',
                        \App\Models\Shop::TYPE_GAMES    => 'Игры',
                        \App\Models\Shop::TYPE_BOTH     => 'Ваучеры + Игры',
                    ])
                    ->default(\App\Models\Shop::TYPE_VOUCHERS)
                    ->live()
                    ->required(),
                TextInput::make('domain')
                    ->label('Домен')
                    ->placeholder('example.com')
                    ->prefix('https://'),
                TextInput::make('voucher_prefix')
                    ->label('Префикс ваучеров')
                    ->placeholder('SHOP-')
                    ->maxLength(10),
                TextInput::make('ps_tax')
                    ->label('Наценка PS Tax (%)')
                    ->numeric()
                    ->default(35)
                    ->visible(fn (callable $get) => in_array($get('type'), [
                        \App\Models\Shop::TYPE_GAMES,
                        \App\Models\Shop::TYPE_BOTH
                    ]))
                    ->required(),
                TextInput::make('ps_tax_for_sites')
                    ->label('Наценка PS Tax для сайтов (%)')
                    ->numeric()
                    ->default(35)
                    ->visible(fn (callable $get) => in_array($get('type'), [
                        \App\Models\Shop::TYPE_GAMES,
                        \App\Models\Shop::TYPE_BOTH
                    ]))
                    ->required(),
                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true),
            ]),
            
            Section::make('Интеграция Яндекс.Маркет')->schema([
                TextInput::make('business_id')
                    ->label('Business ID')
                    ->required(),
                TextInput::make('campaign_id')
                    ->label('Campaign ID')
                    ->required(),
                TextInput::make('api_key')
                    ->label('API Key')
                    ->password()
                    ->revealable()
                    ->required(),
                TextInput::make('notification_token')
                    ->label('Токен уведомлений')
                    ->password()
                    ->revealable()
                    ->required(),
            ]),

            Section::make('Автоматизация')->schema([
                Toggle::make('auto_purchase_enabled')
                    ->label('Автозакупка включена')
                    ->default(true),
            ]),
        ]);
    }
}
