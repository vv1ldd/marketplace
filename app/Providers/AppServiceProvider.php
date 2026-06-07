<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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

        $this->app->bind(
            \Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class,
            \App\Actions\Auth\CustomFindPasskeyToAuthenticateAction::class
        );
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

        Livewire::component('passkeys', \App\Livewire\PasskeysComponent::class);

        Auth::provider('vault', function ($app, array $config) {
            return new \App\Auth\VaultUserProvider($app['hash'], $config['model']);
        });

        Gate::guessPolicyNamesUsing(function (string $modelClass) {
            return str_replace('Models', 'Policies', $modelClass).'Policy';
        });

        // Grant sovereign validators access to all protected operations.
        Gate::before(function ($user, $ability) {
            return $user->hasOpsSovereignAccess() ? true : null;
        });

        LogViewer::auth(function ($request) {
            return $request->user() && $request->user()->hasOpsSovereignAccess();
        });

    }
}
