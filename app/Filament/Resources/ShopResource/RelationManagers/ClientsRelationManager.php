<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Filament\Resources\Users\Tables\CustomersTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ClientsRelationManager extends RelationManager
{
    protected static string $relationship = 'clients';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('admin.shops.relations.clients');
    }

    public function table(Table $table): Table
    {
        return CustomersTable::configure($table)
            ->headerActions([])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }
}
