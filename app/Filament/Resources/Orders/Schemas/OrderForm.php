<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Mail\SendAccountDataMail;
use App\Services\AccountGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        $order = $schema->getRecord();
        $order?->loadMissing(['shop']);

        $order_user_meta = $order->user?->meta ?? null;

        $is_create = ! $order;
        $is_update = ! $is_create;

        $currentPanelId = \Filament\Facades\Filament::getCurrentPanel()?->getId();
        $isPartnerPanel = $currentPanelId === 'partner';

        $user = auth()->user();
        $is_executor = ! $isPartnerPanel && $user->hasRole('executor');
        $is_support = ! $isPartnerPanel && $user->hasRole('support');
        $super_admin = $isPartnerPanel || $user->hasRole('super_admin'); // Partners can see their own prices

        $skus = $order?->items?->pluck('sku')->filter()->unique()->toArray() ?? [];
        $tenant = \Filament\Facades\Filament::getTenant();
        $shopForPricing = $order?->shop ?? ($tenant instanceof \App\Models\Shop ? $tenant : ($tenant instanceof \App\Models\LegalEntity ? $tenant->shops()->first() : null));

        $alts = \App\Models\Product::whereIn('sku', $skus)
            ->with('provider')
            ->get()
            ->mapWithKeys(fn (\App\Models\Product $p) => [
                $p->sku => [
                    'name' => $p->name,
                    'price_rub' => $p->price_rub,
                    'purchase_price_rub' => $p->purchase_price_rub,
                    'type' => $p->type,
                    'catalog' => $p->getSellerPurchaseCatalogForShop($shopForPricing),
                ],
            ])
            ->all();

        return $schema
            ->components([
                Section::make(__('admin.navigation.orders'))
                    ->collapsible()
                    ->headerActions([
                        \Filament\Actions\Action::make('complete_order')
                            ->label('Завершить заказ')
                            ->icon('heroicon-m-check-badge')
                            ->color('success')
                            ->requiresConfirmation()
                            ->hidden(fn ($record) => $record?->progress_id === 4)
                            ->action(function ($record, $set) {
                                $record->update(['progress_id' => 4]);
                                $set('progress_id', 4);
                                \Filament\Notifications\Notification::make()
                                    ->title('Заказ #' . $record->id . ' завершен')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('id')
                            ->label(__('admin.orders.order_number'))
                            ->readOnly($is_update)
                            ->copyable()
                            ->hidden($is_create)
                            ->required($is_create),
                        Placeholder::make('sandbox_notice')
                            ->label('')
                            ->columnSpanFull()
                            ->visible(fn ($record) => (bool) $record?->is_test)
                            ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                                '<div class="flex items-center gap-2 rounded-lg border border-dashed border-amber-400 bg-amber-50 dark:bg-amber-950 px-4 py-3 text-sm text-amber-700 dark:text-amber-300 font-medium">'
                                . '🧪 <strong>Тестовый заказ (Sandbox)</strong> — этот заказ создан для демонстрации API и функционала. Реальная активация не производится.'
                                . '</div>'
                            )),
                        Placeholder::make('customer_display')
                            ->label('Клиент')
                            ->hidden($is_executor || $is_support)
                            ->content(function ($record) use ($isPartnerPanel) {
                                if (! $record) {
                                    return new \Illuminate\Support\HtmlString('<span class="text-gray-500 italic text-sm">⏳ '.__('admin.orders.status_labels.waiting_activation').'</span>');
                                }

                                $record->loadMissing('user');

                                if ($record->user) {
                                    $user = $record->user;
                                    $name = trim("{$user->first_name} {$user->last_name}") ?: __('admin.orders.customer').' #'.$user->id;

                                    if ($isPartnerPanel) {
                                        return 'Клиент #'.$user->id;
                                    }

                                    $url = \App\Filament\Resources\Users\ClientResource::getUrl('edit', ['record' => $user->id]);

                                    return new \Illuminate\Support\HtmlString(
                                        '<a href="'.e($url).'" target="_blank" class="text-primary-600 font-semibold hover:underline">'
                                        .e($name).
                                        '</a>'
                                    );
                                }

                                $waiting = '<span class="text-gray-500 italic text-sm">⏳ '.__('admin.orders.status_labels.waiting_activation').'</span>';
                                $inferred = $record->findInferredCustomer();

                                if ($inferred) {
                                    $name = trim("{$inferred->first_name} {$inferred->last_name}") ?: __('admin.orders.customer').' #'.$inferred->id;
                                    $hint = e(__('admin.orders.customer_inferred_hint'));
                                    $nameSafe = e($name);

                                    if ($isPartnerPanel) {
                                        return new \Illuminate\Support\HtmlString(
                                            $waiting
                                            .'<p class="mt-2 text-sm text-gray-700 dark:text-gray-300">'.$hint.' <strong>Клиент #'.$inferred->id.'</strong></p>'
                                        );
                                    }

                                    $url = \App\Filament\Resources\Users\ClientResource::getUrl('edit', ['record' => $inferred->id]);
                                    $link = '<a href="'.e($url).'" target="_blank" class="text-primary-600 font-semibold hover:underline">'.$nameSafe.'</a>';

                                    return new \Illuminate\Support\HtmlString(
                                        $waiting
                                        .'<p class="mt-2 text-sm text-gray-700 dark:text-gray-300">'.$hint.' '.$link.' <span class="text-gray-500">(#'.$inferred->id.')</span></p>'
                                    );
                                }

                                return new \Illuminate\Support\HtmlString($waiting);
                            }),
                        Select::make('progress_id')
                            ->options([
                                4 => 'Обработан полностью',
                                2 => 'В обработке',
                                1 => 'Не обработан',
                                3 => 'Обработан частично',
                                5 => 'Отменен',
                                6 => 'Возвращен',
                            ])
                            ->required()
                            ->label(__('admin.orders.progress')),
                        Toggle::make('is_problem')
                            ->inline(false)
                            ->label(__('admin.orders.is_problem'))
                            ->default(false)
                            ->disabled(),
                        DateTimePicker::make('created_at')
                            ->label(__('admin.orders.create_date'))
                            ->disabled(),
                        //                        Textarea::make('comment')
                        //                            ->label('Комментарий')
                        //                            ->rows(2)
                        //                            ->columnSpanFull(),
                    ]),

                ])->columnSpanFull(),

                Section::make(__('admin.common.view'))->collapsible()->schema([
                    TextInput::make('order_id')
                        ->label(__('admin.orders.order_number'))
                        ->required()
                        ->readOnly(),
                    TextInput::make('status')
                        ->label(__('admin.orders.status'))
                        ->readOnly(),
                    TextInput::make('sub_status')
                        ->label(__('admin.orders.sub_status'))
                        ->readOnly(),
                    TextInput::make('dispute_decision')
                        ->label(__('admin.orders.dispute_status'))
                        ->readOnly()
                        ->visible(fn ($record) => $record?->dispute_decision !== null),

                    Grid::make(3)
                        ->schema([
                            TextInput::make('client_info.last_name')
                                ->label(__('admin.customers.last_name'))
                                ->readOnly(),
                            TextInput::make('client_info.first_name')
                                ->label(__('admin.customers.first_name'))
                                ->readOnly(),
                            TextInput::make('client_info.middle_name')
                                ->label(__('admin.customers.middle_name')) // Assuming I need to add this to translations
                                ->readOnly(),
                        ]),

                    Grid::make(2)
                        ->schema([
                            TextInput::make('client_info.email')
                                ->readOnly(),
                            TextInput::make('client_info.phone')
                                ->mask('+79999999999')
                                ->label(__('admin.customers.phone'))
                                ->readOnly(),
                        ]),

                ])
                    ->hidden($is_executor || $isPartnerPanel)
                    ->columnSpanFull(),

                Section::make(__('admin.orders.items'))
                    ->collapsible()
                    ->disabled($is_executor || $is_support)
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->collapsible()
                            ->maxItems(100)
                            ->deletable(false)
                            ->addable(false)
                            ->truncateItemLabel()
                            ->itemLabel(__('admin.orders.item'))
                            ->columns(1)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('sku')
                                        ->label('SKU')
                                        ->copyable()
                                        ->required()
                                        ->readOnly(),

                                    Placeholder::make('game_name')
                                        ->label('Наименование товара')
                                        ->content(fn (Get $get) => $alts[$get('sku')]['name'] ?? null),

                                    Grid::make(4)->schema([
                                        Placeholder::make('price_rub_info')
                                            ->label(__('admin.orders.price_sell'))
                                            ->visible($super_admin)
                                            ->content(function (Get $get) use ($alts) {
                                                if ($price = $get('price_rub')) {
                                                    return ($price / 100).' RUB';
                                                }
                                                $product = $alts[$get('sku')] ?? null;

                                                return ($product['price_rub'] ?? null) ? ($product['price_rub'] / 100).' RUB' : null;
                                            }),
                                        Placeholder::make('purchase_catalog_amount')
                                            ->label(__('admin.orders.catalog_purchase_amount'))
                                            ->visible($super_admin || $is_executor)
                                            ->content(function (Get $get) use ($alts) {
                                                $cat = $alts[$get('sku')]['catalog'] ?? null;
                                                if (! $cat) {
                                                    return '—';
                                                }

                                                return number_format((float) $cat['amount'], 2, '.', ' ');
                                            }),
                                        Placeholder::make('purchase_catalog_currency')
                                            ->label(__('admin.orders.catalog_purchase_currency'))
                                            ->visible($super_admin || $is_executor)
                                            ->content(function (Get $get) use ($alts) {
                                                $cat = $alts[$get('sku')]['catalog'] ?? null;
                                                if (! $cat) {
                                                    return '—';
                                                }

                                                return $cat['currency'];
                                            }),
                                        Placeholder::make('purchase_catalog_rub')
                                            ->label(__('admin.orders.catalog_purchase_rub'))
                                            ->visible(function (Get $get) use ($alts, $super_admin, $is_executor) {
                                                if (! ($super_admin || $is_executor)) {
                                                    return false;
                                                }
                                                $cat = $alts[$get('sku')]['catalog'] ?? null;

                                                return $cat
                                                    && ($cat['currency'] ?? '') !== 'RUB'
                                                    && ($cat['rub'] ?? null) !== null;
                                            })
                                            ->content(function (Get $get) use ($alts) {
                                                $cat = $alts[$get('sku')]['catalog'] ?? null;

                                                return number_format((float) $cat['rub'], 2, '.', ' ').' ₽';
                                            }),
                                    ])->columns(),

                                    TextInput::make('count')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(100)
                                        ->default(1)
                                        ->label('Количество')
                                        ->readOnly(),

                                ]),

                                Grid::make(3)->schema([
                                    Toggle::make('is_redeemed')
                                        ->default(false)
                                        ->inline(false)
                                        ->label(__('admin.orders.code_entered'))
                                        ->disabled(),
                                    Toggle::make('is_activated')
                                        ->inline(false)
                                        ->default(false)
                                        ->label(__('admin.orders.activated'))
                                        ->disabled(),
                                    Select::make('purchase_status')
                                        ->options([
                                            'none' => __('admin.orders.options.none'),
                                            'pending' => __('admin.orders.options.pending'),
                                            'success' => __('admin.orders.options.success'),
                                            'failed' => __('admin.orders.options.failed'),
                                            'manual' => __('admin.orders.options.manual'),
                                            'sandbox' => '🧪 Заказ тестовый',
                                        ])
                                        ->label($isPartnerPanel ? 'Статус активации' : __('admin.orders.purchase_status'))
                                        ->disabled()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            if (in_array($state, ['success', 'manual'])) {
                                                $set('is_activated', true);
                                                if (! $get('activated_at')) {
                                                    $set('activated_at', now());
                                                }
                                            }
                                        }),
                                ])->hidden($is_executor || $is_support),

                                Grid::make(2)->schema([
                                    DateTimePicker::make('activated_at')
                                        ->label(__('admin.orders.activated_at'))
                                        ->disabled()
                                        ->hidden($is_executor || $is_support),
                                    TextInput::make('original_code')
                                        ->label(__('admin.orders.gift_card_code'))
                                        ->copyable()
                                        ->readOnly(fn ($state) => filled($state))
                                        ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Str::mask($state, '*', 4, -4) : $state)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, $record) {
                                            if ($state && $record) {
                                                $record->update(['purchase_status' => 'success']);

                                                // Проверяем статус всего заказа
                                                $order = $record->order;
                                                if ($order) {
                                                    $allItemsHasCode = $order->items()
                                                        ->where(fn ($q) => $q->whereNull('original_code')->where('id', '!=', $record->id))
                                                        ->doesntExist();

                                                    $order->update([
                                                        'progress_id' => $allItemsHasCode ? 4 : 3,
                                                    ]);
                                                }
                                            }
                                        })
                                        ->hidden($is_executor || $is_support)
                                        ->suffixAction(
                                            Action::make('manual_send')
                                                ->label(__('admin.orders.actions.manual_issuance'))
                                                ->icon('heroicon-m-paper-airplane')
                                                ->color('success')
                                                ->requiresConfirmation()
                                                ->modalHeading(__('admin.orders.modals.manual_issuance_title'))
                                                ->modalDescription(__('admin.orders.modals.manual_issuance_desc'))
                                                ->disabled(fn ($record) => in_array($record?->purchase_status, ['success', 'manual']))
                                                ->action(function ($state, $record, Set $set) {
                                                    if (! $state) {
                                                        Notification::make()->title(__('admin.orders.notifications.enter_code_first'))->danger()->send();

                                                        return;
                                                    }

                                                    // 1. Обновляем статус товара
                                                    $record->update([
                                                        'purchase_status' => 'success',
                                                        'original_code' => $state,
                                                        'activated_at' => $record->activated_at ?? now(),
                                                        'is_activated' => true,
                                                    ]);

                                                    $order = $record->order;

                                                    // 2. Отправляем Email
                                                    $email = data_get($record->client_info, 'email') ?: $order->user?->email;
                                                    if ($email) {
                                                        try {
                                                            \Illuminate\Support\Facades\Mail::to($email)->send(new \App\Mail\SendActivationCode($state, $order));
                                                        } catch (\Exception $e) {
                                                            \Illuminate\Support\Facades\Log::error('Manual Email send error', [$e->getMessage()]);
                                                        }
                                                    }

                                                    // 3. Отправляем в чат Яндекс.Маркета
                                                    if ($order->chat_id) {
                                                        try {
                                                            $ymService = new \App\Http\Services\YmService($order->shop);
                                                            $ymService->sendMessage($order->chat_id, view('chat.send_code_message', ['code' => $state, 'shop' => $order->shop])->render());
                                                        } catch (\Exception $e) {
                                                            \Illuminate\Support\Facades\Log::error('Manual YM Chat send error', [$e->getMessage()]);
                                                        }
                                                    }

                                                    // 4. Логируем действие
                                                    $order->comments()->create([
                                                        'user_id' => auth()->id(),
                                                        'user_type' => get_class(auth()->user()),
                                                        'comment' => __('admin.orders.logs.manual_issuance', ['name' => auth()->user()->name, 'code' => \Illuminate\Support\Str::mask($state, '*', 4, -4)]),
                                                    ]);

                                                    // 5. Проверяем статус всего заказа
                                                    $allItemsHasCode = $order->items()
                                                        ->where(fn ($q) => $q->whereNull('original_code')->where('id', '!=', $record->id))
                                                        ->doesntExist();

                                                    $order->update([
                                                        'progress_id' => $allItemsHasCode ? 4 : 3,
                                                    ]);

                                                    // 5. Уведомление в админке
                                                    Notification::make()
                                                        ->title(__('admin.orders.notifications.code_issued'))
                                                        ->success()
                                                        ->send();

                                                    $set('purchase_status', 'success');
                                                    $set('is_activated', true);
                                                })
                                        ),
                                ]),

                                TextInput::make('key')
                                    ->hidden($is_executor || $is_support)
                                    ->readOnly()
                                    ->required()
                                    ->unique(ignoreRecord: $is_update)
                                    ->label(__('admin.orders.key'))
                                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Str::mask($state, '*', 7, -4)),

                                Section::make('Опция')
                                    ->compact()
                                    ->hidden() // Полностью скрываем, так как выбор типа активации в заказе лишний
                                    ->schema([
                                        Select::make('type_id')
                                            ->relationship('type', 'name')
                                            ->label('Тип заказа')
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set) {
                                                $set('client_info.option.type_id', $get('type_id'));
                                            })
                                            ->default(1)
                                            ->preload()
                                            ->searchable(),
                                        DatePicker::make('client_info.option.ps_birthday')
                                            ->disabled(fn (Get $get) => $get('type_id') != 2)
                                            ->hidden(fn (Get $get) => $get('type_id') != 2)
                                            ->required(fn (Get $get) => $get('type_id') == 2)
                                            ->label(__('admin.customers.middle_name')),

                                        TextInput::make('client_info.option.ps_network_id')
                                            ->disabled(fn (Get $get) => $get('type_id') != 3)
                                            ->live()
                                            ->hidden(fn (Get $get) => $get('type_id') != 3)
                                            ->copyable()
                                            ->required(fn (Get $get) => $get('type_id') == 3)
                                            ->label('PS Network ID'),
                                        TextInput::make('client_info.option.ps_network_password')
                                            ->disabled(fn (Get $get) => $get('type_id') != 3)
                                            ->live(onBlur: true)
                                            ->copyable()
                                            ->hidden(fn (Get $get) => $get('type_id') != 3)
                                            ->required(fn (Get $get) => $get('type_id') == 3)
                                            ->label('PS Network Password'),
                                        TextInput::make('client_info.option.ps_2fa_code')
                                            ->live()
                                            ->copyable()
                                            ->disabled(fn (Get $get) => $get('type_id') != 3)
                                            ->hidden(fn (Get $get) => $get('type_id') != 3)
                                            ->label('Код 2FA'),

                                    ])->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),

                Section::make(__('admin.orders.sections.account_data'))
                    ->collapsible()
                    ->hidden(function () use ($order, $alts) {
                        if (! $order || $order->items->isEmpty()) {
                            return false;
                        }

                        return $order->items->every(fn ($item) => ($alts[$item->sku]['type'] ?? null) === 'voucher');
                    })
                    ->description(fn (Get $get) => $order->account_data_on_send ? __('admin.orders.helpers.account_data_sent') : __('admin.orders.helpers.account_data_not_sent'))
                    ->columnSpanFull()
                    ->headerActions([
                        Action::make('generate_account_data')
                            ->label(__('admin.orders.actions.generate'))
                            ->requiresConfirmation()
                            ->color('warning')
                            ->icon(Heroicon::ArrowPath)
                            ->modalHeading(__('admin.orders.modals.confirm_action'))
                            ->modalDescription(__('admin.orders.modals.generate_desc'))
                            ->modalSubmitActionLabel(__('admin.orders.actions.confirm'))
                            ->action(function (Set $set) use ($order) {

                                $user = $order->user;

                                $accountGenerator = new AccountGenerator;
                                $res = $accountGenerator->generateForOrder($user);

                                $set('meta.generated_account.login', $res['login']);
                                $set('meta.generated_account.password', $res['password']);

                                Notification::make()
                                    ->title(__('admin.orders.notifications.successfully_sent'))
                                    ->success()
                                    ->send();
                            })
                            ->disabled($order->account_data_on_send)
                            ->hidden(fn (Get $get) => (bool) $get('meta.generated_account.login')),
                        Action::make('send_account_data')
                            ->label(__('admin.orders.actions.send_email'))
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading(__('admin.orders.modals.confirm_action'))
                            ->icon(Heroicon::Envelope)
                            ->modalDescription(__('admin.orders.modals.send_email_desc'))
                            ->modalSubmitActionLabel(__('admin.orders.actions.confirm'))
                            ->action(function (Get $get) use ($order) {

                                $user = $order->user;
                                $email = $user->email;
                                $meta = $user->meta;

                                $login = $get('meta.generated_account.login');
                                $password = $get('meta.generated_account.password');
                                $codes = $get('meta.generated_account.codes');

                                if (! $email || ! $login || ! $password || ! $codes) {
                                    Notification::make()
                                        ->title(__('admin.common.view'))
                                        ->body(__('admin.orders.notifications.error_missing_data'))
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                Mail::to($email)->send(new SendAccountDataMail($login, $password, $codes));

                                Notification::make()
                                    ->title(__('admin.orders.notifications.successfully_sent'))
                                    ->success()
                                    ->send();

                                $order->update(['account_data_on_send' => true]);

                                $meta['generated_account']['codes'] = $codes;

                                $user->update(['meta' => $meta]);
                            })
                            ->disabled($order->account_data_on_send)
                            ->hidden(fn (Get $get) => ! $get('meta.generated_account.login')),
                    ])
                    ->schema([
                        TextInput::make('meta.generated_account.login')
                            ->label(__('admin.orders.fields.login'))
                            ->readOnly($order->account_data_on_send)
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.login', ''));
                            })
                            ->copyable(),

                        TextInput::make('meta.generated_account.password')
                            ->label(__('admin.orders.fields.password'))
                            ->password()
                            ->readOnly($order->account_data_on_send)
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.password', ''));
                            })
                            ->revealable()
                            ->copyable(),

                        Textarea::make('meta.generated_account.codes')
                            ->label(__('admin.orders.fields.2fa_codes'))
                            ->columnSpanFull()
                            ->readOnly($order->account_data_on_send)
                            ->disabled(fn (Get $get) => ! $get('meta.generated_account.login') || ! $get('meta.generated_account.password'))
                            ->afterStateHydrated(function (Textarea $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.codes'));
                            })
                            ->rows(10),

                    ])->columns()->visible(fn ($record) => $record->items->contains(fn ($item) => $item->type_id === 2)),
            ]);
    }
}
