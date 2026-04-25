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
                \Filament\Actions\AttachAction::make()
                    ->recordSelectSearchColumns(['email', 'first_name', 'last_name'])
                    ->recordSelectOptionsQuery(fn (\Illuminate\Database\Eloquent\Builder $query) => 
                        $query->whereHas('roles', fn ($q) => $q->whereIn('name', ['b2b_partner', 'super_admin', 'manager']))
                    )
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
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
                \Filament\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
