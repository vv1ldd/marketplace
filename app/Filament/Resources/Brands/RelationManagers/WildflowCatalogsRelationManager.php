<?php

namespace App\Filament\Resources\Brands\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WildflowCatalogsRelationManager extends RelationManager
{
    protected static string $relationship = 'wildflowCatalogs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('title')
                    ->label('Название')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('region.name_ru')
                    ->label('Регион')
                    ->formatStateUsing(fn ($record) => ($record->region?->flag ?? '').' '.($record->region?->name_ru ?? 'Глобально'))
                    ->sortable(),
                TextColumn::make('purchase_price')
                    ->label('Закупка')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->sortable(),
                TextColumn::make('retail_price')
                    ->label('Розница')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->sortable(),
                TextColumn::make('profit')
                    ->label('Прибыль')
                    ->money(fn ($record) => $record->currency_code ?? 'USD')
                    ->getStateUsing(fn ($record) => $record->retail_price - $record->purchase_price)
                    ->color('success')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->recordActions([
                \Filament\Tables\Actions\Action::make('post_to_telegram')
                    ->label('В Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('direct_channel_id')
                            ->label('Выберите канал')
                            ->options(\App\Models\DirectChannel::where('type', 'telegram_bot')->where('is_active', true)->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $channel = \App\Models\DirectChannel::find($data['direct_channel_id']);
                        
                        if (!$channel) {
                            \Filament\Notifications\Notification::make()
                                ->title('Ошибка')
                                ->body('Канал не найден')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Ищем соответствующий Product для этого айтема каталога
                        $product = \App\Models\Product::where('catalog_id', $record->id)->first();
                        
                        if (!$product) {
                            \Filament\Notifications\Notification::make()
                                ->title('Товар не найден')
                                ->body('Этот айтем каталога еще не импортирован в таблицу Products.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Используем наш существующий механизм постинга с указанием конкретного товара
                        \Illuminate\Support\Facades\Artisan::call('telegram:auto-post', [
                            '--channel' => $channel->id,
                            '--product' => $product->id,
                        ]);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Запощено!')
                            ->body("Товар отправлен в канал {$channel->name}")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
            ]);
    }
}
