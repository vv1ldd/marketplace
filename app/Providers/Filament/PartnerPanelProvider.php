<?php

namespace App\Providers\Filament;

use App\Models\LegalEntity;
use App\Models\Shop;
use App\Support\FilamentPanelDomain;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PartnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('partner')
            ->path(config('app.partner_panel_hosts') ? '' : 'partner')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->registration(\App\Filament\Partner\Pages\Auth\Register::class)
            ->authGuard('sellers')
            ->databaseNotifications()
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->brandName('Consortium Terminal')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-[#f53003]/10 text-[#f53003] dark:bg-[#f53003]/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM6.262 6.072a8.25 8.25 0 1 0 10.56 10.864.75.75 0 1 1 1.25.832 9.75 9.75 0 1 1-12.375-12.393.75.75 0 1 1 .565 1.392l-.003.005-.001.001a.748.748 0 0 1-.564-1.392-.75.75 0 0 1 .567-.309Zm6.238 1.428a.75.75 0 0 1 .75.75v.75a.75.75 0 0 1-1.5 0v-.75a.75.75 0 0 1 .75-.75ZM12 9.75a.75.75 0 0 0-.75.75v3c0 .414.336.75.75.75h3a.75.75 0 0 0 0-1.5h-2.25V10.5A.75.75 0 0 0 12 9.75Z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] dark:text-[#FF4433] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">Consortium</span>
                    </div>
                </div>
            '))
            ->tenant(LegalEntity::class, slugAttribute: 'id') // Using ID for simplicity in URLs
            ->discoverResources(in: app_path('Filament/Partner/Resources'), for: 'App\Filament\Partner\Resources')
            ->discoverPages(in: app_path('Filament/Partner/Pages'), for: 'App\Filament\Partner\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Partner/Widgets'), for: 'App\Filament\Partner\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                \App\Http\Middleware\SovereignContextMiddleware::class,
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
            ->tenantMiddleware([
                \Filament\Http\Middleware\AuthenticateSession::class,
            ], isPersistent: true);

        return FilamentPanelDomain::apply($panel, config('app.partner_panel_hosts', []));
    }
}
