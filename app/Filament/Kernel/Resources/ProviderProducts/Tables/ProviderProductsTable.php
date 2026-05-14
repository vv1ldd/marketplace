<?php

namespace App\Filament\Kernel\Resources\ProviderProducts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;

class ProviderProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular(),
                TextColumn::make('provider.name')
                    ->label('Провайдер')
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('purchase_price')
                    ->label('Закупка')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable(),
                TextColumn::make('retail_price')
                    ->label('Розница')
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable(),
                TextColumn::make('currency')
                    ->label('Валюта')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('add_to_direct_channel')
                    ->label('В канал продаж')
                    ->icon('heroicon-m-plus-circle')
                    ->color('primary')
                    ->form([
                        \Filament\Forms\Components\Select::make('channel_id')
                            ->label('Выберите канал')
                            ->options(\App\Models\DirectChannel::pluck('name', 'id'))
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_enabled')
                            ->label('Активен сразу')
                            ->default(true),
                    ])
                    ->action(function ($record, array $data) {
                        // 1. Find or Create global Product
                        $product = \App\Models\Product::where('sku', $record->sku)
                            ->whereNull('shop_id')
                            ->first();
                        
                        if (!$product) {
                            $product = \App\Models\Product::create([
                                'provider_id' => $record->provider_id,
                                'brand_id' => $record->brand_id,
                                'sku' => $record->sku,
                                'name' => $record->name,
                                'purchase_price_rub' => (int) round($record->retail_price * app(\App\Services\FinanceService::class)->getRate($record->currency) * 100),
                                'is_active' => true,
                                'image' => $record->image,
                                'data' => $record->data,
                            ]);
                        }

                        // 2. Attach to channel
                        $channel = \App\Models\DirectChannel::find($data['channel_id']);
                        $channel->products()->syncWithoutDetaching([
                            $product->id => ['is_enabled' => $data['is_enabled']]
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Товар добавлен в канал')
                            ->body("«{$record->name}» теперь в ассортименте канала «{$channel->name}».")
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
