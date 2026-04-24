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
            ->select(['id', 'sku', 'name', 'type', 'price_rub', 'price_try', 'is_active', 'created_at']);
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
                            TextInput::make('price_try')
                                ->label('Цена (лир.)')
                                ->numeric()
                                ->prefix('TL'),
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
            ])->headerActions([
                \Filament\Actions\Action::make('generate_images')
                    ->label('Сгенерировать картинки')
                    ->icon('heroicon-o-photo')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function () {
                        $products = \App\Models\Product::whereNull('image')->limit(50)->get();
                        $generator = new \App\Services\ImageGenerator();
                        $count = 0;
                        foreach ($products as $product) {
                            try {
                                $data = $product->data['data'] ?? [];
                                $generateData = [
                                    'sku' => $product->sku,
                                    'price' => $data['price'] ?? 0,
                                    'symbol' => $data['product']['currency']['symbol'] ?? ($product->type === 'playstation' ? ' TL' : ''),
                                    'category' => $product->data['category'] ?? ($product->type === 'playstation' ? 'ps' : 'other'),
                                    'region_code' => $data['product']['regions'][0]['code'] ?? 'TR',
                                ];
                                
                                $path = $generator->generate($generateData);
                                $product->update([
                                    'image' => $path,
                                    'image_updated_at' => now(),
                                ]);
                                $count++;
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error("Image generation failed for product {$product->sku}: " . $e->getMessage());
                            }
                        }
                        \Filament\Notifications\Notification::make()->title("Сгенерировано картинок: $count")->success()->send();
                    })
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
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'game' => 'info',
                        'voucher' => 'success',
                        'service' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('price_rub')
                    ->label('Цена (руб.)')
                    ->state(fn ($record) => $record->price_rub ? $record->price_rub / 100 . ' ₽' : '-')
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
                EditAction::make(),
                DeleteAction::make(),
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
