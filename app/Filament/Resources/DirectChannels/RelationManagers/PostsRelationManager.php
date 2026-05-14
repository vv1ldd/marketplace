<?php

namespace App\Filament\Resources\DirectChannels\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'История автопостинга и статистика';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('wildflow_catalog_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('wildflow_catalog_id')
            ->columns([
                TextColumn::make('product.name')
                    ->label('Товар')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('posted_price')
                    ->label('Цена (₽)')
                    ->numeric()
                    ->sortable(),
                
                TextColumn::make('clicks')
                    ->label('Клики')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('Дата поста')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Посты создаются только через команду, запрещаем ручное создание
            ])
            ->recordActions([
                // Только просмотр статистики
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
