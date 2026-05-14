<?php

namespace App\Filament\Resources\DirectChannels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class DirectChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                
                \Filament\Tables\Columns\TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'telegram_bot' => 'Telegram Bot',
                        'yandex_market' => 'Яндекс Маркет',
                        'woocommerce' => 'WooCommerce',
                        'offline' => 'Оффлайн',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'telegram_bot' => 'success',
                        'yandex_market' => 'warning',
                        'woocommerce' => 'info',
                        'offline' => 'gray',
                        default => 'primary',
                    }),
                    
                \Filament\Tables\Columns\IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')
                    ->label('Тип')
                    ->options([
                        'telegram_bot' => 'Telegram Bot',
                        'yandex_market' => 'Яндекс Маркет',
                        'woocommerce' => 'WooCommerce',
                        'offline' => 'Оффлайн',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
