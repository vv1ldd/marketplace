<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Mail\SendAccountDataMail;
use App\Models\PlayStation\PlayStationAlt;
use App\Services\AccountGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
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

        $order_user_meta = $order->user?->meta ?? null;

        $is_create = !$order;
        $is_update = !$is_create;
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');
        $super_admin = auth()->user()->hasRole('super_admin');

        $skus = $order?->items?->pluck('sku')->filter()->unique()->toArray() ?? [];
        $alts = \App\Models\Product::whereIn('sku', $skus)
            ->get(['sku', 'name', 'price_rub', 'price_try'])
            ->keyBy('sku');


        return $schema
            ->components([
                Section::make('Заказ')->collapsible()->schema([
                    Grid::make(3)->schema([
                        TextInput::make('id')
                            ->label('Номер заказа')
                            ->readOnly($is_update)
                            ->copyable()
                            ->hidden($is_create)
                            ->required($is_create),
                        Select::make('user_id')
                            ->relationship('user', 'email')
                            ->hidden($is_executor || $is_support)
                            ->label('Юзер'),
                        Select::make('progress_id')
                            ->relationship('progress', 'name')
                            ->required()
                            ->label('Прогресс по заказу'),
                        Toggle::make('is_problem')
                            ->inline(false)
                            ->label('Проблемный заказ')
                            ->default(false),
                        DateTimePicker::make('created_at')
                            ->label('Дата создания')
                            ->disabled(),
//                        Textarea::make('comment')
//                            ->label('Комментарий')
//                            ->rows(2)
//                            ->columnSpanFull(),
                    ])

                ])->columnSpanFull(),

                Section::make('Информация')->collapsible()->schema([
                    TextInput::make('order_id')
                        ->label('Номер')
                        ->required(),
                    TextInput::make('status')
                        ->label('Статус'),
                    TextInput::make('sub_status')
                        ->label('Подстатус'),

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


                ])
                    ->hidden($is_executor)
                    ->columnSpanFull(),

                Section::make('Товары в заказе')
                    ->collapsible()
                    ->disabled($is_executor || $is_support)
                    ->schema([
                        Repeater::make('items_in_order')
                            ->relationship('items')
                            ->collapsible()
                            ->maxItems(100)
                            ->addActionLabel('Добавить товар')
                            ->addable(!$is_executor)
                            ->truncateItemLabel()
                            ->itemLabel(fn(array $state): ?string => \App\Models\Product::where('sku', $state['sku'])
                                ->value('name') ?? null)
                            ->columns(1)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('sku')
                                        ->label('SKU')
                                        ->copyable()
                                        ->required(),

                                    TextEntry::make('game_name')
                                        ->copyable()
                                        ->label('Название игры')
                                        ->state(fn(Get $get) => $alts[$get('sku')]->name ?? null),

                                    Grid::make()->schema([
                                        TextEntry::make('price_rub')
                                            ->label('Цена, руб')
                                            ->visible($super_admin)
                                            ->state(function (Get $get) use ($alts) {
                                                if ($price = $get('price_rub')) return $price / 100;
                                                return $alts[$get('sku')]->price_rub ? $alts[$get('sku')]->price_rub / 100 : null;
                                            }),
                                        TextEntry::make('price_try')
                                            ->label('Цена, лир')
                                            ->visible($super_admin || $is_executor)
                                            ->state(function (Get $get) use ($alts) {
                                                if ($price = $get('price_try')) return $price / 100;
                                                return $alts[$get('sku')]->price_try ? $alts[$get('sku')]->price_try / 100 : null;
                                            }),

                                    ])->columns(),

                                    TextInput::make('count')
                                        ->required()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(100)
                                        ->default(1)
                                        ->label('Количество'),


                                    Select::make('type_form_id')
                                        ->relationship('typeForm', 'name')
                                        ->label('Тип формы'),
                                ]),

                                Grid::make(3)->schema([
                                    Toggle::make('is_redeemed')
                                        ->default(false)
                                        ->inline(false)
                                        ->label('Код введен'),
                                    Toggle::make('is_activated')
                                        ->inline(false)
                                        ->default(false)
                                        ->label('Активирован'),
                                    Select::make('purchase_status')
                                        ->options([
                                            'none' => 'Нет',
                                            'pending' => 'В процессе',
                                            'success' => 'Успешно',
                                            'failed' => 'Ошибка',
                                            'manual' => 'Вручную',
                                        ])
                                        ->label('Статус закупки')
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            if (in_array($state, ['success', 'manual'])) {
                                                $set('is_activated', true);
                                                if (!$get('activated_at')) {
                                                    $set('activated_at', now());
                                                }
                                            }
                                        }),
                                ])->hidden($is_executor || $is_support),

                                Grid::make(2)->schema([
                                    DateTimePicker::make('activated_at')
                                        ->label('Дата активации')
                                        ->hidden($is_executor || $is_support),
                                    TextInput::make('original_code')
                                        ->label('Код продукта (Wildflow/Manual)')
                                        ->copyable()
                                        ->hidden($is_executor || $is_support)
                                        ->suffixAction(
                                            Action::make('manual_send')
                                                ->label('Выдать вручную')
                                                ->icon('heroicon-m-paper-airplane')
                                                ->color('success')
                                                ->requiresConfirmation()
                                                ->modalHeading('Ручная выдача кода')
                                                ->modalDescription('Вы действительно хотите выдать этот код клиенту вручную? Будет отправлено письмо и сообщение в чат Яндекс.Маркета (если доступно).')
                                                ->action(function ($state, $record, Set $set) {
                                                    if (!$state) {
                                                        Notification::make()->title('Сначала введите код')->danger()->send();
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
                                                        'comment' => "Менеджер " . auth()->user()->name . " вручную выдал код: " . \Illuminate\Support\Str::mask($state, '*', 4, -4)
                                                    ]);

                                                    // 5. Уведомление в админке
                                                    Notification::make()
                                                        ->title('Код успешно выдан и отправлен клиенту')
                                                        ->success()
                                                        ->send();
                                                        
                                                    $set('purchase_status', 'success');
                                                    $set('is_activated', true);
                                                })
                                        ),
                                ]),

                                Textarea::make('purchase_error')
                                    ->label('Ошибка закупки')
                                    ->readOnly()
                                    ->columnSpanFull()
                                    ->hidden(fn (Get $get) => !$get('purchase_error')),

                                TextInput::make('key')
                                    ->hidden($is_executor || $is_support)
                                    ->readOnly()
                                    ->required()
                                    ->unique(ignoreRecord: $is_update)
                                    ->label('Ключ'),

                                Grid::make(4)->schema([
                                    TextInput::make('client_info.first_name')
                                        ->label('Имя'),
                                    TextInput::make('client_info.last_name')
                                        ->label('Фамилия'),
                                    TextInput::make('client_info.email')
                                        ->email()
                                        ->label('Email'),
                                    TextInput::make('client_info.phone')
                                        ->mask('+79999999999')
                                        ->label('Телефон'),
                                ])->columnSpanFull()->hidden($is_executor || $is_support),

                                Section::make('Опция')
                                    ->compact()
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
                                            ->disabled(fn(Get $get) => $get('type_id') != 2)
                                            ->hidden(fn(Get $get) => $get('type_id') != 2)
                                            ->required(fn(Get $get) => $get('type_id') == 2)
                                            ->label('Дата рождения'),

                                        TextInput::make('client_info.option.ps_network_id')
                                            ->disabled(fn(Get $get) => $get('type_id') != 3)
                                            ->live()
                                            ->hidden(fn(Get $get) => $get('type_id') != 3)
                                            ->copyable()
                                            ->required(fn(Get $get) => $get('type_id') == 3)
                                            ->label('PS Network ID'),
                                        TextInput::make('client_info.option.ps_network_password')
                                            ->disabled(fn(Get $get) => $get('type_id') != 3)
                                            ->live(onBlur: true)
                                            ->copyable()
                                            ->hidden(fn(Get $get) => $get('type_id') != 3)
                                            ->required(fn(Get $get) => $get('type_id') == 3)
                                            ->label('PS Network Password'),
                                        TextInput::make('client_info.option.ps_2fa_code')
                                            ->live()
                                            ->copyable()
                                            ->disabled(fn(Get $get) => $get('type_id') != 3)
                                            ->hidden(fn(Get $get) => $get('type_id') != 3)
                                            ->label('Код 2FA'),

                                    ])->columnSpanFull(),
                            ])
                            ->hiddenLabel()
                    ])->columnSpanFull(),

                Section::make('Данные для создания аккаунта')
                    ->collapsible()
                    ->description(fn(Get $get) => $order->account_data_on_send ? 'Данные по аккаунту отправлены' : 'Данные по аккаунту не отправлены')
                    ->columnSpanFull()
                    ->id('generate_account_section')
                    ->headerActions([
                        Action::make('generate_account_data')
                            ->label('Генерация')
                            ->requiresConfirmation()
                            ->color('warning')
                            ->icon(Heroicon::ArrowPath)
                            ->modalHeading('Подтвердите действие')
                            ->modalDescription('При создании новых данных, старые будут затерты')
                            ->modalSubmitActionLabel('Подтвердить')
                            ->action(function (Set $set) use ($order) {

                                $user = $order->user;

                                $accountGenerator = new AccountGenerator();
                                $res = $accountGenerator->generateForOrder($user);

                                $set('meta.generated_account.login', $res['login']);
                                $set('meta.generated_account.password', $res['password']);

                                Notification::make()
                                    ->title('Аккаунт сгенерирован')
                                    ->success()
                                    ->send();
                            })
                            ->disabled($order->account_data_on_send)
                            ->hidden(fn(Get $get) => (bool)$get('meta.generated_account.login')),
                        Action::make('send_account_data')
                            ->label('Отправить данные')
                            ->color('success')
                            ->requiresConfirmation()
                            ->modalHeading('Подтвердите действие')
                            ->icon(Heroicon::Envelope)
                            ->modalDescription('Отправить клиенту на почту сгенерированные данные?')
                            ->modalSubmitActionLabel('Подтвердить')
                            ->action(function (Get $get) use ($order) {

                                $user = $order->user;
                                $email = $user->email;
                                $meta = $user->meta;

                                $login = $get('meta.generated_account.login');
                                $password = $get('meta.generated_account.password');
                                $codes = $get('meta.generated_account.codes');

                                if (!$email || !$login || !$password || !$codes) {
                                    Notification::make()
                                        ->title('Ошибка')
                                        ->body('Не хватает данных для отправки письма, проверьте заполнены ли все поля для отправки данных')
                                        ->danger()
                                        ->send();
                                    return;
                                }

                                Mail::to($email)->send(new SendAccountDataMail($login, $password, $codes));

                                Notification::make()
                                    ->title('Успешно отправлено')
                                    ->success()
                                    ->send();

                                $order->update(['account_data_on_send' => true]);

                                $meta['generated_account']['codes'] = $codes;

                                $user->update(['meta' => $meta]);
                            })
                            ->disabled($order->account_data_on_send)
                            ->hidden(fn(Get $get) => !$get('meta.generated_account.login')),
                    ])
                    ->schema([
                        TextInput::make('meta.generated_account.login')
                            ->label('Логин')
                            ->readOnly($order->account_data_on_send)
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.login', ''));
                            })
                            ->copyable(),

                        TextInput::make('meta.generated_account.password')
                            ->label('Пароль')
                            ->password()
                            ->readOnly($order->account_data_on_send)
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.password', ''));
                            })
                            ->revealable()
                            ->copyable(),

                        Textarea::make('meta.generated_account.codes')
                            ->label('2FA-коды')
                            ->columnSpanFull()
                            ->readOnly($order->account_data_on_send)
                            ->disabled(fn(Get $get) => !$get('meta.generated_account.login') || !$get('meta.generated_account.password'))
                            ->afterStateHydrated(function (Textarea $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.codes'));
                            })
                            ->rows(10)

                    ])->columns()->visible(fn($record) => $record->items->contains(fn($item) => $item->type_id === 2))
            ]);
    }
}
