<?php

namespace App\Filament\Resources\B2B\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ManagersRelationManager extends RelationManager
{
    protected static string $relationship = 'managers';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('admin.shops.relations.managers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')->label(__('admin.customers.email')),
                Tables\Columns\TextColumn::make('pivot.role')->label(__('admin.users.role'))->badge(),
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
                \Filament\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
