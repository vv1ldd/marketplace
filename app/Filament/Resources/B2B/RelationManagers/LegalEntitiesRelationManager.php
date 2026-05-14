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
    protected static string $relationship = 'managedLegalEntities';

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
                \Filament\Actions\CreateAction::make(),
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        \Filament\Forms\Components\Select::make('role')
                            ->label(__('admin.users.role'))
                            ->options([
                                'owner' => __('admin.shops.relations.roles.owner'),
                                'manager' => __('admin.shops.relations.roles.manager'),
                                'viewer' => __('admin.shops.relations.roles.viewer'),
                            ])
                            ->required()
                            ->default('manager'),
                    ]),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DetachAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }
}
