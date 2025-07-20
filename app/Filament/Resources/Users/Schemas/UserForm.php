<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $is_update = (bool)$schema->getRecord();
        $is_super_admin = auth()->user()->hasRole('super_admin');

        return $schema
            ->components([
                Section::make()->schema([
                    Grid::make()->schema([
                        TextInput::make('name')
                            ->label('Имя')
                            ->disabled()
                            ->required(),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->label('Email')
                            ->unique(ignoreRecord: $is_update),
                        TextInput::make('password')
                            ->password()
                            ->label('Пароль')
                            ->required()
                            ->confirmed()
                            ->revealable()
                            ->hidden($is_update),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->label('Подтверждение пароля')
                            ->revealable()
                            ->required()
                            ->hidden($is_update),
                        Select::make('roles')
                            ->searchable()
                            ->preload()
                            ->relationship('roles', 'name', fn($query) => $is_super_admin ? $query : $query->where('name', '<>', 'super_admin'))
                            ->label('Роль')
                    ])
                ])->columnSpanFull()
            ]);
    }
}
