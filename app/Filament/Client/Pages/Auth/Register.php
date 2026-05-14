<?php

namespace App\Filament\Client\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class Register extends BaseRegister
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Forms\Components\TextInput::make('first_name')
                    ->label('Никнейм')
                    ->prefix('@')
                    ->placeholder('имя_в_сети')
                    ->required()
                    ->maxLength(255),
            ])
            ->statePath('data');
    }

    protected function handleRegistration(array $data): Model
    {
        // Генерируем технический email и случайный пароль
        $data['email'] = Str::slug($data['first_name']) . '@' . Str::random(8) . '.local';
        $data['password'] = Str::random(32);

        $user = static::getUserModel()::create($data);

        $user->assignRole('customer');

        return $user;
    }

    protected function getRedirectUrl(): string
    {
        // Перенаправляем в профиль для регистрации Passkey
        return '/profile';
    }
}
