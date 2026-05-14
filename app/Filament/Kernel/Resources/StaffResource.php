<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\StaffResource\Pages;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $slug = 'staff';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;
    
    public static function getNavigationLabel(): string
    {
        return __('admin.users.staff');
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Администрирование';
    }

    protected static ?int $navigationSort = 100;

    public static function getLabel(): ?string
    {
        return __('admin.users.user');
    }

    public static function getPluralLabel(): ?string
    {
        return __('admin.users.staff');
    }

    protected static bool $hasTitleCaseModelLabel = false;

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', function ($q) {
                $q->whereIn('name', \App\Models\User::SYSTEM_ROLES);
            });
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$model::system()->count();
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaff::route('/'),
            'create' => Pages\CreateStaff::route('/create'),
            'edit' => Pages\EditStaff::route('/{record}/edit'),
        ];
    }
}
