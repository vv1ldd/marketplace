<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Mail\SendAccountDataMail;
use App\Models\PlayStation\PlayStationAlt;
use App\Services\AccountGenerator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;;
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
        $alts = PlayStationAlt::whereIn('sku', $skus)
            ->get(['sku', 'name', 'woo_price_rub', 'woo_price_try'])
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
                            ->required()
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
                            ->minItems(1)
                            ->truncateItemLabel()
                            ->itemLabel(fn(array $state): ?string => PlayStationAlt::where('sku', $state['sku'])
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
                                            ->state(fn(Get $get) => $alts[$get('sku')]->woo_price_rub ?? null),
                                        TextEntry::make('price_try')
                                            ->label('Цена, лир')
                                            ->visible($super_admin || $is_executor)
                                            ->state(fn(Get $get) => $alts[$get('sku')]->woo_price_try ?? null),

                                    ])->columns(),

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
                                ])->hidden($is_executor || $is_support),

                                DateTimePicker::make('activated_at')
                                    ->label('Дата активации')
                                    ->hidden($is_executor || $is_support)
                                    ->required(),

                                TextInput::make('key')
                                    ->hidden($is_executor || $is_support)
                                    ->readOnly()
                                    ->required()
                                    ->unique(ignoreRecord: $is_update)
                                    ->label('Ключ'),

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
                                ])->columnSpanFull()->hidden($is_executor || $is_support),

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
                            ->readOnly()
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.login', ''));
                            })
                            ->copyable(),

                        TextInput::make('meta.generated_account.password')
                            ->label('Пароль')
                            ->password()
                            ->readOnly()
                            ->afterStateHydrated(function (TextInput $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.password', ''));
                            })
                            ->revealable()
                            ->copyable(),

                        Textarea::make('meta.generated_account.codes')
                            ->label('2FA-коды')
                            ->columnSpanFull()
                            ->disabled(fn(Get $get) => !$get('meta.generated_account.login') || !$get('meta.generated_account.password'))
                            ->afterStateHydrated(function (Textarea $component) use ($order_user_meta) {
                                $component->state(data_get($order_user_meta, 'generated_account.codes'));
                            })
                            ->rows(10)

                    ])->columns()->visible(fn($record) => $record->items->contains(fn($item) => $item->type_id === 2))
            ]);
    }
}
