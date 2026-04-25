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
                    ->label('ФИО')
                    ->getStateUsing(fn($record) => $record->getFullName()),
                TextColumn::make('roles')->label('Роль')->getStateUsing(function ($record) {
                    return $record->roles()->pluck('name')->implode(', ');
                }),
                TextColumn::make('email')->searchable()->copyable()->label('Email'),
                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->label('Телефон'),
                TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i:s'),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('user_type')
                    ->label('Тип пользователя')
                    ->options([
                        'system' => 'Сотрудники',
                        'clients' => 'Клиенты',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data) {
                        if ($data['value'] === 'system') return $query->system();
                        if ($data['value'] === 'clients') return $query->clients();
                        return $query;
                    }),
                \Filament\Tables\Filters\SelectFilter::make('shop')
                    ->label('Магазин')
                    ->relationship('shops', 'name')
            ])
            ->defaultSort('created_at', 'desc')
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
