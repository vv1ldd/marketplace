<?php

namespace App\Filament\Partner\Resources;

use App\Models\Shop;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use App\Http\Services\YmService;
use App\Models\Product;
use App\Models\Settings;
use App\Models\ApiApplication;
use Illuminate\Support\Str;

class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static bool $isScopedToTenant = true;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Магазины';

    protected static ?string $pluralLabel = 'Магазины';

    protected static ?string $label = 'Магазин';
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Settings')
                ->tabs([
                    Tab::make('Основное')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('name')
                                    ->label('Название магазина')
                                    ->required(),
                                TextInput::make('domain')
                                    ->label('Домен (без https://)')
                                    ->placeholder('myshop.com'),
                                TextInput::make('voucher_prefix')
                                    ->label('Префикс ваучеров')
                                    ->placeholder('WLD'),
                                Toggle::make('is_active')
                                    ->label('Магазин активен'),
                                Toggle::make('is_sandbox')
                                    ->label('Режим Sandbox (песочница)')
                                    ->helperText('В этом режиме все генерации и отгрузки товаров будут симулироваться без реальных списаний со счета'),
                            ]),
                            Section::make('Активация ваучеров (Redeem)')
                                ->schema([
                                    Toggle::make('use_custom_redeem_url')
                                        ->label('Свой URL активации')
                                        ->live(),
                                    TextInput::make('redeem_url')
                                        ->label('Ссылка на вашу страницу активации')
                                        ->url()
                                        ->visible(fn ($get) => (bool) $get('use_custom_redeem_url')),
                                ]),
                            Section::make('Контакты поддержки')
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('support_email')->label('Email поддержки'),
                                        TextInput::make('support_telegram')->label('Telegram поддержки')->prefix('https://t.me/'),
                                    ]),
                                ]),
                        ]),
                    Tab::make('Яндекс Маркет')
                        ->schema([
                            Grid::make(3)->schema([
                                TextInput::make('business_id')->label('Business ID')->numeric(),
                                TextInput::make('campaign_id')->label('Campaign ID')->numeric(),
                                TextInput::make('api_key')->label('API Key')->password()->revealable(),
                            ]),
                            Grid::make(3)->schema([
                                TextInput::make('markup_percent')->label('Наценка (%)')->numeric()->suffix('%'),
                                TextInput::make('ym_tax')->label('Налог Яндекс (%)')->numeric()->suffix('%'),
                                TextInput::make('ym_boost_percent')->label('Буст (%)')->numeric()->suffix('%'),
                            ]),
                            Select::make('ym_warehouse_id')
                                ->label('Склад Яндекс')
                                ->options(function(Shop $record) {
                                    if (!$record->api_key || !$record->business_id) return [];
                                    try {
                                        $service = new YmService($record);
                                        $warehouses = $service->getWarehouses();
                                        $options = [];
                                        foreach ($warehouses as $w) {
                                            $options[$w['id']] = $w['name'] . " (ID: {$w['id']})";
                                        }
                                        return $options;
                                    } catch (\Exception $e) {
                                        return [];
                                    }
                                })->searchable(),
                        ]),
                    Tab::make('Интеграции')
                        ->schema([
                            Section::make('Telegram Уведомления')
                                ->collapsed()
                                ->schema([
                                    TextInput::make('telegram_bot_token')->label('Bot Token')->password()->revealable(),
                                    TextInput::make('telegram_chat_id')->label('Chat ID'),
                                ]),
                            Section::make('SMTP')
                                ->collapsed()
                                ->schema([
                                    Toggle::make('use_custom_smtp')->label('Свой SMTP')->live(),
                                    Grid::make(2)->schema([
                                        TextInput::make('smtp_host')->label('Host'),
                                        TextInput::make('smtp_port')->label('Port')->numeric(),
                                        TextInput::make('smtp_user')->label('User'),
                                        TextInput::make('smtp_password')->label('Password')->password()->revealable(),
                                        Select::make('smtp_encryption')->label('Encryption')->options(['tls'=>'TLS', 'ssl'=>'SSL']),
                                    ])->visible(fn($get) => $get('use_custom_smtp')),
                                ]),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('domain')
                    ->label('Домен')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_sandbox')
                    ->label('Sandbox')
                    ->boolean(),
                Tables\Columns\TextColumn::make('import_status')
                    ->label('Статус импорта')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('import_progress')
                    ->label('Прогресс')
                    ->numeric()
                    ->suffix('%'),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\Action::make('simulate_sandbox_order')
                        ->label('Симулировать заказ')
                        ->icon('heroicon-o-beaker')
                        ->color('warning')
                        ->visible(fn (Shop $record) => $record->is_sandbox)
                        ->requiresConfirmation()
                        ->action(function (Shop $record) {
                            $product = $record->products()->where('is_active', true)->inRandomOrder()->first();
                            if (!$product) {
                                Notification::make()->title('Нет активных товаров')->danger()->send();
                                return;
                            }

                            $orderId = rand(100000000, 999999999);
                            $price = $product->price_rub ?? 10000;

                            $basePayload = [
                                'orderId' => $orderId,
                                'campaignId' => $record->campaign_id,
                                'shop_id' => $record->id,
                                'fake' => true,
                                'order_full_info' => [
                                    'id' => $orderId,
                                    'status' => 'UNPAID',
                                    'substatus' => 'RESERVATION',
                                    'buyerTotal' => $price,
                                    'currency' => 'RUR',
                                    'fake' => true,
                                    'items' => [
                                        [
                                            'id' => rand(100000, 999999),
                                            'offerId' => $product->sku,
                                            'offerName' => $product->name,
                                            'count' => 1,
                                            'price' => $price,
                                            'buyerPrice' => $price,
                                        ]
                                    ]
                                ],
                                'client_info' => [
                                    'id' => 'ym-buyer-' . rand(1000, 9999),
                                    'email' => 'sandbox@example.com',
                                    'phone' => '+79990001122',
                                    'lastName' => 'Тест',
                                    'firstName' => 'Санбокс',
                                ]
                            ];

                            // 1. Send ORDER_CREATED
                            $payload1 = array_merge($basePayload, [
                                'notificationType' => 'ORDER_CREATED',
                                'status' => 'UNPAID',
                                'substatus' => 'RESERVATION'
                            ]);
                            \App\Jobs\ProcessYmNotification::dispatch($payload1);

                            // 2. Send ORDER_STATUS_UPDATED (PROCESSING) after 3 seconds
                            $payload2 = array_merge($basePayload, [
                                'notificationType' => 'ORDER_STATUS_UPDATED',
                                'status' => 'PROCESSING',
                                'substatus' => 'STARTED'
                            ]);
                            $payload2['order_full_info']['status'] = 'PROCESSING';
                            $payload2['order_full_info']['substatus'] = 'STARTED';
                            \App\Jobs\ProcessYmNotification::dispatch($payload2)->delay(now()->addSeconds(3));

                            Notification::make()->title('Тестовый заказ отправлен в очередь! ID: ' . $orderId)->success()->send();
                        }),
                    \Filament\Actions\Action::make('import_ym')
                        ->label('Импорт из YM')
                        ->icon('heroicon-o-cloud-arrow-down')
                        ->requiresConfirmation()
                        ->action(function (Shop $record) {
                            if (!$record->business_id || !$record->api_key) {
                                Notification::make()->title('Настройки не заполнены')->danger()->send();
                                return;
                            }
                            $importToken = uniqid('im_', true);
                            $record->update(['import_status' => 'Запуск...', 'import_progress' => 1, 'import_token' => $importToken]);
                            \App\Jobs\ImportProductsFromYM::dispatch($record, $importToken);
                            Notification::make()->title('Импорт запущен')->success()->send();
                        }),
                ]),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ShopResource\Pages\ListShops::route('/'),
            'create' => ShopResource\Pages\CreateShop::route('/create'),
            'edit' => ShopResource\Pages\EditShop::route('/{record}/edit'),
        ];
    }
}
