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

class KernelPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('kernel')
            ->path(config('app.kernel_panel_hosts') ? '' : 'kernel')
            ->login()
            ->colors([
                'primary' => Color::Slate, // Slate Steel
                'danger'  => Color::Red,
                'warning' => Color::Orange,
            ])
            ->brandName('System Kernel')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-slate-500/10 text-slate-500 dark:bg-slate-500/20">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12.75 2.75a.75.75 0 0 0-1.5 0V4.5h1.5V2.75ZM12.75 19.5v1.75a.75.75 0 0 1-1.5 0V19.5h1.5ZM4.5 11.25V9.75H2.75a.75.75 0 0 0 0 1.5H4.5ZM21.25 11.25a.75.75 0 0 0 0-1.5H19.5v1.5h1.75ZM6 6a2.25 2.25 0 0 1 2.25-2.25h7.5A2.25 2.25 0 0 1 18 6v12a2.25 2.25 0 0 1-2.25 2.25h-7.5A2.25 2.25 0 0 1 6 18V6Zm3 3a1.5 1.5 0 0 0-1.5 1.5v3A1.5 1.5 0 0 0 9 15h6a1.5 1.5 0 0 0 1.5-1.5v-3A1.5 1.5 0 0 0 15 9H9Z" /></svg>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-slate-500 dark:text-slate-400 uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-gray-900 dark:text-white uppercase">System Kernel</span>
                    </div>
                </div>
            '))
            ->databaseNotifications()
            ->discoverResources(in: app_path('Filament/Kernel/Resources'), for: 'App\Filament\Kernel\Resources')
            ->discoverPages(in: app_path('Filament/Kernel/Pages'), for: 'App\Filament\Kernel\Pages')
            ->discoverWidgets(in: app_path('Filament/Kernel/Widgets'), for: 'App\Filament\Kernel\Widgets')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('System Toplogy')
                    ->icon('heroicon-o-server-stack'),
                NavigationGroup::make()
                    ->label('Liquidity Gateways')
                    ->icon('heroicon-o-cpu-chip'),
                NavigationGroup::make()
                    ->label('Internal Registers')
                    ->icon('heroicon-o-folder-open'),
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
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Администрирование')
                    ->navigationLabel(__('admin.users.roles'))
                    ->modelLabel(__('admin.users.role'))
                    ->pluralModelLabel(__('admin.users.roles')),
            ])
            ->profile()
            ->maxContentWidth('full')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(false);

        return FilamentPanelDomain::apply($panel, config('app.kernel_panel_hosts', []));
    }
}
