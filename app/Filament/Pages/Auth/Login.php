<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Illuminate\Validation\ValidationException;

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

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public function getFormActions(): array
    {
        return [];
    }

    public function authenticate(): ?LoginResponse
    {
        throw ValidationException::withMessages([
            'data' => 'Вход по паролю отключен. Используйте Passkey или одноразовую ссылку миграции.',
        ]);
    }

    public function register(): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () {
            // Generate a premium cybernetic nickname
            $prefixes = ['nomad', 'agent', 'citizen', 'pioneer', 'sovereign', 'beacon', 'vector', 'prime', 'architect', 'quantum'];
            $nickname = $prefixes[array_rand($prefixes)] . '_' . rand(1000, 9999);
            
            // Ensure unique nickname
            while (\App\Models\User::where('first_name', $nickname)->exists()) {
                $nickname = $prefixes[array_rand($prefixes)] . '_' . rand(1000, 9999);
            }

            $email = $nickname . '@meanly.local';

            $user = \App\Models\User::create([
                'first_name' => $nickname,
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'password_login_enabled' => false,
            ]);
 
            $user->assignRole('customer');
 
            // Store registration context for L1 anchoring (Step 2)
            session(['partner_registration' => [
                'email' => $user->email,
                'name' => $user->first_name,
                'is_b2b' => false,
            ]]);

            \Illuminate\Support\Facades\Auth::login($user);
            
            $this->redirect('/partner/register/enroll');
        });
    }
}
