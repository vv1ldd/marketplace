<?php

namespace App\Filament\Kernel\Resources;

use App\Filament\Kernel\Resources\ApiApplicationResource\Pages\CreateApiApplication;
use App\Filament\Kernel\Resources\ApiApplicationResource\Pages\EditApiApplication;
use App\Filament\Kernel\Resources\ApiApplicationResource\Pages\ListApiApplications;
use App\Filament\Kernel\Resources\ApiApplicationResource\Schemas\ApiApplicationForm;
use App\Filament\Kernel\Resources\ApiApplicationResource\Tables\ApiApplicationsTable;
use App\Models\ApiApplication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ApiApplicationResource extends Resource
{
    protected static ?string $model = ApiApplication::class;

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.api_apps');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Администрирование';
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Key;

    protected static ?int $navigationSort = 101;

    protected static ?string $label = 'admin.settings.api_app';

    protected static ?string $pluralLabel = 'admin.settings.api_apps';

    public static function form(Schema $schema): Schema
    {
        return ApiApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApiApplicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApiApplications::route('/'),
            'create' => CreateApiApplication::route('/create'),
            'edit' => EditApiApplication::route('/{record}/edit'),
        ];
    }
}
