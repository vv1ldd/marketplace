<?php

namespace App\Filament\Partner\Resources\ProductResource\RelationManagers;

use App\Http\Services\YmService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $title = 'Остатки по складам';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('warehouse_id')
                    ->label('Склад')
                    ->relationship('warehouse', 'name', fn (Builder $query) => $query->where('shop_id', $this->getOwnerRecord()->shop_id))
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('count')
                    ->label('Количество')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('warehouse_id')
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Склад')
                    ->sortable()
                    ->description(fn ($record) => $record->warehouse->is_main ? 'Центральный склад' : null)
                    ->icon(fn ($record) => $record->warehouse->is_main ? 'heroicon-o-star' : null)
                    ->iconColor('warning'),
                Tables\Columns\TextColumn::make('warehouse.ym_id')
                    ->label('ID Яндекса'),
                Tables\Columns\TextInputColumn::make('count')
                    ->label('Остаток')
                    ->type('number')
                    ->sortable(),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Синхронизировано')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make()
                    ->label('Добавить на склад'),
                \Filament\Actions\Action::make('syncAll')
                    ->label('Отправить в Яндекс')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->action(function () {
                        $product = $this->getOwnerRecord();
                        $shop = $product->shop;
                        $stocks = $product->stocks()->with('warehouse')->get();

                        if ($stocks->isEmpty()) {
                            Notification::make()->title('Нет остатков для синхронизации')->warning()->send();

                            return;
                        }

                        $service = new YmService($shop);
                        $skus = $stocks->map(function ($stock) use ($product) {
                            return [
                                'sku' => $product->sku,
                                'warehouseId' => (int) $stock->warehouse->ym_id,
                                'items' => [
                                    [
                                        'count' => (int) $stock->count,
                                        'type' => 'FIT',
                                        'updatedAt' => now()->toIso8601String(),
                                    ],
                                ],
                            ];
                        })->toArray();

                        try {
                            $service->updateStocks($skus);

                            $product->stocks()->update(['synced_at' => now()]);

                            Notification::make()
                                ->title('Остатки товара успешно отправлены в Яндекс')
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Ошибка синхронизации')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
