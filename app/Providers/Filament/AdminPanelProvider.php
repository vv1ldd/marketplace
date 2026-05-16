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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('ops')
            ->path(config('app.admin_panel_hosts') ? '' : 'ops')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->brandName('Operations Command')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-[#f53003]/10 text-[#f53003] dark:bg-[#f53003]/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" d="M11.828 2.25c-.916 0-1.699.663-1.85 1.567l-.091.549a.798.798 0 0 1-.517.608 7.47 7.47 0 0 0-.438.21 7.574 7.574 0 0 0-.438.21.798.798 0 0 1-.76.006l-.507-.293a1.875 1.875 0 0 0-2.56.935l-.56 1.536a1.875 1.875 0 0 0 .935 2.56l.507.293c.272.157.44.448.44.76 0 .28-.052.56-.154.816a.798.798 0 0 1-.608.517l-.549.091a1.875 1.875 0 0 0-1.567 1.85v1.12c0 .916.663 1.699 1.567 1.85l.549.091c.28.047.517.237.608.517.102.256.154.536.154.816 0 .312-.168.603-.44.76l-.507.293a1.875 1.875 0 0 0-.935 2.56l.56 1.536a1.875 1.875 0 0 0 2.56.935l.507-.293a.798.798 0 0 1 .76-.006c.145.083.291.153.438.21.147.057.293.127.438.21a.798.798 0 0 1 .517.608l.091.549c.15.904.934 1.567 1.85 1.567h1.12c.916 0 1.699-.663 1.85-1.567l.091-.549a.798.798 0 0 1 .517-.608c.145-.083.291-.153.438-.21.147-.057.293-.127.438-.21a.798.798 0 0 1 .76-.006l.507.293a1.875 1.875 0 0 0 2.56-.935l.56-1.536a1.875 1.875 0 0 0-.935-2.56l-.507-.293a.798.798 0 0 1-.44-.76 7.99 7.99 0 0 0 .154-.816.798.798 0 0 1 .608-.517l.549-.091a1.875 1.875 0 0 0 1.567-1.85v-1.12c0-.916-.663-1.699-1.567-1.85l-.549-.091a.798.798 0 0 1-.608-.517 7.99 7.99 0 0 0-.154-.816.798.798 0 0 1 .44-.76l.507-.293a1.875 1.875 0 0 0 .935-2.56l-.56-1.536a1.875 1.875 0 0 0-2.56-.935l-.507.293a.798.798 0 0 1-.76.006 7.574 7.574 0 0 0-.438-.21 7.47 7.47 0 0 0-.438-.21.798.798 0 0 1-.517-.608l-.091-.549A1.875 1.875 0 0 0 13.312 2.25h-1.484ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] dark:text-[#FF4433] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">Operations</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Мастер-ключ')
                    ->icon('heroicon-o-key'),
                NavigationGroup::make()
                    ->label('Магазины и B2B')
                    ->icon('heroicon-o-building-storefront'),
                NavigationGroup::make()
                    ->label('Каталог и Контент')
                    ->icon('heroicon-o-square-3-stack-3d'),
                NavigationGroup::make()
                    ->label('Система')
                    ->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make()
                    ->label('Поддержка')
                    ->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make()
                    ->label('Администрирование')
                    ->icon('heroicon-o-cog-6-tooth'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                \App\Filament\Widgets\SovereignChatWidget::class,
                //                AccountWidget::class,
                //                FilamentInfoWidget::class,
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
            ->plugins([
                \MarcelWeidum\Passkeys\PasskeysPlugin::make(),
            ])
            ->profile()
            ->maxContentWidth('full')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(false);

        return FilamentPanelDomain::apply($panel, config('app.admin_panel_hosts', []));
    }
}
