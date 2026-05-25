<?php
 
namespace App\Providers\Filament;
 
use App\Models\LegalEntity;
use App\Models\Shop;
use App\Support\FilamentPanelDomain;
use App\Http\Middleware\Authenticate;
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
            ->path(config('app.partner_panel_hosts') ? '' : 'partner-old')

            ->registration(\App\Filament\Partner\Pages\Auth\Register::class)
            ->authGuard('sellers')
            ->databaseNotifications()
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Профиль')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => \App\Filament\Partner\Pages\EditProfile::getUrl()),
            ])
            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->brandName('Consortium Terminal')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-[#f53003]">
                        <div class="w-3 h-3 bg-white rounded-sm"></div>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-white uppercase">Consortium</span>
                    </div>
                </div>
            '))
            ->tenant(LegalEntity::class, slugAttribute: 'id')
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
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                \App\Http\Middleware\SyncSovereignGuards::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\SovereignContextMiddleware::class,
                \App\Http\Middleware\EnsureUserHasPasskey::class,
            ])
            ->tenantMiddleware([
                \Filament\Http\Middleware\AuthenticateSession::class,
            ], isPersistent: true)
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
            );
 
        return FilamentPanelDomain::apply($panel, config('app.partner_panel_hosts', []));
    }
}
