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

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static string | \UnitEnum | null $navigationGroup = 'Каталог';

    protected static ?string $label = 'Товар';

    protected static ?string $pluralLabel = 'Товары';

    protected static ?int $navigationSort = 1;

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
                        Select::make('type')
                            ->label('Тип товара')
                            ->options([
                                'game' => 'Игра',
                                'voucher' => 'Ваучер',
                                'service' => 'Сервис',
                            ])
                            ->required(),
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
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
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
                //
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
