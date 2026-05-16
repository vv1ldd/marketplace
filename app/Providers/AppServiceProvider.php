<?php

namespace App\Providers;

use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Suppress annoying notices globally
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent::class,
            \App\Listeners\StoreEntrySignature::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            \App\Listeners\LogSovereignLogoutIntent::class
        );

        Auth::provider('vault', function ($app, array $config) {
            return new \App\Auth\VaultUserProvider($app['hash'], $config['model']);
        });

        Export::polymorphicUserRelationship(true);
        Import::polymorphicUserRelationship(true);
        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            return str_replace('Models', 'Policies', $modelClass).'Policy';
        });

        // Grant super_admin sovereign access to all permissions globally
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        LogViewer::auth(function ($request) {
            return $request->user() && $request->user()->hasRole('super_admin');
        });

        // Настройка переключателя языков
        if (class_exists(\BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch::class)) {
            \BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch::configureUsing(function (\BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch $switch) {
                $switch->locales(['ru', 'en', 'es']);
            });
        }
    }
}
