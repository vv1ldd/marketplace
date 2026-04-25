<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use App\Http\Services\YmService;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $recordTitleAttribute = null;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static string | \UnitEnum | null $navigationGroup = 'Каталог';

    protected static ?string $label = 'Товар';

    protected static ?string $pluralLabel = 'Товары';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->select(['id', 'sku', 'name', 'type', 'category', 'price_rub', 'purchase_price', 'purchase_currency', 'is_active', 'created_at']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Основная информация')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('sku')
                                ->label('SKU')
                                ->required()
                                ->unique(ignoreRecord: true),
                            TextInput::make('name')
                                ->label('Название')
                                ->required()
                                ->columnSpan(2),
                        ]),
                        Grid::make(2)->schema([
                            Select::make('type')
                                ->label('Провайдер')
                                ->options([
                                    'playstation' => 'PlayStation',
                                    'wildflow' => 'Wildflow',
                                ])
                                ->required(),
                            Select::make('category')
                                ->label('Категория')
                                ->options([
                                    'game' => 'Игра',
                                    'gift-card' => 'Подарочная карта',
                                    'subscription' => 'Подписка',
                                    'other' => 'Прочее',
                                ])
                                ->required(),
                        ]),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3),
                    ]),
                Section::make('Цены и Активация')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('price_rub')
                                ->label('Цена (руб.)')
                                ->numeric()
                                ->prefix('₽'),
                            TextInput::make('purchase_price')
                                ->label('Закупочная цена')
                                ->numeric(),
                            Select::make('purchase_currency')
                                ->label('Валюта закупки')
                                ->options([
                                    'TRY' => 'TL (Лиры)',
                                    'USD' => '$ (Доллары)',
                                    'EUR' => '€ (Евро)',
                                    'KZT' => '₸ (Тенге)',
                                ])
                                ->default('TRY'),
                            TextInput::make('base_price')
                                ->label('Базовая цена')
                                ->numeric(),
                        ]),
                        Grid::make(2)->schema([
                            Select::make('type_form_id')
                                ->relationship('typeForm', 'name')
                                ->label('Тип формы активации'),
                            Toggle::make('is_manual')
                                ->label('Ручная обработка')
                                ->inline(false),
                            Toggle::make('is_active')
                                ->label('Активен')
                                ->default(true)
                                ->inline(false),
                            \Filament\Forms\Components\FileUpload::make('image')
                                ->image()
                                ->imageEditor()
                                ->directory('img/card')
                                ->label('Карточка товара')
                                ->disabled()
                                ->helperText('Генерируется автоматически'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'playstation' => 'info',
                        'wildflow' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('category')
                    ->label('Категория')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'game' => 'Игра',
                        'gift-card' => 'Гифт-карта',
                        'subscription' => 'Подписка',
                        'other' => 'Прочее',
                        default => $state,
                    }),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('price_rub')
                    ->label('Цена (руб.)')
                    ->state(fn ($record) => $record->price_rub ? $record->price_rub / 100 . ' ₽' : '-')
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('Закупка')
                    ->state(fn ($record) => $record->purchase_price ? ($record->purchase_price / 100) . ' ' . $record->purchase_currency : '-')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'game' => 'Игры',
                        'gift-card' => 'Гифт-карты',
                        'subscription' => 'Подписки',
                    ])
                    ->label('Категория'),
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'playstation' => 'PlayStation',
                        'wildflow' => 'Wildflow',
                    ])
                    ->label('Провайдер'),
            ])
            ->actions([
                Action::make('send_this_to_market')
                    ->label('Залить')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->form([
                        Select::make('shop_id')
                            ->label('Магазин')
                            ->options(\App\Models\Shop::whereNotNull('api_key')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (\App\Models\Product $record, array $data) {
                        $shop = \App\Models\Shop::find($data['shop_id']);
                        $service = new YmService($shop);
                        $categoryId = (int)\App\Models\Settings::get('YM_CATEGORY_ID', 70301474);
                        
                        $offer = ["offer" => $record->toYmOffer($categoryId)];
                        $service->offerMappingsUpdate([$offer]);

                        Notification::make()
                            ->title('Товар отправлен в магазин: ' . $shop->name)
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('sync_ps')
                    ->label('Синхронизировать PS')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function () {
                        Artisan::call('ps:sync-to-products');
                        Notification::make()
                            ->title('Синхронизация PlayStation запущена')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
                Action::make('send_to_market')
                    ->label('Залить на Маркет (Выборочно)')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('success')
                    ->form([
                        Select::make('shop_id')
                            ->label('Магазин')
                            ->options(\App\Models\Shop::whereNotNull('api_key')->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $shop = \App\Models\Shop::find($data['shop_id']);
                        $products = \App\Models\Product::where('is_active', true)->get();
                        $service = new YmService($shop);
                        $categoryId = (int)\App\Models\Settings::get('YM_CATEGORY_ID', 70301474);
                        
                        $offers = $products->map(fn($p) => ["offer" => $p->toYmOffer($categoryId)])->toArray();
                        $chunks = array_chunk($offers, 20);
                        
                        foreach ($chunks as $chunk) {
                            $service->offerMappingsUpdate($chunk);
                        }

                        Notification::make()
                            ->title('Товары отправлены в магазин: ' . $shop->name)
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
