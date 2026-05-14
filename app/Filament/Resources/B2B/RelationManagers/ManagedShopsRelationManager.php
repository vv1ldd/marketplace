<?php

namespace App\Filament\Resources\B2B\RelationManagers;

use App\Filament\Resources\ShopResource\Schemas\ShopForm;
use App\Filament\Resources\ShopResource\Tables\ShopsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ManagedShopsRelationManager extends RelationManager
{
    protected static string $relationship = 'managedShops';

    protected static ?string $title = 'Магазины в управлении';

    protected static ?string $label = 'Магазин';

    public function form(Schema $schema): Schema
    {
        return ShopForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ShopsTable::configure($table)
            ->headerActions([
                \Filament\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (\Filament\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        \Filament\Forms\Components\Select::make('role')
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
