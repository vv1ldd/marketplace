<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Номер заказа')->sortable(),
                TextColumn::make('order_id')->label('Заказ YM')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')->label('Статус YM'),
                TextColumn::make('order_items_count')->label('Кол-во товаров')
                    ->getStateUsing(fn($record) => $record->items()->count()),
                TextColumn::make('user.first_name')
                    ->label('Юзер')
                    ->url(fn($record) => $record->user?->id ? EditUser::getUrl(['record' => $record->user->id]) : null, true),
                TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i:s'),
                TextColumn::make('updated_at')->label('Обновлено')->dateTime('d.m.Y H:i:s'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->paginationPageOptions([
                25, 50, 100
            ]);
    }
}
