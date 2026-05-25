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

            ->colors([
                'primary' => Color::hex('#f53003'),
            ])
            ->brandName('Operations Command')
            ->brandLogo(fn () => new \Illuminate\Support\HtmlString('
                <div class="flex items-center gap-3">
                    <div class="p-1.5 rounded-lg bg-[#f53003]">
                        <div class="w-3 h-3 bg-white rounded-sm"></div>
                    </div>
                    <div class="flex flex-col text-left leading-tight">
                        <span class="text-[10px] font-bold tracking-[0.2em] text-[#f53003] uppercase">Meanly Systems</span>
                        <span class="text-lg font-black tracking-wide text-white uppercase">Operations</span>
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
                NavigationGroup::make()->label('Мастер-ключ')->icon('heroicon-o-key'),
                NavigationGroup::make()->label('Магазины и B2B')->icon('heroicon-o-building-storefront'),
                NavigationGroup::make()->label('Каталог и Контент')->icon('heroicon-o-square-3-stack-3d'),
                NavigationGroup::make()->label('Система')->icon('heroicon-o-presentation-chart-line'),
                NavigationGroup::make()->label('Поддержка')->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make()->label('Администрирование')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->widgets([
                \App\Filament\Widgets\SovereignChatWidget::class,
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
            ->font('Instrument Sans')
            ->renderHook(
                'panels::head.end',
                fn () => new \Illuminate\Support\HtmlString('
                    <link rel="stylesheet" href="/css/filament-theme.css?v=' . filemtime(public_path('css/filament-theme.css')) . '">
                    <script>
                        (function() {
                            const savedTheme = localStorage.getItem("theme") || "consortium";
                            document.documentElement.setAttribute("data-theme", savedTheme);
                            document.addEventListener("DOMContentLoaded", function() {
                                document.body.setAttribute("data-theme", savedTheme);
                            });
                        })();
                    </script>
                ')
            )
            ->renderHook(
                'panels::user-menu.before',
                fn () => new \Illuminate\Support\HtmlString('
                    <!-- 🎨 Premium Admin 3-Skin Switcher Pill -->
                    <div class="skin-switcher-pill mr-4 hidden items-center gap-1.5 bg-black/20 dark:bg-black/40 border border-white/5 dark:border-white/10 rounded-full p-1 text-[10px] font-extrabold uppercase">
                        <button onclick="setTheme(\'partner\')" class="skin-btn px-3 py-1 rounded-full cursor-pointer transition-all duration-200 hover:text-white" id="skin-btn-partner" style="letter-spacing: 0.5px;">Partner 🌟</button>
                        <button onclick="setTheme(\'consortium\')" class="skin-btn px-3 py-1 rounded-full cursor-pointer transition-all duration-200 hover:text-white" id="skin-btn-consortium" style="letter-spacing: 0.5px;">Flagship 🚩</button>
                        <button onclick="setTheme(\'retro\')" class="skin-btn px-3 py-1 rounded-full cursor-pointer transition-all duration-200 hover:text-white" id="skin-btn-retro" style="letter-spacing: 0.5px;">Retro ⚡</button>
                    </div>
                    <script>
                        function setTheme(theme) {
                            localStorage.setItem("theme", theme);
                            document.cookie = "theme=" + theme + "; path=/; max-age=31536000; SameSite=Lax";
                            document.documentElement.setAttribute("data-theme", theme);
                            document.body.setAttribute("data-theme", theme);
                            updateActiveThemeButton();
                        }
                        function checkSettingsPage() {
                            const pill = document.querySelector(".skin-switcher-pill");
                            if (pill) {
                                if (window.location.pathname.includes("/profile")) {
                                    pill.style.display = "flex";
                                } else {
                                    pill.style.display = "none";
                                }
                            }
                        }
                        function updateActiveThemeButton() {
                            const currentTheme = localStorage.getItem("theme") || "consortium";
                            document.querySelectorAll(".skin-btn").forEach(btn => {
                                btn.classList.remove("active-skin", "bg-[#f53003]", "text-white", "bg-[#ff9f0a]", "text-black", "bg-[#7c3aed]", "text-white");
                                btn.style.background = "transparent";
                                btn.style.color = "";
                            });
                            
                            const activeBtn = document.getElementById("skin-btn-" + currentTheme);
                            if (activeBtn) {
                                activeBtn.classList.add("active-skin");
                                if (currentTheme === "partner") {
                                    activeBtn.style.background = "#ff9f0a";
                                    activeBtn.style.color = "#000000";
                                } else if (currentTheme === "retro") {
                                    activeBtn.style.background = "#7c3aed";
                                    activeBtn.style.color = "#ffffff";
                                } else {
                                    activeBtn.style.background = "#f53003";
                                    activeBtn.style.color = "#ffffff";
                                }
                            }
                        }
                        document.addEventListener("DOMContentLoaded", () => {
                            updateActiveThemeButton();
                            checkSettingsPage();
                        });
                        window.addEventListener("load", checkSettingsPage);
                        setTimeout(checkSettingsPage, 100);
                        setTimeout(updateActiveThemeButton, 500); 
                    </script>
                ')
            )
            ->plugins([
                \MarcelWeidum\Passkeys\PasskeysPlugin::make(),
            ])
            ->profile(isSimple: false)
            ->maxContentWidth('full')
            ->authMiddleware([
                Authenticate::class,
            ])
            ->spa(false);
 
        return FilamentPanelDomain::apply($panel, config('app.admin_panel_hosts', []));
    }
}
