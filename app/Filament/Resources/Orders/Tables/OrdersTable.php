<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $currentPanelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();
        $isPartnerPanel = $currentPanelId === 'partner';

        $user = auth()->user();
        $is_executor = ! $isPartnerPanel && $user->hasRole('executor');
        $is_support = ! $isPartnerPanel && $user->hasRole('support');
        $is_super_admin = $isPartnerPanel || $user->hasRole('super_admin');

        return $table
            ->header(new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render('<style>
                /* Классические табы, но на всю ширину */
                .fi-tabs {
                    width: 100% !important;
                    max-width: 100% !important;
                }
                .fi-tabs nav {
                    display: flex !important;
                    width: 100% !important;
                    justify-content: space-between !important;
                }
                .fi-tabs nav > * {
                    flex: 1 !important;
                    text-align: center !important;
                    justify-content: center !important;
                }
            </style>')))
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.orders.order_number'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('shop.name')
                    ->label(__('admin.users.shop'))
                    ->placeholder(__('admin.common.all'))
                    ->getStateUsing(function ($record) {
                        if ($record instanceof \App\Models\User) {
                            return $record->shop?->name;
                        }

                        return $record->shop?->name ?? '';
                    })
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('order_id')->label(__('admin.orders.source_number'))
                    ->searchable()
                    ->hidden($is_executor || $is_support)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable(),
                TextColumn::make('status')->label(__('admin.orders.source_status'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hidden($is_executor || $is_support),
                //                TextColumn::make('order_items_count')->label('Товаров')
                //                    ->getStateUsing(fn($record) => $record->items()->count()),
                TextColumn::make('user.id')
                    ->label(__('admin.orders.customer'))
                    ->hidden($is_executor || $is_support)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(fn ($record) => $isPartnerPanel ? 'Клиент #'.$record->user_id : ($record->user?->id ?? '—'))
                    ->url(fn ($record) => (! $isPartnerPanel && $record->user?->id) ? EditUser::getUrl(['record' => $record->user->id]) : null, true),
                TextColumn::make('progress.name')->label(__('admin.orders.progress'))
                    ->sortable()
                    ->toggleable()
                    ->color(function ($record) {
                        return match ($record->progress_id) {
                            1 => 'warning',
                            4 => 'success',
                            default => 'secondary',
                        };
                    })
                    ->badge(),
                IconColumn::make('items.is_redeemed')
                    ->hidden($is_executor || $is_support)
                    ->icon(fn ($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn ($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'danger' : 'success')
                    ->label(__('admin.orders.code_entered'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                IconColumn::make('items.is_activated')
                    ->hidden($is_executor || $is_support)
                    ->icon(fn ($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn ($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'danger' : 'success')
                    ->label(__('admin.orders.activated'))
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                TextColumn::make('purchase_status_display')
                    ->label($isPartnerPanel ? 'Статус активации' : __('admin.orders.purchase'))
                    ->badge()
                    ->toggleable()
                    ->color(fn ($state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'manual' => 'info',
                        'sandbox' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'sandbox' ? '🧪 Тестовый' : $state)
                    ->getStateUsing(fn ($record) => $record->is_test ? 'sandbox' : ($record->items->first()?->purchase_status ?? 'none'))
                    ->hidden($is_executor || $is_support),
                TextColumn::make('items.typeForm.name')->label(__('admin.orders.type'))
                    ->limitList(1)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->badge(),
                TextColumn::make('dispute_decision')
                    ->label(__('admin.orders.dispute_status'))
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'DECLINED' => 'success',
                        'REFUND_TO_BUYER' => 'danger',
                        'REPLACEMENT_REQUIRED' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'DECLINED' => 'Победа (Отказ)',
                        'REFUND_TO_BUYER' => 'Возврат покупателю',
                        'REPLACEMENT_REQUIRED' => 'Замена товара',
                        'OTHER_DECISION' => 'Другое решение',
                        default => $state,
                    })
                    ->visible(fn ($record) => $record?->dispute_decision !== null)
                    ->toggleable(),
                TextColumn::make('created_at')->label(__('admin.orders.created'))->dateTime('d.m.Y H:i')->sortable()->toggleable(),
                TextColumn::make('assigned_at')->label(__('admin.orders.assigned_at'))
                    ->visible($is_super_admin)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime('d.m.Y H:i'),
                TextColumn::make('updated_at')->label(__('admin.orders.updated_at'))->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->hidden($is_executor || $is_support),
            ])
            ->filters([
                SelectFilter::make('shop_id')
                    ->label(__('admin.users.shop'))
                    ->searchable()
                    ->options(function() use ($isPartnerPanel) {
                        $query = \App\Models\Shop::query();
                        if ($isPartnerPanel) {
                            $tenant = \Filament\Facades\Filament::getTenant();
                            $query->where('legal_entity_id', $tenant?->id);
                        }
                        return $query->pluck('name', 'id');
                    })
                    ->searchable(),
            ], layout: FiltersLayout::Modal)
            ->persistFiltersInSession()
            ->recordActions([
                \Filament\Actions\Action::make('quick_complete')
                    ->label('Завершить')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->hidden(fn ($record) => $record->progress_id === 4)
                    ->action(function ($record) {
                        $record->update(['progress_id' => 4]);
                        \Filament\Notifications\Notification::make()
                            ->title('Заказ #' . $record->id . ' завершен')
                            ->success()
                            ->send();
                    }),
                \Filament\Actions\Action::make('purchase_voucher')
                    ->label('Закупить ваучер')
                    ->icon('heroicon-m-shopping-cart')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Ручная закупка ваучера')
                    ->modalDescription(function ($record) {
                        $item = $record->items->first();
                        if (!$item) return 'В заказе нет товаров.';
                        
                        $product = \App\Models\Product::where('sku', $item->sku)->first();
                        $catalogSku = $product?->wildflow_catalog_sku ?? $item->sku;
                        $catalog = \App\Models\WildflowCatalog::where('sku', $catalogSku)->first();
                        
                        if (!$catalog) return 'Товар не найден в каталоге провайдера.';
                        
                        $shop = $record->shop;
                        $priceRub = 0;
                        if ($shop) {
                            $price = $catalog->getPurchasePriceForShop($shop);
                            $rate = app(\App\Services\FinanceService::class)->getRate($catalog->currency_code ?? 'USD');
                            $priceRub = $price * $rate * $item->count;
                        }
                        
                        return new \Illuminate\Support\HtmlString("Будет произведена закупка через провайдера.<br>С баланса партнера (ИП/Юр.лицо) спишется по его тарифу: <b>" . number_format($priceRub, 2) . " RUB</b>.");
                    })
                    ->visible(function ($record) use ($isPartnerPanel) {
                        // Только для главной админки, и если товар еще не закуплен
                        if ($isPartnerPanel) return false;
                        $item = $record->items->first();
                        return $item && !in_array($item->purchase_status, ['success']);
                    })
                    ->action(function ($record) {
                        $item = $record->items->first();
                        if (!$item) return;

                        $product = \App\Models\Product::where('sku', $item->sku)->first();
                        $catalogSku = $product?->wildflow_catalog_sku ?? $item->sku;
                        $catalog = \App\Models\WildflowCatalog::where('sku', $catalogSku)->first();

                        if (!$catalog) {
                            \Filament\Notifications\Notification::make()->title('Товар не найден в каталоге')->danger()->send();
                            return;
                        }

                        $shop = $record->shop;
                        $legalEntity = $shop?->legalEntity;
                        
                        if (!$legalEntity) {
                            \Filament\Notifications\Notification::make()->title('К магазину не привязано Юридическое лицо')->danger()->send();
                            return;
                        }

                        // Считаем цену по тарифу
                        $priceUsd = $catalog->getPurchasePriceForShop($shop);
                        $rate = app(\App\Services\FinanceService::class)->getRate($catalog->currency_code ?? 'USD');
                        $costRub = $priceUsd * $rate * $item->count;

                        if ($legalEntity->available_balance < $costRub) {
                            \Filament\Notifications\Notification::make()->title('Недостаточно средств на балансе партнера')->danger()->send();
                            return;
                        }

                        // Списываем средства
                        $legalEntity->decrement('available_balance', $costRub);

                        try {
                            $hub = app(\App\Services\Provider\ProviderHub::class);
                            $providerProduct = \App\Models\ProviderProduct::where('market_sku_bidx', app(\App\Services\VaultTransitService::class)->computeBlindIndex($catalog->sku))->first();
                            $provider = $providerProduct?->provider ?? \App\Models\Provider::where('type', 'wildflow')->first();
                            $driver = $hub->driver($provider);

                            $customer = $record->user;
                            $email = $customer?->email ?? 'admin@wildflow.dev';
                            $providerReference = $item->providerReference() . '-manual';

                            // Делаем реальный заказ
                            $externalOrderId = $driver->createOrder(
                                sku: $catalog->service_sku,
                                reference: $providerReference,
                                price: $catalog->retail_price,
                                quantity: $item->count,
                                meta: [
                                    'buying_price' => $catalog->purchase_price,
                                    'email' => $email,
                                ]
                            );

                            sleep(1);
                            $codes = $driver->getCodes($externalOrderId);
                            $original_code = !empty($codes) ? $codes[0] : null;

                            if ($original_code) {
                                $item->update([
                                    'purchase_status' => 'success',
                                    'original_code' => $original_code,
                                    'purchase_error' => null,
                                    'provider_order_id' => $externalOrderId,
                                ]);
                                
                                $record->update(['progress_id' => 4]);

                                app(\App\Services\LedgerService::class)->record($shop, 'FINANCE_CAPTURE_MANUAL', $record, [
                                    'amount_rub' => $costRub,
                                    'order_item_id' => $item->id,
                                    'provider' => $provider->type,
                                    'code_masked' => \Illuminate\Support\Str::mask($original_code, '*', 4, -4),
                                ]);

                                \Filament\Notifications\Notification::make()->title('Успешно закуплено! Код привязан.')->success()->send();
                            } else {
                                throw new \Exception('Провайдер не вернул код мгновенно (заказ создан).');
                            }
                        } catch (\Exception $e) {
                            // Возвращаем средства при ошибке
                            $legalEntity->increment('available_balance', $costRub);
                            \Filament\Notifications\Notification::make()->title('Ошибка закупки')->body($e->getMessage())->danger()->send();
                        }
                    }),
                EditAction::make(),
                ViewAction::make(),
            ])
            ->deferFilters(false)
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
// ->poll('10s')
            ->paginationPageOptions([
                20, 25, 50, 100,
            ]);
    }
}
