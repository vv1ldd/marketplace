<?php
 
namespace App\Providers\Filament;
 
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use App\Support\FilamentPanelDomain;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
 
class ClientPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('client')
            ->path('/')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->registration(\App\Filament\Client\Pages\Auth\Register::class)
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->discoverResources(in: app_path('Filament/Client/Resources'), for: 'App\Filament\Client\Resources')
            ->discoverPages(in: app_path('Filament/Client/Pages'), for: 'App\Filament\Client\Pages')
            ->pages([
                \App\Filament\Client\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Client/Widgets'), for: 'App\Filament\Client\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->plugins([
                \MarcelWeidum\Passkeys\PasskeysPlugin::make(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->font('Instrument Sans')
            ->renderHook(
                'panels::head.done',
                fn () => new \Illuminate\Support\HtmlString('
                    <style>
                        /* 🌑 Cursor-style Deep Dark UI */
                        :root {
                            --brand-bg: #050505;
                            --brand-card: #0a0a0a;
                            --brand-border: #161616;
                        }
 
                        .fi-layout { background-color: var(--brand-bg) !important; }
                        
                        .fi-sidebar {
                            background-color: var(--brand-card) !important;
                            border-right: 1px solid var(--brand-border) !important;
                        }
 
                        /* 💊 Refined Sidebar Items (Cursor style) */
                        .fi-sidebar-item-button {
                            border-radius: 8px !important;
                            margin: 0.2rem 0.6rem !important;
                            transition: all 0.2s ease !important;
                        }
 
                        .fi-sidebar-item-button-active {
                            background-color: rgba(255, 255, 255, 0.05) !important;
                            color: #ffffff !important;
                        }
 
                        .fi-sidebar-item-button:hover:not(.fi-sidebar-item-button-active) {
                            background-color: rgba(255, 255, 255, 0.03) !important;
                        }
 
                        /* 🧩 Clean Minimal Cards */
                        .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-card-ctn, .fi-wi-widget {
                            background-color: var(--brand-card) !important;
                            border: 1px solid var(--brand-border) !important;
                            box-shadow: none !important;
                            border-radius: 12px !important;
                        }
 
                        .fi-ta-header-ctn { border-bottom: 1px solid var(--brand-border) !important; }
 
                        /* High Contrast Text */
                        .fi-header-heading { letter-spacing: -0.02em; font-weight: 700; }
                    </style>
                ')
            );
 
        return FilamentPanelDomain::apply($panel, array_values(array_filter([config('app.domain')])));
    }
}
