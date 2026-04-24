<?php

namespace App\Filament\Resources\ApiApplicationResource\Schemas;
 
use Filament\Actions\Action;
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
                Section::make()
                    ->schema([
                        Select::make('shop_id')
                            ->label('Магазин')
                            ->relationship('shop', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Название магазина'),
                        TextInput::make('first_name')
                            ->maxLength(255)
                            ->label('Имя отв. лица'),
                        TextInput::make('last_name')
                            ->maxLength(255)
                            ->label('Фамилия отв. лица'),
                        TextInput::make('phone')
                            ->maxLength(255)
                            ->label('Телефон отв. лица (для Marketplace)'),
                        TextInput::make('domain')
                            ->maxLength(255)
                            ->label('Домен (optional)')
                            ->placeholder('example.com'),
                        TextInput::make('token')
                            ->required()
                            ->maxLength(64)
                            ->label('API Токен')
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
                            ->label('Активен'),
                    ])
            ]);
    }
}
