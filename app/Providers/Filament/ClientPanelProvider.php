<?php
 
namespace App\Providers\Filament;
 
use App\Http\Middleware\Authenticate;
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
            ->path('cabinet')

            ->registration(\App\Filament\Client\Pages\Auth\Register::class)
            ->passwordReset()
            ->emailVerification()
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Профиль')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn () => \App\Filament\Client\Pages\EditProfile::getUrl()),
            ])
            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->brandName('Meanly Systems')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-[#f53003]">
                        <div class="w-3 h-3 bg-white rounded-sm"></div>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-white uppercase">Client Terminal</span>
                    </div>
                </div>
            '))
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
                \App\Http\Middleware\EnsureUserHasPasskey::class,
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
            );
 
        return FilamentPanelDomain::apply($panel, array_values(array_filter([config('app.domain')])));
    }
}
