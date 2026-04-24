<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Users\Pages\EditUser;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        $is_executor = auth()->user()->hasRole('executor');
        $is_support = auth()->user()->hasRole('support');
        $is_super_admin = auth()->user()->hasRole('super_admin');

        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('Номер заказа')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('order_id')->label('Номер источника')
                    ->searchable()
                    ->hidden($is_executor || $is_support)
                    ->copyable(),
                TextColumn::make('status')->label('Статус источника')
                    ->hidden($is_executor || $is_support),
//                TextColumn::make('order_items_count')->label('Товаров')
//                    ->getStateUsing(fn($record) => $record->items()->count()),
                TextColumn::make('user.id')
                    ->label('Юзер')
                    ->hidden($is_executor || $is_support)
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
                    ->hidden($is_executor || $is_support)
                    ->icon(fn($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn($record) => $record->items()->where('is_redeemed', '<>', true)->exists() ? 'danger' : 'success')
                    ->label('Введен')
                    ->boolean(),
                IconColumn::make('items.is_activated')
                    ->hidden($is_executor || $is_support)
                    ->icon(fn($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'heroicon-s-x-circle' : 'heroicon-s-check-circle')
                    ->color(fn($record) => $record->items()->where('is_activated', '<>', true)->exists() ? 'danger' : 'success')
                    ->label('Активирован')
                    ->boolean(),
                TextColumn::make('purchase_status_display')
                    ->label('Закупка')
                    ->badge()
                    ->color(fn($state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        'manual' => 'info',
                        default => 'secondary',
                    })
                    ->getStateUsing(fn($record) => $record->items->first()?->purchase_status ?? 'none')
                    ->hidden($is_executor || $is_support),
                TextColumn::make('items.typeForm.name')->label('Тип')
                    ->limitList(1)
                    ->badge(),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i:s'),
                TextColumn::make('assigned_at')->label('Взят')
                    ->visible($is_super_admin)
                    ->dateTime('d.m.Y H:i:s'),
                TextColumn::make('updated_at')->label('Обновлен')->dateTime('d.m.Y H:i:s')
                    ->hidden($is_executor || $is_support),
            ])
            ->filters([
                SelectFilter::make('progress')
                    ->label('Прогресс')
                    ->multiple()
                    ->relationship('progress', 'name')
                    ->attribute('progress_id')
                    ->visible($is_super_admin),
                SelectFilter::make('shop_id')
                    ->label('Магазин')
                    ->relationship('shop', 'name'),
            ], layout: FiltersLayout::AboveContent)
            ->persistFiltersInSession()
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->deferFilters(false)
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->poll('10s')
            ->paginationPageOptions([
                20, 25, 50, 100,
            ]);
    }
}
