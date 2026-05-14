<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.customers.name'))
                    ->getStateUsing(fn($record) => $record->getFullName())
                    ->searchable(['first_name', 'last_name', 'middle_name']),
                TextColumn::make('email')->searchable()->copyable()->label(__('admin.customers.email')),
                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->label(__('admin.customers.phone')),
                TextColumn::make('shop.name')
                    ->label(__('admin.users.shop'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.customers.registered'))
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('shop_id')
                    ->label(__('admin.users.shop'))
                    ->relationship('shop', 'name')
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->paginationPageOptions([
                25, 50, 100
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
