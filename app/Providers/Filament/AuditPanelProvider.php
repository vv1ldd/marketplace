<?php
 
namespace App\Providers\Filament;
 
use App\Support\FilamentPanelDomain;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use App\Http\Middleware\Authenticate;
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

            ->colors([
                'primary' => Color::hex('#f53003'),
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
            ])
            ->brandName('Integrity Tribunal')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-[#f53003]">
                        <div class="w-3 h-3 bg-white rounded-sm"></div>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-white uppercase">Integrity Tribunal</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Audit/Resources'), for: 'App\Filament\Audit\Resources')
            ->discoverPages(in: app_path('Filament/Audit/Pages'), for: 'App\Filament\Audit\Pages')
            ->discoverWidgets(in: app_path('Filament/Audit/Widgets'), for: 'App\Filament\Audit\Widgets')
            ->navigationGroups([
                NavigationGroup::make()->label('Sovereign Сеть')->icon('heroicon-o-globe-alt'),
                NavigationGroup::make()->label('Аудит и Безопасность')->icon('heroicon-o-shield-check'),
                NavigationGroup::make()->label('Администрирование')->icon('heroicon-o-cog-6-tooth'),
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
            ->font('Instrument Sans')
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('
                    <link rel="stylesheet" href="/css/filament-theme.css?v=' . filemtime(public_path('css/filament-theme.css')) . '">
                    <script>
                        (function() {
                            const savedTheme = localStorage.getItem("theme") || "consortium";
                            document.documentElement.setAttribute("data-theme", savedTheme);
                            document.addEventListener("DOMContentLoaded", () => {
                                document.body.setAttribute("data-theme", savedTheme);
                            });
                        })();
                    </script>
                ')
            )
            ->profile(isSimple: false)
            ->maxContentWidth('full')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(false);
 
        return FilamentPanelDomain::apply($panel, config('app.audit_panel_hosts', []));
    }
}
