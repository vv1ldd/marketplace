<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * Restore legacy fields
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->autocomplete('username'),
            \Filament\Forms\Components\TextInput::make('password')
                ->label('Пароль')
                ->password()
                ->required()
                ->autocomplete('current-password'),
        ]);
    }

    /**
     * Show submit button for legacy login
     */
    protected function hasFullWidthFormActions(): bool
    {
        return true;
    }

    public function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
        ]; 
    }
}
