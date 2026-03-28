<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiApplicationResource\Pages\CreateApiApplication;
use App\Filament\Resources\ApiApplicationResource\Pages\EditApiApplication;
use App\Filament\Resources\ApiApplicationResource\Pages\ListApiApplications;
use App\Filament\Resources\ApiApplicationResource\Schemas\ApiApplicationForm;
use App\Filament\Resources\ApiApplicationResource\Tables\ApiApplicationsTable;
use App\Models\ApiApplication;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ApiApplicationResource extends Resource
{
    protected static ?string $model = ApiApplication::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-key';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?string $label = 'API Приложение';

    protected static ?string $pluralLabel = 'API Приложения';

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
