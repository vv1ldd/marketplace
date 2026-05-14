<?php

namespace App\Filament\Resources\DirectChannels\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DirectChannelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основное')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Название канала')
                                ->required()
                                ->columnSpan(1),
                            
                            Select::make('type')
                                ->label('Тип интеграции')
                                ->options([
                                    'telegram_bot' => 'Telegram Bot / Канал',
                                    'yandex_market' => 'Яндекс Маркет',
                                    'woocommerce' => 'WooCommerce',
                                    'offline' => 'Оффлайн',
                                ])
                                ->default('telegram_bot')
                                ->required()
                                ->live()
                                ->columnSpan(1),

                            Toggle::make('is_active')
                                ->label('Канал активен')
                                ->default(true)
                                ->columnSpan(1),
                        ]),
                    ]),

                Section::make('Ценообразование и Маржинальность')
                    ->description('Настройка стратегии минимальной накрутки для быстрых продаж.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('settings.margin_percent')
                                ->label('Чистая прибыль (%)')
                                ->numeric()
                                ->default(5)
                                ->suffix('%')
                                ->helperText('Желаемый процент минимальной накрутки поверх себестоимости.'),
                                
                            TextInput::make('settings.marketplace_fee_percent')
                                ->label('Комиссия площадки (%)')
                                ->numeric()
                                ->default(20)
                                ->suffix('%')
                                ->helperText('Примерная комиссия Маркета/Woo (налог). Будет заложена в цену.'),
                                
                            TextInput::make('settings.min_price')
                                ->label('Мин. цена продажи (₽)')
                                ->numeric()
                                ->default(300)
                                ->suffix('₽')
                                ->helperText('Ниже этой суммы товары выгружаться не будут.'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('settings.default_stock')
                                ->label('Остаток (Шт)')
                                ->numeric()
                                ->default(10)
                                ->helperText('Фиктивный или реальный остаток для витрины.'),
                                
                            TextInput::make('settings.ym_category_id')
                                ->label('ID Категории (YM)')
                                ->numeric()
                                ->visible(fn ($get) => $get('type') === 'yandex_market')
                                ->helperText('Например: 70301474 (Цифровые игры)'),
                        ]),
                    ]),

                Section::make('Интеграция Yandex Market')
                    ->description('Настройка интеграции с Яндекс Маркетом (DBS).')
                    ->visible(fn ($get) => $get('type') === 'yandex_market')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('business_id')
                                ->label('Business ID')
                                ->placeholder('1234567')
                                ->numeric()
                                ->helperText('Кабинет бизнеса в Яндексе'),

                            TextInput::make('campaign_id')
                                ->label('Campaign ID')
                                ->placeholder('149014578')
                                ->numeric()
                                ->helperText('ID кампании (магазина) в Яндексе'),
                        ]),
                    ]),

                Section::make('Интеграция WooCommerce')
                    ->description('Настройка автоматической синхронизации через REST API.')
                    ->visible(fn ($get) => $get('type') === 'woocommerce')
                    ->collapsible()
                    ->schema([
                        Grid::make(1)->schema([
                            TextInput::make('woo_api_url')
                                ->label('WooCommerce API URL')
                                ->placeholder('https://yourstore.com/wp-json/wc/v3/')
                                ->url()
                                ->helperText('Полный путь к REST API (обычно заканчивается на /wp-json/wc/v3/)'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('woo_consumer_key')
                                ->label('Consumer Key')
                                ->placeholder('ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
                            TextInput::make('woo_consumer_secret')
                                ->label('Consumer Secret')
                                ->password()
                                ->revealable()
                                ->placeholder('cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'),
                        ]),
                    ]),
                Section::make('Ассортимент')
                    ->description('Выберите, какие товары будут участвовать в этом канале продаж.')
                    ->schema([
                        Select::make('settings.assortment_type')
                            ->label('Режим выбора товаров')
                            ->options([
                                'all' => 'Все активные товары',
                                'providers' => 'По провайдерам',
                                'brands' => 'По брендам',
                                'manual' => 'Вручную (Pivot)',
                            ])
                            ->default('all')
                            ->live()
                            ->required(),

                        Select::make('settings.selected_providers')
                            ->label('Выберите провайдеров')
                            ->multiple()
                            ->searchable()
                            ->options(\App\Models\Provider::pluck('name', 'id'))
                            ->visible(fn ($get) => $get('settings.assortment_type') === 'providers')
                            ->required(),

                        Select::make('settings.selected_brands')
                            ->label('Выберите бренды')
                            ->multiple()
                            ->searchable()
                            ->options(\App\Models\Brand::pluck('name', 'id'))
                            ->visible(fn ($get) => $get('settings.assortment_type') === 'brands')
                            ->required(),
                    ]),

                Section::make('Интеграция Telegram Bot')
                    ->description('Настройка бота для авто-постинга ходовых товаров в Telegram-канал.')
                    ->visible(fn ($get) => $get('type') === 'telegram_bot')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('settings.telegram_bot_token')
                                ->label('Bot Token')
                                ->password()
                                ->revealable()
                                ->placeholder('1234567890:AAH...xxxxxxxxxxxxxxxx')
                                ->helperText('Токен, полученный у @BotFather'),

                            TextInput::make('settings.telegram_channel_id')
                                ->label('ID Канала / Chat ID')
                                ->placeholder('@my_sales_channel или -1001234567')
                                ->helperText('Бот должен быть администратором в этом канале с правом отправки сообщений.'),
                                
                            TextInput::make('settings.telegram_manager_username')
                                ->label('Username Менеджера (Для продаж)')
                                ->placeholder('MyStoreSupportBot или MyManagerName')
                                ->helperText('Куда перекидывать клиента при клике "Купить" (без @).'),
                        ]),
                        Grid::make(1)->schema([
                            \Filament\Forms\Components\Textarea::make('settings.sbp_details')
                                ->label('Реквизиты для оплаты (СБП)')
                                ->placeholder("СБП: +7 999 000-00-00\nБанк: Т-Банк\nПолучатель: Иван И.")
                                ->helperText('Текст, который бот автоматически пришлет покупателю для оплаты.'),
                                
                            TextInput::make('settings.telegram_message_template')
                                ->label('Шаблон сообщения (По умолчанию)')
                                ->default("🔥 Супер-цена!\n\n🎮 <b>{product_name}</b>\n💰 Всего за <b>{price} ₽</b>\n\n👉 <a href=\"{buy_link}\">Купить сейчас</a>")
                                ->helperText('Доступные переменные: {product_name}, {price}, {buy_link}, {region}. Поддерживается HTML (<b>жирный</b>, <i>курсив</i>, <a>ссылки</a>).')
                        ])
                    ]),
            ]);
    }
}
