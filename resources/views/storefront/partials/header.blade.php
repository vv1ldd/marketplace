@php
    $homeUrl = \Illuminate\Support\Facades\Route::has('home') ? route('home') : url('/');
    $howItWorksUrl = \Illuminate\Support\Facades\Route::has('storefront.ai-chat') ? route('storefront.ai-chat') : $homeUrl.'#infrastructure';
    $loginUrl = \Illuminate\Support\Facades\Route::has('login') ? route('login') : url('/login');
    $cabinetUrl = \Illuminate\Support\Facades\Route::has('filament.client.pages.dashboard') ? route('filament.client.pages.dashboard') : url('/cabinet');
    $logoutUrl = \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : url('/logout');
    $opsUrl = url('/ops');
    $partnerUrl = url('/partner');
    $businessUrl = \Illuminate\Support\Facades\Route::has('business.landing') ? route('business.landing') : url('/business');
@endphp

@once
    <style>
        .meanly-standard-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 72px;
            min-height: 72px;
            padding: 0 22px;
            background: var(--theme-nav-bg, var(--panel, #ffffff));
            color: var(--theme-text, var(--ink, #050505));
            border-bottom: 4px solid var(--theme-border, var(--line, #050505));
            box-shadow: 0 4px 0 var(--theme-border, var(--line, #050505));
            display: flex;
            align-items: center;
            backdrop-filter: var(--theme-backdrop-filter, none);
            -webkit-backdrop-filter: var(--theme-backdrop-filter, none);
        }

        .meanly-standard-header .nav-container {
            width: min(1180px, calc(100vw - 32px));
            margin: 0 auto;
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 18px;
        }

        .meanly-standard-header .logo {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: inherit;
            text-decoration: none;
            font-size: 18px;
            font-weight: 900;
            letter-spacing: -0.035em;
            white-space: nowrap;
        }

        .meanly-standard-header .logo-mark {
            width: 12px;
            height: 12px;
            flex: 0 0 auto;
            background: var(--brand, #7c3aed);
            border: 2px solid var(--theme-border, var(--line, #050505));
            box-shadow: 2px 2px 0 var(--theme-border, var(--line, #050505));
        }

        .meanly-standard-header .nav-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .meanly-standard-header .nav-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
        }

        .meanly-standard-header .nav-links a,
        .meanly-standard-header .btn-nav-login,
        .meanly-standard-header .btn-nav-cta {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: 2px solid transparent;
            border-radius: 6px;
            background: transparent;
            color: inherit;
            padding: 0 12px;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
        }

        .meanly-standard-header button.btn-nav-login,
        .meanly-standard-header button.btn-nav-cta {
            appearance: none;
            -webkit-appearance: none;
        }

        .meanly-standard-header .btn-nav-cta {
            border-color: var(--theme-border, var(--line, #050505));
            background: var(--brand, #7c3aed);
            color: #ffffff;
            box-shadow: 4px 4px 0 var(--theme-border, var(--line, #050505));
            font-weight: 950;
        }

        .meanly-standard-header .btn-nav-cta:hover {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 var(--theme-border, var(--line, #050505));
        }

        .meanly-standard-header .nav-links a:hover,
        .meanly-standard-header .nav-links a.active,
        .meanly-standard-header .btn-nav-login:hover {
            background: var(--brand-soft, #efe6ff);
            border-color: var(--theme-border, var(--line, #050505));
        }

        .meanly-standard-header .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .meanly-standard-header .meanly-mobile-menu-toggle {
            display: none !important;
            width: 42px;
            height: 42px;
            border: 2px solid var(--theme-border, #050505);
            border-radius: var(--theme-radius-md, 10px);
            background: var(--theme-control-bg, #ffffff);
            color: var(--theme-text, #050505);
            box-shadow: 3px 3px 0 var(--theme-border, #050505);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
        }

        .meanly-standard-header .meanly-mobile-menu-toggle span:not(.sr-only) {
            width: 18px;
            height: 2px;
            display: block;
            border-radius: 999px;
            background: currentColor;
            transition: transform 0.18s ease, opacity 0.18s ease;
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-button {
            --nav-icon-color: #5f6472;
            width: 42px !important;
            height: 42px !important;
            min-height: 42px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            color: var(--nav-icon-color) !important;
            box-shadow: none !important;
            transform: translate(0, 0) !important;
            transition: transform 0.14s ease, color 0.14s ease, background 0.14s ease, box-shadow 0.14s ease !important;
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-button:hover,
        html[data-theme] body nav.meanly-standard-header .nav-icon-button.active {
            color: var(--brand, #7c3aed) !important;
            transform: translateY(-2px) !important;
            background: transparent !important;
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-button:active {
            transform: translateY(1px) !important;
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-button svg {
            width: 24px !important;
            height: 24px !important;
            display: block !important;
            stroke: currentColor !important;
            stroke-width: 2.4 !important;
            filter: drop-shadow(1.4px 1.4px 0 rgba(0, 0, 0, 0.18)) !important;
        }

        html[data-theme] body nav.meanly-standard-header :is(a, button).nav-icon-button.nav-icon-primary {
            width: 42px !important;
            height: 42px !important;
            min-height: 42px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 3px solid var(--theme-border, var(--line, #050505)) !important;
            border-radius: var(--theme-radius-md, 0) !important;
            background: var(--theme-accent, #7c3aed) !important;
            background-color: var(--theme-accent, #7c3aed) !important;
            color: #ffffff !important;
            box-shadow: var(--theme-control-shadow, 4px 4px 0 #050505) !important;
            transform: translate(0, 0) !important;
        }

        html[data-theme] body nav.meanly-standard-header :is(a, button).nav-icon-button.nav-icon-primary:hover,
        html[data-theme] body nav.meanly-standard-header :is(a, button).nav-icon-button.nav-icon-primary.active {
            background: var(--theme-accent, #7c3aed) !important;
            background-color: var(--theme-accent, #7c3aed) !important;
            color: #ffffff !important;
            box-shadow: 2px 2px 0 var(--theme-border, var(--line, #050505)) !important;
            transform: translate(2px, 2px) !important;
        }

        html[data-theme] body nav.meanly-standard-header :is(a, button).nav-icon-button.nav-icon-primary:active {
            box-shadow: 0 0 0 var(--theme-border, var(--line, #050505)) !important;
            transform: translate(4px, 4px) !important;
        }

        html[data-theme] body nav.meanly-standard-header :is(a, button).nav-icon-button.nav-icon-primary svg {
            width: 23px !important;
            height: 23px !important;
            color: #ffffff !important;
            stroke: #ffffff !important;
            filter: none !important;
            stroke-width: 2.4 !important;
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-user {
            --nav-icon-color: var(--theme-muted, #5f6472);
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-admin {
            --nav-icon-color: var(--theme-text, #101010);
        }

        html[data-theme] body nav.meanly-standard-header .nav-icon-partner {
            --nav-icon-color: var(--brand, #7c3aed);
        }

        @media (max-width: 760px) {
            html[data-theme] body .meanly-standard-header {
                height: 64px !important;
                min-height: 64px !important;
                padding: 0 14px !important;
                align-items: center !important;
                overflow: visible !important;
            }

            html[data-theme] body .meanly-standard-header > .nav-container {
                position: relative !important;
                height: 100% !important;
                display: grid !important;
                grid-template-columns: 1fr auto !important;
                align-items: center !important;
                justify-items: stretch !important;
                gap: 12px !important;
            }

            html[data-theme] body .meanly-standard-header .meanly-mobile-menu-toggle {
                display: inline-flex !important;
                justify-self: end !important;
            }

            html[data-theme] body .meanly-standard-header .nav-links,
            html[data-theme] body .meanly-standard-header .nav-actions {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 1001 !important;
                display: none !important;
                width: 100% !important;
                background: var(--theme-nav-bg, #ffffff) !important;
                border: 2px solid var(--theme-border, #050505) !important;
                box-shadow: 6px 6px 0 var(--theme-border, #050505) !important;
                backdrop-filter: var(--theme-backdrop-filter, none) !important;
                -webkit-backdrop-filter: var(--theme-backdrop-filter, none) !important;
            }

            html[data-theme] body .meanly-standard-header .nav-links {
                top: calc(100% + 10px) !important;
                flex-direction: column !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                gap: 6px !important;
                padding: 12px !important;
                border-radius: 14px 14px 0 0 !important;
                overflow: visible !important;
            }

            html[data-theme] body .meanly-standard-header .nav-actions {
                top: calc(100% + 82px) !important;
                flex-direction: column !important;
                align-items: stretch !important;
                justify-content: flex-start !important;
                gap: 8px !important;
                padding: 12px !important;
                border-radius: 0 0 14px 14px !important;
                border-top: 0 !important;
            }

            html[data-theme] body .meanly-standard-header.is-open .nav-links,
            html[data-theme] body .meanly-standard-header.is-open .nav-actions {
                display: flex !important;
            }

            html[data-theme] body .meanly-standard-header :is(.nav-links a, .btn-nav-login, .btn-nav-cta) {
                width: 100% !important;
                min-height: 42px !important;
                justify-content: flex-start !important;
                padding: 0 12px !important;
                white-space: nowrap !important;
            }

            html[data-theme] body .meanly-standard-header .nav-icon-button {
                width: 100% !important;
            }

            html[data-theme] body .meanly-standard-header .nav-icon-button .sr-only {
                position: static !important;
                width: auto !important;
                height: auto !important;
                margin: 0 0 0 8px !important;
                clip: auto !important;
                overflow: visible !important;
            }

            html[data-theme] body .meanly-standard-header.is-open .meanly-mobile-menu-toggle span:nth-child(1) {
                transform: translateY(7px) rotate(45deg);
            }

            html[data-theme] body .meanly-standard-header.is-open .meanly-mobile-menu-toggle span:nth-child(2) {
                opacity: 0;
            }

            html[data-theme] body .meanly-standard-header.is-open .meanly-mobile-menu-toggle span:nth-child(3) {
                transform: translateY(-7px) rotate(-45deg);
            }

            html[data-theme] body .meanly-standard-header + main.shell {
                padding-top: 88px !important;
            }
        }
    </style>
    <script>
        (() => {
            const initMeanlyMenus = () => {
                document.querySelectorAll('.meanly-standard-header').forEach((header) => {
                    const button = header.querySelector('[data-meanly-mobile-menu-toggle]');

                    if (!button || button.dataset.meanlyMenuReady === '1') {
                        return;
                    }

                    button.dataset.meanlyMenuReady = '1';

                    const setOpen = (isOpen) => {
                        header.classList.toggle('is-open', isOpen);
                        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    };

                    button.addEventListener('click', () => {
                        setOpen(!header.classList.contains('is-open'));
                    });

                    header.querySelectorAll('.nav-links a, .nav-actions a, .nav-actions button').forEach((item) => {
                        item.addEventListener('click', () => setOpen(false));
                    });

                    window.addEventListener('resize', () => {
                        if (window.innerWidth > 760) {
                            setOpen(false);
                        }
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initMeanlyMenus, { once: true });
            } else {
                initMeanlyMenus();
            }
        })();
    </script>
@endonce

<nav class="meanly-standard-header" aria-label="Публичная навигация Meanly">
    <div class="nav-container">
        <a class="logo" href="{{ $homeUrl }}"><span class="logo-mark"></span> MEANLY</a>

        <button
            class="meanly-mobile-menu-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="meanlyMobileMenu"
            data-meanly-mobile-menu-toggle
        >
            <span></span>
            <span></span>
            <span></span>
            <span class="sr-only">Открыть меню</span>
        </button>

        <div class="nav-links" id="meanlyMobileMenu">
            <a href="{{ $howItWorksUrl }}">Как работает</a>
        </div>

        <div class="nav-actions">
            @auth
                @if(auth()->user()?->hasRole('super_admin'))
                    <a href="{{ $opsUrl }}" @class(['btn-nav-login', 'active' => request()->is('ops*')])>
                        Ops
                    </a>
                @elseif(auth()->user()?->hasRole('b2b_partner'))
                    <a href="{{ $partnerUrl }}" @class(['btn-nav-cta', 'active' => request()->is('partner*')])>
                        B2B Консоль
                    </a>
                @else
                    <a href="{{ $businessUrl }}" @class(['btn-nav-login', 'active' => request()->routeIs('business.*')])>
                        B2B
                    </a>
                @endif
                <a href="{{ $cabinetUrl }}" @class(['btn-nav-login', 'active' => request()->is('cabinet*')])>
                    Кабинет
                </a>
                <form id="storefrontHeaderLogoutForm" method="POST" action="{{ $logoutUrl }}" style="display:none;">@csrf</form>
                <button type="button" onclick="document.getElementById('storefrontHeaderLogoutForm').submit()" class="btn-nav-login">
                    Выйти
                </button>
            @else
                <a href="{{ $businessUrl }}" @class(['btn-nav-login', 'active' => request()->routeIs('business.*')])>
                    B2B
                </a>
                <a href="{{ $loginUrl }}" @class(['btn-nav-cta', 'active' => request()->is('login')])>
                    Войти
                </a>
            @endauth
        </div>
    </div>
</nav>
