<?php

namespace App\Filament\Client\Resources;

use App\Models\Order\Order;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;

use BackedEnum;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    public static function getNavigationLabel(): string
    {
        return 'Мои заказы';
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Детали заказа')
                    ->schema([
                        Grid::make(3)->schema([
                            Html::make(fn ($record) => $record->id)->label('Номер заказа'),
                            Html::make(fn ($record) => $record->created_at->format('d.m.Y H:i'))->label('Дата'),
                            Html::make(fn ($record) => $record->progress?->name)->label('Статус'),
                        ]),
                    ]),

                Section::make('Купленные товары')
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->deletable(false)
                            ->addable(false)
                            ->schema([
                                Grid::make(2)->schema([
                                    Html::make(fn ($record) => $record->sku)->label('Товар'),
                                    TextInput::make('original_code')
                                        ->label('Ваш код активации')
                                        ->readOnly()
                                        ->copyable()
                                        ->visible(fn ($record) => filled($record->original_code)),
                                ]),
                            ])
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('№')->sortable(),
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('progress.name')->label('Статус')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Обработан полностью' => 'success',
                        'В обработке' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('items_count')->label('Товаров')->counts('items'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Client\Resources\OrderResource\Pages\ListOrders::route('/'),
            'view' => \App\Filament\Client\Resources\OrderResource\Pages\ViewOrder::route('/{record}'),
        ];
    }
}
