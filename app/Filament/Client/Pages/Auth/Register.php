<?php

namespace App\Filament\Client\Pages\Auth;

use Filament\Auth\Pages\Register as BaseRegister;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use App\Models\User;

class Register extends BaseRegister
{
    protected string $view = 'filament.pages.auth.register';

    public string $step = 'email'; // 'email' or 'sent'
    public string $magicLink = '';
    public string $rawBlueprint = '';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('email')
                    ->label('Ваш Email 🛡️')
                    ->email()
                    ->required()
                    ->placeholder('mail@example.com')
                    ->autocomplete('email'),
            ])
            ->statePath('data');
    }

    public function register(): ?\Filament\Auth\Http\Responses\Contracts\RegistrationResponse
    {
        // 1. Get and validate form data
        $data = $this->form->getState();
        $email = $data['email'];

        // 2. Ensure email is strictly unique in our system
        if (User::where('email', $email)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.email' => 'Этот email уже зарегистрирован. Пожалуйста, войдите в систему.',
            ]);
        }

        // 3. Generate secure token
        $token = bin2hex(random_bytes(32));

        // 4. Assemble Sovereign Intent Blueprint
        $blueprint = [
            'schema' => 'meanly:l1:identity:activation:v1',
            'email' => $email,
            'timestamp' => now()->toIso8601String(),
            'salt' => bin2hex(random_bytes(16)),
            'authority' => 'meanly.systems',
            'is_b2b' => false,
        ];

        // 5. Save in Cache for 15 minutes
        \Illuminate\Support\Facades\Cache::put("intent:{$token}", $blueprint, now()->addMinutes(15));

        // 6. Generate magic link & raw blueprint JSON
        $this->magicLink = route('register.verify', ['token' => $token]);
        $this->rawBlueprint = json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // 7. Transition state
        $this->step = 'sent';

        return null;
    }
}
