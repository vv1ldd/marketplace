<?php

namespace App\Filament\Resources\ApiApplicationResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
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
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Название'),
                        TextInput::make('domain')
                            ->maxLength(255)
                            ->label('Домен (optional)')
                            ->placeholder('example.com'),
                        TextInput::make('token')
                            ->required()
                            ->maxLength(64)
                            ->label('API Токен')
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('generateToken')
                                    ->icon('heroicon-m-arrow-path')
                                    ->action(function ($set) {
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
