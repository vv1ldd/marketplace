<?php

namespace App\Providers\Filament;

use App\Support\FilamentPanelDomain;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Navigation\NavigationGroup;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class TreasuryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('treasury')
            ->path(config('app.treasury_panel_hosts') ? '' : 'treasury')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
                'danger'  => Color::Rose,
                'warning' => Color::Amber, // Represents Gold
            ])
            ->brandName('Treasury Nexus')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-500 dark:bg-emerald-500/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M21 6.375c0 2.692-4.03 4.875-9 4.875S3 9.067 3 6.375 7.03 1.5 12 1.5s9 2.183 9 4.875Z" /><path d="M12 12.75c4.97 0 9-2.183 9-4.875V12c0 2.692-4.03 4.875-9 4.875S3 14.692 3 12V7.875c0 2.692 4.03 4.875 9 4.875Z" /><path d="M12 18.375c4.97 0 9-2.183 9-4.875V18c0 2.692-4.03 4.875-9 4.875S3 20.692 3 18v-4.5c0 2.692 4.03 4.875 9 4.875Z" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-emerald-600 dark:text-emerald-400 uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">The Treasury</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Treasury/Resources'), for: 'App\Filament\Treasury\Resources')
            ->discoverPages(in: app_path('Filament/Treasury/Pages'), for: 'App\Filament\Treasury\Pages')
            ->discoverWidgets(in: app_path('Filament/Treasury/Widgets'), for: 'App\Filament\Treasury\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Liquidity Grid')
                    ->icon('heroicon-o-map'),
                NavigationGroup::make()
                    ->label('Settlement Rails')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make()
                    ->label('FX Oracle')
                    ->icon('heroicon-o-globe-alt'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                // Future Treasury Overview Widget
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

            ->profile()
            ->maxContentWidth('full')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(false);

        return FilamentPanelDomain::apply($panel, config('app.treasury_panel_hosts', []));
    }
}
