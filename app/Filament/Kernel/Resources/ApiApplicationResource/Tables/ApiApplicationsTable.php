<?php

namespace App\Filament\Kernel\Resources\ApiApplicationResource\Tables;

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
                    ->label(__('admin.orders.type'))
                    ->badge()
                    ->color(fn ($state) => $state === ApiApplication::TYPE_PLATFORM ? 'warning' : 'info')
                    ->formatStateUsing(fn ($state) => $state === ApiApplication::TYPE_PLATFORM ? __('admin.settings.api_app_details.options.platform') : __('admin.settings.api_app_details.options.shop')),
                TextColumn::make('shop.name')
                    ->label(__('admin.shops.shop'))
                    ->sortable()
                    ->searchable()
                    ->placeholder('- Глобальный -'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('admin.products.name')),
                TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->label(__('admin.shops.fields.domain')),
                TextColumn::make('token')
                    ->limit(10)
                    ->copyable()
                    ->label(__('admin.settings.api_app_details.fields.token')),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('admin.settings.api_app_details.fields.is_active')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label(__('admin.orders.created')),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.settings.api_app_details.fields.access_level'))
                    ->options([
                        ApiApplication::TYPE_SHOP => __('admin.settings.api_app_details.options.shop'),
                        ApiApplication::TYPE_PLATFORM => __('admin.settings.api_app_details.options.platform'),
                    ]),
                SelectFilter::make('shop_id')
                    ->label(__('admin.shops.shop'))
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
