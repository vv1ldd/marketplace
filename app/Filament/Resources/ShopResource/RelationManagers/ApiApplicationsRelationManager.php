<?php

namespace App\Filament\Resources\ShopResource\RelationManagers;

use App\Models\ApiApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Schemas\Schema;

class ApiApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'apiApplications';

    protected static ?string $title = null;

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('admin.shops.relations.api_apps');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.settings.api_app_details.fields.app_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('domain')
                ->label(__('admin.settings.api_app_details.fields.domain'))
                ->placeholder('example.com')
                ->maxLength(255),
            Select::make('type')
                ->label(__('admin.settings.api_app_details.fields.access_level'))
                ->options([
                    ApiApplication::TYPE_SHOP => __('admin.settings.api_app_details.options.shop_access'),
                    ApiApplication::TYPE_PLATFORM => __('admin.settings.api_app_details.options.platform_access'),
                ])
                ->default(ApiApplication::TYPE_SHOP)
                ->required(),
            TextInput::make('token')
                ->label(__('admin.settings.api_app_details.fields.token'))
                ->password()
                ->revealable()
                ->default(fn () => \Illuminate\Support\Str::random(60))
                ->required(),
            \Filament\Forms\Components\Toggle::make('is_active')
                ->label(__('admin.settings.api_app_details.fields.is_active'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.settings.api_app_details.fields.app_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('admin.settings.api_app_details.fields.access_level'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        ApiApplication::TYPE_SHOP => __('admin.settings.api_app_details.options.shop'),
                        ApiApplication::TYPE_PLATFORM => __('admin.settings.api_app_details.options.platform'),
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        ApiApplication::TYPE_SHOP => 'info',
                        ApiApplication::TYPE_PLATFORM => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('token')
                    ->label(__('admin.settings.api_app_details.fields.token'))
                    ->limit(10)
                    ->copyable(),
                IconColumn::make('is_active')
                    ->label(__('admin.settings.api_app_details.fields.is_active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
