<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\Clients\CreateClient;
use App\Filament\Resources\Users\Pages\Clients\EditClient;
use App\Filament\Resources\Users\Pages\Clients\ListClients;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::UserGroup;
    protected static string|null|\UnitEnum $navigationGroup = null; // Move out of 'Management'
    protected static ?string $navigationLabel = 'Клиенты';
    protected static ?int $navigationSort = 2;

    protected static ?string $label = 'Клиента';
    protected static ?string $pluralLabel = 'Клиенты';
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', \App\Models\User::SYSTEM_ROLES);
            });
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::clients()->count();
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClients::route('/'),
            'create' => CreateClient::route('/create'),
            'edit' => EditClient::route('/{record}/edit'),
        ];
    }
}
