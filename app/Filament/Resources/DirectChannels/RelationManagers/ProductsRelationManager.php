<?php

namespace App\Filament\Resources\DirectChannels\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Toggle::make('is_enabled')
                    ->label('Активен в канале'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('image')
                    ->label('')
                    ->circular(),
                
                TextColumn::make('name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('purchase_price')
                    ->label('Закупка')
                    ->money(fn ($record) => $record->purchase_currency ?? 'RUB')
                    ->sortable(),

                IconColumn::make('pivot.is_enabled')
                    ->label('Активен')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Выбрать из каталога')
                    ->preloadRecordSelect()
                    ->multiple()
                    ->form(fn (AttachAction $action) => [
                        $action->getRecordSelect(),
                        \Filament\Forms\Components\Toggle::make('is_enabled')
                            ->label('Активен сразу')
                            ->default(true),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
