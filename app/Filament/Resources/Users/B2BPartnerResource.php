<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateB2BPartner;
use App\Filament\Resources\Users\Pages\EditB2BPartner;
use App\Filament\Resources\Users\Pages\ListB2BPartners;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class B2BPartnerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';

    protected static string | \UnitEnum | null $navigationGroup = 'Управление';

    protected static ?string $label = 'B2B Партнер';

    protected static ?string $pluralLabel = 'B2B Партнеры';

    protected static ?string $slug = 'b2b-partners';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->role('b2b_partner');
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
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
