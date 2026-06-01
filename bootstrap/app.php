<?php

use App\Http\Middleware\AllowIframeForRoute;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['theme', 'holiday']);

        $middleware->web(append: [
            \App\Http\Middleware\ResolveMarketContext::class,
            \App\Http\Middleware\ResolvePricingContext::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SetTheme::class,
            \App\Http\Middleware\EnsureUserHasPasskey::class,
            \App\Http\Middleware\HolidayContextMiddleware::class,
        ]);
        $middleware->append(\App\Http\Middleware\TrackMeanlyAnalyticsRequests::class);
        $middleware->append(AllowIframeForRoute::class);
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'api.redeem.auth' => \App\Http\Middleware\CheckApiApplicationToken::class,
            'api.ledger.auth' => \App\Http\Middleware\CheckApiApplicationToken::class,
            'plane.guard'     => \App\Http\Middleware\SovereignPlaneGuard::class,
            'partner.intent'  => \App\Http\Middleware\RecordPartnerActionIntent::class,
            // Seller terminal authentication: X-Terminal-Id + X-Terminal-Pin headers
            'seller.terminal' => \App\Http\Middleware\AuthenticateSellerTerminal::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
