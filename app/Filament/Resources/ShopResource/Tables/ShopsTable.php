<?php

namespace App\Filament\Resources\ShopResource\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        \App\Models\Shop::TYPE_VOUCHERS => 'Ваучеры',
                        \App\Models\Shop::TYPE_GAMES    => 'Игры',
                        \App\Models\Shop::TYPE_BOTH     => 'Ваучеры + Игры',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        \App\Models\Shop::TYPE_VOUCHERS => 'info',
                        \App\Models\Shop::TYPE_GAMES    => 'warning',
                        \App\Models\Shop::TYPE_BOTH     => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('campaign_id')
                    ->label('Campaign ID')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
                IconColumn::make('auto_purchase_enabled')
                    ->label('Автозакуп')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Создан')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
