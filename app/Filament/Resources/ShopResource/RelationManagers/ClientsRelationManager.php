<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\Users\Tables\UsersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = 'Клиенты';

    public function table(Table $table): Table
    {
        return UsersTable::configure($table)
            ->headerActions([]) // Remove create/add actions if you don't want to add clients to shop manually
            ->actions([
                // Keep view/edit if needed
                \Filament\Actions\ViewAction::make(),
            ]);
    }
}
