<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('admin.customers.name'))
                    ->getStateUsing(fn($record) => $record->getFullName()),
                TextColumn::make('roles')->label(__('admin.users.roles'))->getStateUsing(function ($record) {
                    return $record->roles()->pluck('name')->implode(', ');
                }),
                TextColumn::make('email')->searchable()->copyable()->label(__('admin.customers.email')),
                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->label(__('admin.customers.phone')),
                TextColumn::make('created_at')->label(__('admin.orders.created'))->dateTime('d.m.Y H:i:s'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('user_type')
                    ->label(__('admin.users.fields.type'))
                    ->options([
                        'system' => __('admin.users.staff'),
                        'clients' => __('admin.customers.customers'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if ($data['value'] === 'system') return $query->system();
                        if ($data['value'] === 'clients') return $query->clients();
                        return $query;
                    }),

            ])
            ->defaultSort(fn ($query) => $query->getModel()->getTable() . '.created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->paginationPageOptions([
                25, 50, 100
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
