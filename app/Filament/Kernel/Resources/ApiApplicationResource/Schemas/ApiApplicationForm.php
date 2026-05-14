<?php

namespace App\Filament\Kernel\Resources\ApiApplicationResource\Schemas;
 
use App\Models\ApiApplication;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
 
class ApiApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.settings.api_app_details.sections.access_type'))
                    ->schema([
                        Radio::make('type')
                            ->label(__('admin.settings.api_app_details.fields.access_level'))
                            ->options([
                                ApiApplication::TYPE_SHOP => __('admin.settings.api_app_details.options.shop_access'),
                                ApiApplication::TYPE_PLATFORM => __('admin.settings.api_app_details.options.platform_access'),
                            ])
                            ->default(ApiApplication::TYPE_SHOP)
                            ->hidden(fn ($livewire) => $livewire instanceof \Filament\Resources\RelationManagers\RelationManager)
                            ->live()
                            ->required(),
                        Select::make('shop_id')
                            ->label(__('admin.settings.api_app_details.fields.select_shop'))
                            ->relationship('shop', 'name')
                            ->visible(fn ($get, $livewire) => $get('type') === ApiApplication::TYPE_SHOP && !($livewire instanceof \Filament\Resources\RelationManagers\RelationManager))
                            ->required(fn ($get) => $get('type') === ApiApplication::TYPE_SHOP),
                    ]),

                Section::make(__('admin.settings.api_app_details.sections.app_info'))
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label(__('admin.settings.api_app_details.fields.app_name')),
                        TextInput::make('first_name')
                            ->maxLength(255)
                            ->label(__('admin.settings.api_app_details.fields.first_name')),
                        TextInput::make('last_name')
                            ->maxLength(255)
                            ->label(__('admin.settings.api_app_details.fields.last_name')),
                        TextInput::make('phone')
                            ->maxLength(255)
                            ->label(__('admin.settings.api_app_details.fields.phone')),
                        TextInput::make('domain')
                            ->maxLength(255)
                            ->label(__('admin.settings.api_app_details.fields.domain'))
                            ->placeholder('example.com'),
                        TextInput::make('token')
                            ->required()
                            ->maxLength(64)
                            ->label(__('admin.settings.api_app_details.fields.token'))
                            ->suffixAction(
                                Action::make('generateToken')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function (Set $set) {
                                        $set('token', Str::random(64));
                                    })
                            )
                            ->default(fn () => Str::random(64)),
                        Toggle::make('is_active')
                            ->required()
                            ->default(true)
                            ->label(__('admin.settings.api_app_details.fields.is_active')),
                    ])
            ]);
    }
}
