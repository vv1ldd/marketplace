<?php

namespace App\Filament\Support\Resources;

use App\Filament\Support\Resources\ClientResource\Pages;
use App\Filament\Resources\Users\Schemas\CustomerForm;
use App\Filament\Resources\Users\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $slug = 'clients';

    public static function getNavigationGroup(): ?string
    {
        return 'Клиенты';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;
    protected static ?string $navigationLabel = 'Все клиенты';
    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'admin.customers.customer';
    protected static ?string $pluralLabel = 'admin.customers.customers';
    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::count();
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}
