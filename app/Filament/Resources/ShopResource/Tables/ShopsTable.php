<?php

namespace App\Filament\Resources\ShopResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.shops.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('campaign_id')
                    ->label(__('admin.shops.fields.campaign_id'))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('admin.shops.fields.is_active'))
                    ->boolean()
                    ->sortable(),
                IconColumn::make('auto_purchase_enabled')
                    ->label(__('admin.shops.fields.auto_purchase_enabled'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.orders.created'))
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
