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
                Select::make('legal_entity_id')
                    ->label('Юр. лицо (Владелец)')
                    ->relationship('legalEntity', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Выберите организацию'),
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
                TextInput::make('redeem_url')
                    ->label('Кастомная ссылка Redeem')
                    ->placeholder('https://partner.ru/redeem')
                    ->url()
                    ->helperText('Необязательно. Если пусто, используется стандартная ссылка хаба.'),
                TextInput::make('store_api_token')
                    ->label('API Токен сайта (Storefront)')
                    ->password()
                    ->revealable()
                    ->placeholder('Введите токен для выгрузки товаров'),
                \Filament\Forms\Components\Textarea::make('ip_whitelist')
                    ->label('Белый список IP')
                    ->placeholder('1.2.3.4, 5.6.7.8')
                    ->helperText('IP-адреса сайта магазина через запятую. Если пусто, проверка отключена.'),
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

            Section::make('Почта (SMTP)')->schema([
                Toggle::make('use_custom_smtp')
                    ->label('Использовать собственные настройки SMTP')
                    ->helperText('Если выключено, будут использоваться стандартные настройки платформы.')
                    ->live(),

                \Filament\Schemas\Components\Grid::make(3)
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.example.com'),
                        TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->placeholder('587'),
                        TextInput::make('smtp_encryption')
                            ->label('Шифрование')
                            ->placeholder('tls/ssl'),
                    ])
                    ->visible(fn (callable $get) => $get('use_custom_smtp')),

                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        TextInput::make('smtp_user')
                            ->label('SMTP User')
                            ->placeholder('user@example.com'),
                        TextInput::make('smtp_password')
                            ->label('SMTP Password')
                            ->password()
                            ->revealable(),
                    ])
                    ->visible(fn (callable $get) => $get('use_custom_smtp')),

                \Filament\Schemas\Components\Grid::make(2)
                    ->schema([
                        TextInput::make('smtp_from_address')
                            ->label('Email отправителя')
                            ->placeholder('no-reply@shop.com'),
                        TextInput::make('smtp_from_name')
                            ->label('Имя отправителя')
                            ->placeholder('My Shop Support'),
                        TextInput::make('smtp_subject')
                            ->label('Тема письма')
                            ->placeholder('Ваш код активации')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (callable $get) => $get('use_custom_smtp')),
            ])->collapsed(),

            Section::make('Уведомления Telegram')->schema([
                TextInput::make('telegram_bot_token')
                    ->label('Telegram Bot Token')
                    ->password()
                    ->revealable()
                    ->placeholder('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11'),
                TextInput::make('telegram_chat_id')
                    ->label('Telegram Chat ID')
                    ->placeholder('-100123456789'),
            ])->collapsed(),
        ]);
    }
}
