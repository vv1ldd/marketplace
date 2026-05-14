<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Partner\Resources\OrderResource;
use App\Support\SalesChannels;
use App\Filament\Partner\Resources\ShopResource;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use App\Models\Shop;
use UnitEnum;

class Integrations extends Page
{
    protected string $view = 'filament.partner.pages.integrations';

    protected static string|UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?string $navigationLabel = 'Все каналы';

    protected static ?int $navigationSort = 101;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-plus';

    public function getTitle(): string
    {
        return 'Интеграции и каналы продаж';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('createSandboxOrder')
                ->label('🧪 Тестовый заказ (API)')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->modalHeading('Создать тестовый заказ (Sandbox)')
                ->modalDescription('Тестовый заказ создаётся для проверки интеграций по API или вебхукам. Реальная закупка товара не производится.')
                ->modalWidth('md')
                ->form([
                    \Filament\Forms\Components\Select::make('sku')
                        ->label('Товар (SKU)')
                        ->searchable()
                        ->required()
                        ->options(function () {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            $shopIds = $tenant?->shops->pluck('id')->toArray() ?? [];
                            return \App\Models\Product::query()
                                ->whereIn('shop_id', $shopIds)
                                ->orWhereNull('shop_id')
                                ->limit(200)
                                ->get()
                                ->mapWithKeys(fn ($p) => [$p->sku => "[{$p->sku}] " . ($p->name ?? $p->sku)])
                                ->toArray();
                        }),
                    \Filament\Forms\Components\TextInput::make('price_rub')
                        ->label('Цена продажи (RUB, в копейках)')
                        ->numeric()
                        ->default(5000)
                        ->helperText('Например: 5000 = 50 ₽'),
                    \Filament\Forms\Components\TextInput::make('code')
                        ->label('Тестовый код активации')
                        ->default('SANDBOX-TEST-CODE-0000')
                        ->helperText('Будет показан в заказе как код гифт-карты'),
                ])
                ->action(function (array $data) {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $shop = $tenant?->shops?->first();

                    if (!$shop) {
                        \Filament\Notifications\Notification::make()->title('Нет доступных магазинов')->danger()->send();
                        return;
                    }

                    try {
                        \Illuminate\Support\Facades\DB::beginTransaction();

                        $sandboxId = 'SANDBOX-' . strtoupper(\Illuminate\Support\Str::random(8));

                        $orderId = \Illuminate\Support\Facades\DB::table('orders')->insertGetId([
                            'order_id'    => $sandboxId,
                            'uuid'        => \Illuminate\Support\Str::uuid()->toString(),
                            'status'      => 'PROCESSING',
                            'sub_status'  => 'SANDBOX',
                            'shop_id'     => $shop->id,
                            'is_test'     => 1,
                            'progress_id' => 2,
                            'info'        => json_encode([]),
                            'client_info' => json_encode([
                                'firstName' => 'Sandbox',
                                'lastName'  => 'Client',
                                'email'     => 'sandbox@example.com',
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $typeFormId = \App\Models\Product::queryByOfferSku($data['sku'])?->value('type_form_id');

                        $voucherCode = $data['code'];
                        if ($voucherCode === 'SANDBOX-TEST-CODE-0000') {
                            $voucherCode = \App\Services\VoucherEngine::issue(
                                issuerPrefix: $shop->name ?? 'SND',
                                sku: $data['sku']
                            );
                        }

                        // Используем системный сервис для шифрования и генерации индекса
                        $vault = app(\App\Services\VaultTransitService::class);
                        $encryptedCode = $vault->encrypt($voucherCode);
                        
                        $blindIndex = $vault->computeBlindIndex($voucherCode);

                        \Illuminate\Support\Facades\DB::table('order_items')->insert([
                            'uuid'            => \Illuminate\Support\Str::uuid()->toString(),
                            'order_id'        => $orderId,
                            'sku'             => $data['sku'],
                            'count'           => 1,
                            'price_rub'       => (int) $data['price_rub'],
                            'purchase_status' => 'sandbox',
                            'original_code'   => $encryptedCode,
                            'key'             => $encryptedCode,
                            'key_bidx'        => $blindIndex, // <--- Критически важно для активации
                            'is_activated'    => 0,
                            'is_redeemed'     => 0,
                            'type_form_id'    => $typeFormId,
                            'activate_till'   => now()->addYear()->format('Y-m-d'),
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);

                        \Illuminate\Support\Facades\DB::table('order_comments')->insert([
                            'order_id'   => $orderId,
                            'user_id'    => null,
                            'user_type'  => null,
                            'comment'    => '🧪 Тестовый заказ (Sandbox) создан вручную из раздела Настроек Интеграций.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        \Illuminate\Support\Facades\DB::commit();

                        \Filament\Notifications\Notification::make()
                            ->title("🧪 Тестовый заказ #{$orderId} создан!")
                            ->body("SKU: {$data['sku']} | ID: {$sandboxId}")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\DB::rollBack();
                        \Illuminate\Support\Facades\Log::error('Sandbox order creation failed', ['error' => $e->getMessage()]);
                        \Filament\Notifications\Notification::make()->title('Ошибка: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $channels = SalesChannels::all();
        $cardsByGroup = [];

        foreach ($channels as $key => $meta) {
            if (! ($meta['enabled'] ?? false)) {
                continue;
            }

            $isImplemented = (bool) ($meta['implemented'] ?? false);
            $label = $meta['label'] ?? $key;
            $iconEmoji = $meta['icon'] ?? '🔌';
            $group = $meta['group'] ?? 'marketplaces';

            $card = Section::make($label)
                ->description($isImplemented ? 'Интеграция готова к использованию' : 'В разработке')
                ->schema([
                    Placeholder::make('status_'.$key)
                        ->label('')
                        ->content(new HtmlString("
                            <div class='flex items-center justify-between pb-2'>
                                <div class='flex items-center gap-3'>
                                    <div class='flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-50 text-3xl shadow-inner border border-gray-100'>
                                        {$iconEmoji}
                                    </div>
                                    <div>
                                        <div class='text-[10px] text-gray-400 uppercase tracking-widest font-black'>Статус</div>
                                        <div class='text-sm ".($isImplemented ? 'text-success-600 font-bold' : 'text-gray-500 font-medium')."'>"
                                            .($isImplemented ? 'Подключено' : 'Скоро появится').
                                        "</div>
                                    </div>
                                </div>
                            </div>
                        ")),

                    \Filament\Schemas\Components\Actions::make([
                        \Filament\Actions\Action::make('configure')
                            ->label($isImplemented ? 'Настроить канал' : 'Подробнее')
                            ->icon($isImplemented ? 'heroicon-m-adjustments-horizontal' : 'heroicon-m-information-circle')
                            ->color($isImplemented ? 'primary' : 'gray')
                            ->url(fn () => match ($key) {
                                'yandex_market', 'woocommerce', 'offline_store' => ShopResource::getUrl('index'),
                                default => '#',
                            })
                            ->disabled(fn() => ! $isImplemented || ($key !== 'yandex_market' && $key !== 'woocommerce' && $key !== 'offline_store')),
                    ])->fullWidth(),
                ])
                ->columnSpan(1);

            $cardsByGroup[$group][] = $card;
        }

        $groupLabels = [
            'marketplaces' => 'Маркетплейсы',
            'cms' => 'Собственные сайты и CMS',
            'offline' => 'Оффлайн розница',
            'messengers' => 'Мессенджеры и Соцсети',
        ];

        $tabs = [];
        foreach ($groupLabels as $groupKey => $groupLabel) {
            if (!empty($cardsByGroup[$groupKey])) {
                $tabs[] = \Filament\Schemas\Components\Tabs\Tab::make($groupLabel)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])->schema($cardsByGroup[$groupKey])
                    ]);
            }
        }

        // Добавляем вкладку Архив если есть неактивные магазины
        $archivedShops = Shop::where('is_active', false)->get();
        if ($archivedShops->count() > 0) {
            $archivedCards = [];
            foreach ($archivedShops as $shop) {
                $archivedCards[] = Section::make($shop->name)
                    ->description('Архивный канал')
                    ->schema([
                        Placeholder::make('status_archived_'.$shop->id)
                            ->label('')
                            ->content(new HtmlString("
                                <div class='flex items-center gap-3'>
                                    <div class='flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-2xl bg-gray-100 text-gray-400 text-3xl shadow-inner border border-gray-200'>
                                        🏛️
                                    </div>
                                    <div>
                                        <div class='text-[10px] text-gray-400 uppercase tracking-widest font-black'>Статус</div>
                                        <div class='text-sm text-gray-400 font-medium'>Неактивен / Архив</div>
                                    </div>
                                </div>
                            ")),
                        \Filament\Schemas\Components\Actions::make([
                            \Filament\Actions\Action::make('view_archive')
                                ->label('Смотреть заказы')
                                ->icon('heroicon-m-eye')
                                ->color('gray')
                                ->url(fn () => OrderResource::getUrl('index', ['tableFilters' => ['shop_id' => ['value' => $shop->id]]])),
                        ])->fullWidth(),
                    ])
                    ->columnSpan(1);
            }

            $tabs[] = \Filament\Schemas\Components\Tabs\Tab::make('Архив')
                ->schema([
                    Grid::make([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])->schema($archivedCards)
                ]);
        }

        return $schema->components([
            \Filament\Schemas\Components\Tabs::make('Channels')
                ->tabs($tabs),

            Section::make('🧪 Sandbox Monitor')
                ->description('История последних тестовых активаций и выданных кодов')
                ->collapsible()
                ->schema([
                    Placeholder::make('sandbox_history')
                        ->label('')
                        ->content(function () {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            $shopIds = $tenant?->shops->pluck('id')->toArray() ?? [];
                            
                            $orders = \App\Models\Order\Order::whereIn('shop_id', $shopIds)
                                ->where('is_test', true)
                                ->with(['items'])
                                ->latest()
                                ->limit(10)
                                ->get();

                            if ($orders->isEmpty()) {
                                return new HtmlString('<div class="text-sm text-gray-500 italic py-4">Тестовых заказов пока нет. Нажмите кнопку выше, чтобы создать первый.</div>');
                            }

                            $rows = '';
                            foreach ($orders as $order) {
                                $item = $order->items->first();
                                $code = $item?->key ?? '—';
                                
                                // Если код все еще зашифрован (например, если модель не применила каст)
                                if (str_starts_with((string)$code, 'vault:')) {
                                    $code = app(\App\Services\VaultTransitService::class)->decrypt($code);
                                }

                                $rows .= "
                                    <tr class='border-b border-gray-50 dark:border-white/5 last:border-0'>
                                        <td class='py-3 text-xs font-mono text-gray-500'>#{$order->id}</td>
                                        <td class='py-3'><span class='px-2 py-1 rounded bg-gray-100 dark:bg-white/5 text-[10px] font-bold'>{$item->sku}</span></td>
                                        <td class='py-3'><div class='flex items-center gap-2'><span class='text-xs font-black text-primary-600 dark:text-primary-400'>{$code}</span></div></td>
                                        <td class='py-3 text-right text-[10px] text-gray-400 font-medium'>{$order->created_at->diffForHumans()}</td>
                                    </tr>
                                ";
                            }

                            return new HtmlString("
                                <div class='overflow-x-auto'>
                                    <table class='w-full text-left'>
                                        <thead>
                                            <tr class='text-[10px] uppercase tracking-widest text-gray-400 font-black border-b border-gray-100 dark:border-white/10'>
                                                <th class='pb-2'>ID</th>
                                                <th class='pb-2'>SKU</th>
                                                <th class='pb-2'>Выданный код (Ваучер)</th>
                                                <th class='pb-2 text-right'>Время</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {$rows}
                                        </tbody>
                                    </table>
                                </div>
                            ");
                        })
                ])
        ]);
    }
}
