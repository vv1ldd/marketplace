<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ->searchable()
                    ->getStateUsing(fn ($record) => $record->getFullName()),
                TextColumn::make('roles')->label('Роль')->getStateUsing(function ($record) {
                    return $record->roles()->pluck('name')->implode(', ');
                }),
                TextColumn::make('email')->searchable()->label('Email'),
                TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i:s'),
            ])
            ->filters([
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
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
