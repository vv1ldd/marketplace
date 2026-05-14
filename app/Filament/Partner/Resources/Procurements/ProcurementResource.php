<?php

namespace App\Filament\Partner\Resources\Procurements;

use App\Models\Procurement;
use App\Models\Product;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProcurementResource extends Resource
{
    protected static ?string $model = Procurement::class;

    protected static bool $isScopedToTenant = true;
    
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Активации';

    protected static ?string $navigationLabel = 'История активаций';

    protected static ?string $label = 'Активация';

    protected static ?string $pluralLabel = 'История активаций';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Детали активации')
                ->schema([
                    Placeholder::make('balance_info')
                        ->label('Баланс организации')
                        ->content(function () {
                            $tenant = Filament::getTenant();
                            $balance = $tenant?->available_balance ?? 0;

                            return number_format($balance / 100, 2, '.', ' ').' ₽';
                        })
                        ->columnSpanFull(),

                    Select::make('product_id')
                        ->label('Товар')
                        ->relationship('product', 'name', fn (Builder $query) => 
                            $query->whereHas('shop', fn($q) => $q->where('legal_entity_id', Filament::getTenant()?->id))
                        )
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $product = Product::find($state);
                            $set('price_per_item', $product?->purchase_price_rub ?? 0);
                        }),

                    Select::make('warehouse_id')
                        ->label('Склад назначения')
                        ->relationship('warehouse', 'name', fn (Builder $query) => 
                            $query->whereHas('shop', fn($q) => $q->where('legal_entity_id', Filament::getTenant()?->id))
                        )
                        ->required()
                        ->searchable()
                        ->preload(),

                    TextInput::make('count')
                        ->label('Количество')
                        ->required()
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->live()
                        ->rules([
                            function ($get) {
                                return function (string $attribute, $value, Closure $fail) use ($get) {
                                    $price = (int) $get('price_per_item');
                                    $total = (int) $value * $price;
                                    $balance = Filament::getTenant()?->available_balance ?? 0;

                                    if ($total > $balance) {
                                        $fail('Недостаточно средств на балансе. Нужно: '.number_format($total / 100, 2).' ₽');
                                    }
                                };
                            },
                        ]),

                    TextInput::make('price_per_item')
                        ->label('Цена за ед. (коп.)')
                        ->required()
                        ->numeric()
                        ->prefix('₽')
                        ->disabled()
                        ->dehydrated(),

                    Placeholder::make('total_price_display')
                        ->label('Итого к оплате')
                        ->content(function ($get) {
                            $total = (int) $get('count') * (int) $get('price_per_item');

                            return number_format($total / 100, 2, '.', ' ').' ₽';
                        }),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад'),
                Tables\Columns\TextColumn::make('count')
                    ->label('Кол-во')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Сумма')
                    ->money('RUB', divideBy: 100)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ожидание',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                        default => $state,
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидание',
                        'completed' => 'Выполнено',
                        'cancelled' => 'Отменено',
                    ]),
            ])
            ->actions([
                ViewAction::make(),
                DeleteAction::make()
                    ->hidden(fn ($record) => $record->status === 'completed'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn ($records) => $records->contains('status', 'completed')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Partner\Resources\Procurements\Pages\ManageProcurements::route('/'),
        ];
    }
}
