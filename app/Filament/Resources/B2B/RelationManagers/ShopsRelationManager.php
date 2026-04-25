<?php

namespace App\Filament\Resources\B2B\RelationManagers;

use App\Filament\Resources\ShopResource\Schemas\ShopForm;
use App\Filament\Resources\ShopResource\Tables\ShopsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ShopsRelationManager extends RelationManager
{
    protected static string $relationship = 'shops';

    protected static ?string $title = 'Связанные магазины';

    protected static ?string $label = 'Магазин';

    public function form(Schema $schema): Schema
    {
        return ShopForm::configure($schema);
    }

    public function table(Table $table): Table
    {
        return ShopsTable::configure($table)
            ->headerActions([
                \Filament\Tables\Actions\AssociateAction::make()
                    ->preloadRecordSelect(),
            ])
            ->actions([
                \Filament\Tables\Actions\DissociateAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DissociateBulkAction::make(),
                ]),
            ]);
    }
}
