<?php

namespace App\Providers\Filament;

use App\Support\FilamentPanelDomain;
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

class SupportPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('support')
            ->path(config('app.support_panel_hosts') ? '' : 'support')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::hex('#f53003'), // Sky Cyan for communication/support
                'danger'  => Color::Rose,
                'success' => Color::Emerald,
            ])
            ->brandName('Support Hub')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-[#f53003]/10 text-[#f53003] dark:bg-[#f53003]/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M18 22a.75.75 0 0 0 .75-.75V17.5c0-.966-.784-1.75-1.75-1.75H17a8.988 8.988 0 0 0 4.537-6.315 8.961 8.961 0 0 0-17.074 0A8.988 8.988 0 0 0 7 15.75h.25c-.966 0-1.75.784-1.75 1.75v3.75c0 .414.336.75.75.75h4.25a.75.75 0 0 0 .75-.75v-4.25a.75.75 0 0 0-.75-.75H8.5c.476-3.369 3.38-6 6.9-6 1.296 0 2.5.378 3.517 1.033a.75.75 0 1 0 .82-1.254A10.462 10.462 0 0 0 12 1.5C6.201 1.5 1.5 6.201 1.5 12c0 .212.006.422.019.631a.75.75 0 0 0 1.494-.095C3.004 12.356 3 12.18 3 12c0-4.97 4.03-9 9-9s9 4.03 9 9c0 .18-.004.356-.013.536a.75.75 0 1 0 1.494.095c.013-.21.019-.42.019-.631 0-1.14-.183-2.237-.52-3.262a10.471 10.471 0 0 0-3.24-4.977.75.75 0 0 0-1 1.118A8.961 8.961 0 0 1 21 12c0 .615-.062 1.216-.18 1.8-.824-.626-1.844-.987-2.945-.987-.966 0-1.75.784-1.75 1.75V21.25a.75.75 0 0 0 .75.75h4.25ZM17 20.5v-3.25c0-.138.112-.25.25-.25H17.5c.138 0 .25.112.25.25V20.5h-1.25ZM7 20.5v-3.25c0-.138.112-.25.25-.25H7.5c.138 0 .25.112.25.25V20.5H7Z" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] dark:text-[#FF4433] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">Support Hub</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Support/Resources'), for: 'App\Filament\Support\Resources')
            ->discoverPages(in: app_path('Filament/Support/Pages'), for: 'App\Filament\Support\Pages')
            ->discoverWidgets(in: app_path('Filament/Support/Widgets'), for: 'App\Filament\Support\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Поддержка')
                    ->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make()
                    ->label('Клиенты')
                    ->icon('heroicon-o-user-group'),
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

        return FilamentPanelDomain::apply($panel, config('app.support_panel_hosts', []));
    }
}
