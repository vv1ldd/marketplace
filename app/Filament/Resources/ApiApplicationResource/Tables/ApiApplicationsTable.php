<?php

namespace App\Filament\Resources\ApiApplicationResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use App\Models\ApiApplication;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->color(fn ($state) => $state === ApiApplication::TYPE_PLATFORM ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === ApiApplication::TYPE_PLATFORM ? 'Платформа' : 'Магазин'),
                TextColumn::make('shop.name')
                    ->label('Магазин')
                    ->sortable()
                    ->searchable()
                    ->placeholder('- Глобальный -'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Название'),
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->label('Домен'),
                TextColumn::make('token')
                    ->limit(10)
                    ->copyable()
                    ->label('Токен'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Активен'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Создано'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип доступа')
                    ->options([
                        ApiApplication::TYPE_SHOP => 'Магазин',
                        ApiApplication::TYPE_PLATFORM => 'Платформа',
                    ]),
                SelectFilter::make('shop_id')
                    ->label('Магазин')
                    ->relationship('shop', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
