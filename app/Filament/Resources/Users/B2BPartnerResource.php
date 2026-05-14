<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\B2B\RelationManagers\LegalEntitiesRelationManager;
use App\Filament\Resources\Users\Pages\CreateB2BPartner;
use App\Filament\Resources\Users\Pages\EditB2BPartner;
use App\Filament\Resources\Users\Pages\ListB2BPartners;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\Seller;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class B2BPartnerResource extends Resource
{
    protected static ?string $model = Seller::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return __('admin.users.partners');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Магазины и B2B';
    }

    protected static ?int $navigationSort = 21;

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function getLabel(): ?string
    {
        return __('admin.users.partner');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.users.partners');
    }

    protected static ?string $slug = 'b2b-partners';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function getRelations(): array
    {
        return [
            LegalEntitiesRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListB2BPartners::route('/'),
            'create' => CreateB2BPartner::route('/create'),
            'edit' => EditB2BPartner::route('/{record}/edit'),
        ];
    }
}
