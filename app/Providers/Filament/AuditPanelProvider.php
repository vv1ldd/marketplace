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

class AuditPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('tribunal')
            ->path(config('app.audit_panel_hosts') ? '' : 'tribunal')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::hex('#f53003'),
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
            ])
            ->brandName('Integrity Tribunal')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-[#f53003]/10 text-[#f53003] dark:bg-[#f53003]/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.378 1.602a.75.75 0 0 0-.756 0L3 6.632l9 5.25 9-5.25-8.622-5.03ZM21.75 7.93l-9 5.25v9l8.628-5.032a.75.75 0 0 0 .372-.648V7.93ZM11.25 22.18v-9l-9-5.25v8.59a.75.75 0 0 0 .372.648l8.628 5.033Z" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] dark:text-[#FF4433] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">Integrity Tribunal</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Audit/Resources'), for: 'App\Filament\Audit\Resources')
            ->discoverPages(in: app_path('Filament/Audit/Pages'), for: 'App\Filament\Audit\Pages')
            ->discoverWidgets(in: app_path('Filament/Audit/Widgets'), for: 'App\Filament\Audit\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Sovereign Сеть')
                    ->icon('heroicon-o-globe-alt'),
                NavigationGroup::make()
                    ->label('Аудит и Безопасность')
                    ->icon('heroicon-o-shield-check'),
                NavigationGroup::make()
                    ->label('Администрирование')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->sidebarCollapsibleOnDesktop()
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

        return FilamentPanelDomain::apply($panel, config('app.audit_panel_hosts', []));
    }
}
