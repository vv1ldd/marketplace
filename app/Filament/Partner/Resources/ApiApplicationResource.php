<?php

namespace App\Filament\Partner\Resources;

use App\Models\ApiApplication;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;

class ApiApplicationResource extends Resource
{
    protected static ?string $model = ApiApplication::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('admin.api_apps.title');
    }

    public static function getLabel(): string
    {
        return __('admin.api_apps.title');
    }

    public static function getPluralLabel(): string
    {
        return __('admin.api_apps.title');
    }

    /*
     * Scope to current shop
     */


    public static function form(\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                \Filament\Schemas\Components\Section::make(__('admin.settings.api_app_details.sections.app_info'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('admin.settings.api_app_details.fields.app_name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('token')
                            ->label(__('admin.settings.api_app_details.fields.token'))
                            ->default(fn () => \Illuminate\Support\Str::random(64))
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('generateToken')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function ($set) {
                                        $set('token', \Illuminate\Support\Str::random(64));
                                    })
                            ),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('admin.settings.api_app_details.fields.is_active'))
                            ->default(true),

                        Forms\Components\Select::make('shop_id')
                            ->label('Магазин')
                            ->options(fn() => Filament::getTenant()->shops()->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Hidden::make('type')
                            ->default(\App\Models\ApiApplication::TYPE_SHOP),
                    ])->columns(2),
            ]);
    }

    protected static bool $isScopedToTenant = true;
    
    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.settings.api_app_details.fields.app_name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('token')
                    ->label(__('admin.settings.api_app_details.fields.token'))
                    ->copyable()
                    ->limit(20)
                    ->toggledHiddenByDefault(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.settings.api_app_details.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ApiApplicationResource\Pages\ListApiApplications::route('/'),
            'create' => ApiApplicationResource\Pages\CreateApiApplication::route('/create'),
            'edit' => ApiApplicationResource\Pages\EditApiApplication::route('/{record}/edit'),
        ];
    }
}
