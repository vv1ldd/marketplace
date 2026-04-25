<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ManagersRelationManager extends RelationManager
{
    protected static string $relationship = 'managers';

    protected static ?string $title = 'Менеджеры (B2B партнеры)';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('Email'),
                Tables\Columns\TextColumn::make('pivot.role')->label('Роль')->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                \Filament\Tables\Actions\AttachAction::make()
                    ->form(fn (\Filament\Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Select::make('role')
                            ->label('Роль')
                            ->options([
                                'owner' => 'Владелец',
                                'manager' => 'Менеджер',
                                'viewer' => 'Наблюдатель',
                            ])
                            ->required()
                            ->default('manager'),
                    ]),
            ])
            ->actions([
                \Filament\Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
