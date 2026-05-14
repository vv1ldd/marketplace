<?php

namespace App\Filament\Partner\Resources\OrderResource\Pages;

use App\Filament\Partner\Resources\OrderResource;
use App\Http\Services\YmService;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('syncOrders')
                ->label(__('Синхронизировать заказы'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->action(function () {
                    $tenant = \Filament\Facades\Filament::getTenant();
                    $shops = $tenant?->shops ?? collect();

                    if ($shops->isEmpty()) {
                        Notification::make()->title('Нет доступных магазинов')->danger()->send();

                        return;
                    }

                    $newOrdersCount = 0;

                    foreach ($shops as $shop) {
                        if (! $shop->is_active) {
                            continue;
                        }

                        $ymService = new YmService($shop);

                        // Step 1: get list of recent orders (1 API call)
                        try {
                            // include_sandbox: иначе тестовые (Sandbox) не попадают в выдачу — у API fake=0 только боевые заказы.
                            // from_date: до 30 суток (максимум интервала в API Яндекса).
                            $orderList = $ymService->getOrders([
                                'include_sandbox' => true,
                                'from_date' => date('d-m-Y', strtotime('-30 days')),
                            ]);
                        } catch (\Exception $e) {
                            Log::error("syncOrders: getOrders failed for shop {$shop->name}", [$e->getMessage()]);

                            continue;
                        }

                        if (empty($orderList)) {
                            continue;
                        }

                        foreach ($orderList as $ymOrderShort) {
                            $ym_order_id = data_get($ymOrderShort, 'id');
                            $status = data_get($ymOrderShort, 'status', 'PROCESSING');
                            $substatus = data_get($ymOrderShort, 'substatus');

                            $existingOrder = Order::where('order_id', $ym_order_id)
                                ->where('shop_id', $shop->id)
                                ->first();

                            if ($existingOrder) {
                                // Update status silently if changed
                                if ($existingOrder->status !== $status) {
                                    $existingOrder->update([
                                        'status' => $status,
                                        'sub_status' => $substatus,
                                    ]);
                                }

                                continue;
                            }

                            // Step 2: fetch FULL order (same as webhook flow — needed for items)
                            try {
                                $order_full_info = $ymService->getOrder($ym_order_id);
                            } catch (\Exception $e) {
                                Log::error("syncOrders: getOrder failed for #{$ym_order_id}", [$e->getMessage()]);

                                continue;
                            }

                            $items = data_get($order_full_info, 'items', []);

                            // Step 3: get buyer info
                            $buyer = data_get($order_full_info, 'buyer', []);
                            $client_info = [
                                'id' => data_get($buyer, 'id'),
                                'lastName' => data_get($buyer, 'lastName'),
                                'firstName' => data_get($buyer, 'firstName'),
                                'middleName' => data_get($buyer, 'middleName'),
                                'phone' => data_get($buyer, 'phone'),
                                'email' => data_get($buyer, 'email'),
                            ];

                            try {
                                DB::beginTransaction();

                                // Step 4: Create Order — same fields as OrderController::created()
                                $newOrder = Order::create([
                                    'order_id' => $ym_order_id,
                                    'uuid' => Str::uuid()->toString(),
                                    'status' => $status,
                                    'sub_status' => $substatus,
                                    'info' => $order_full_info, // full data with items
                                    'client_info' => $client_info,
                                    'shop_id' => $shop->id,
                                    'is_test' => data_get($order_full_info, 'fake', false),
                                    'progress_id' => 1, // Не обработан — ручная синхронизация
                                ]);

                                // ⛓️ Sovereign Ledger: Record the manual sync order receipt
                                app(\App\Services\LedgerService::class)->record($shop, 'ORDER_RECEIVE', $newOrder, [
                                    'external_id' => $ym_order_id,
                                    'channel' => 'yandex_sync',
                                    'is_test' => data_get($order_full_info, 'fake', false),
                                ]);

                                // Step 5: Create OrderItems — same fields as updated(PROCESSING)
                                // but WITHOUT key generation and WITHOUT provideOrderDigitalCodes
                                $insertItems = [];
                                foreach ($items as $item) {
                                    $sku = data_get($item, 'offerId');
                                    if (! $sku) {
                                        continue;
                                    }

                                    $type_form_id = Product::queryByOfferSku($sku)->value('type_form_id');

                                    $insertItems[] = [
                                        'uuid' => Str::uuid()->toString(),
                                        'order_id' => $newOrder->id,
                                        'sku' => $sku,
                                        'count' => data_get($item, 'count', 1),
                                        'price_rub' => (int) (data_get($item, 'price', 0) * 100),
                                        'price_try' => (int) (data_get($item, 'buyerPrice', 0) * 100),
                                        'type_form_id' => $type_form_id,
                                        'activate_till' => now()->addYear()->format('Y-m-d'),
                                        'created_at' => now(),
                                        'updated_at' => now(),
                                    ];
                                }

                                if (! empty($insertItems)) {
                                    OrderItems::insert($insertItems);
                                }

                                $isFake = data_get($order_full_info, 'fake', false);

                                $newOrder->comments()->create([
                                    'user_id' => null,
                                    'comment' => '🔄 Заказ добавлен вручную через синхронизацию с Яндекс.Маркетом'.($isFake ? ' (ТЕСТ)' : ''),
                                ]);

                                if ($isFake) {
                                    $newOrder->comments()->create([
                                        'user_id' => null,
                                        'comment' => '⚠️ Внимание! Это тестовый заказ Яндекс.Маркета (Sandbox). Реальная закупка товара производиться не будет.',
                                    ]);
                                }

                                DB::commit();
                                $newOrdersCount++;

                            } catch (\Exception $e) {
                                DB::rollBack();
                                Log::error('syncOrders: failed to create order', [
                                    'order_id' => $ym_order_id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }

                    if ($newOrdersCount > 0) {
                        Notification::make()
                            ->title("Синхронизировано новых заказов: {$newOrdersCount}")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Новых заказов не найдено')
                            ->info()
                            ->send();
                    }
                }),
            \Filament\Actions\Action::make('createSandboxOrder')
                ->label('🧪 Тестовый заказ')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->modalHeading('Создать тестовый заказ (Sandbox)')
                ->modalDescription('Тестовый заказ создаётся для демонстрации API, проверки активаций и обучения. Реальная закупка товара не производится.')
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
                        Notification::make()->title('Нет доступных магазинов')->danger()->send();
                        return;
                    }

                    try {
                        DB::beginTransaction();

                        $sandboxId = 'SANDBOX-' . strtoupper(Str::random(8));

                        $order = Order::create([
                            'order_id'    => $sandboxId,
                            'uuid'        => Str::uuid()->toString(),
                            'status'      => 'PROCESSING',
                            'sub_status'  => 'SANDBOX',
                            'shop_id'     => $shop->id,
                            'is_test'     => true,
                            'progress_id' => 2,
                            'info'        => json_encode([]),
                            'client_info' => json_encode([
                                'firstName' => 'Sandbox',
                                'lastName'  => 'Client',
                                'email'     => 'sandbox@example.com',
                            ]),
                        ]);

                        $typeFormId = \App\Models\Product::queryByOfferSku($data['sku'])?->value('type_form_id');

                        $voucherCode = $data['code'];
                        if ($voucherCode === 'SANDBOX-TEST-CODE-0000') {
                            $prefix = $shop->voucher_prefix ?: substr(preg_replace('/[^A-Za-z0-9]/', '', $shop->name ?? 'SND'), 0, 1);
                            $voucherCode = \App\Services\VoucherEngine::issue(
                                issuerPrefix: $prefix,
                                sku: $data['sku']
                            );
                        }

                        // Используем системный сервис для шифрования и генерации индекса
                        $vault = app(\App\Services\VaultTransitService::class);
                        $encryptedCode = $vault->encrypt($voucherCode);
                        $blindIndex = $vault->computeBlindIndex($voucherCode);

                        // Используем insert() напрямую, чтобы не триггерить booted() хуки
                        DB::table('order_items')->insert([
                            'uuid'            => Str::uuid()->toString(),
                            'order_id'        => $order->id,
                            'sku'             => $data['sku'],
                            'count'           => 1,
                            'price_rub'       => (int) $data['price_rub'],
                            'purchase_status' => 'sandbox',
                            'original_code'   => $encryptedCode,
                            'key'             => $encryptedCode,
                            'key_bidx'        => $blindIndex,
                            'is_activated'    => 0,
                            'is_redeemed'     => 0,
                            'type_form_id'    => $typeFormId,
                            'activate_till'   => now()->addYear()->format('Y-m-d'),
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);

                        // Комментарий
                        DB::table('order_comments')->insert([
                            'order_id'   => $order->id,
                            'user_id'    => null,
                            'comment'    => '🧪 Тестовый заказ (Sandbox) создан вручную через партнерский кабинет.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::commit();

                        Notification::make()
                            ->title("🧪 Тестовый заказ #{$order->id} создан!")
                            ->body("SKU: {$data['sku']} | ID: {$sandboxId}")
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Sandbox order creation failed', ['error' => $e->getMessage()]);
                        Notification::make()->title('Ошибка: ' . $e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
