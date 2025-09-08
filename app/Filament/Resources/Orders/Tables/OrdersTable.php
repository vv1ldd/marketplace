<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Номер заказа')->sortable(),
                TextColumn::make('order_id')->label('Номер источника')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('status')->label('Статус источника'),
                TextColumn::make('order_items_count')->label('Товаров')
                    ->getStateUsing(fn($record) => $record->items()->count()),
                TextColumn::make('user.id')
                    ->label('Юзер')
                    ->url(fn($record) => $record->user?->id ? EditUser::getUrl(['record' => $record->user->id]) : null, true),
                TextColumn::make('progress.name')->label('Прогресс')
                    ->sortable()
                    ->color(function ($record) {
                        return match ($record->progress_id) {
                            1 => 'warning',
                            4 => 'success',
                            default => 'secondary',
                        };
                    })
                    ->badge(),
                IconColumn::make('items.is_redeemed')
                    ->icon(fn($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'danger' : 'success')
                    ->label('Введен')
                    ->boolean(),
                IconColumn::make('items.is_activated')
                    ->icon(fn($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'danger' : 'success')
                    ->label('Активирован')
                    ->boolean(),
                TextColumn::make('items.typeForm.name')->label('Тип')
                    ->limitList(1)
                    ->badge(),
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
