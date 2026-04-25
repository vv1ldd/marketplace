<?php

namespace App\Filament\Resources\B2B\RelationManagers;

use App\Models\LegalEntity;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;

class LegalEntitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'legalEntities';

    protected static ?string $title = 'Организации партнера';

    protected static ?string $label = 'Организация';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Наименование')
                ->required(),
            TextInput::make('inn')
                ->label('ИНН')
                ->required()
                ->maxLength(12),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inn')
                    ->label('ИНН'),
                TextColumn::make('name')
                    ->label('Наименование'),
                TextColumn::make('shops_count')
                    ->label('Магазинов')
                    ->counts('shops'),
            ])
            ->headerActions([
                \Filament\Tables\Actions\CreateAction::make(),
                \Filament\Tables\Actions\AssociateAction::make()
                    ->preloadRecordSelect(),
            ])
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DissociateAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ]);
    }
}
