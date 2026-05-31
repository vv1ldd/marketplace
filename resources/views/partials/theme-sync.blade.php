@php
    $request = request();
    $path = $request->getPathInfo();
    $defaultTheme = 'retro'; // Storefront public pages default theme (light neobrutalist)
    $forceDefaultTheme = true;

    if (
        str_starts_with($path, '/partner') || 
        str_starts_with($path, '/vault') ||
        str_starts_with($path, '/cabinet') || 
        str_starts_with($path, '/login') || 
        str_starts_with($path, '/register') || 
        $request->routeIs('partner.*') || 
        $request->routeIs('cabinet.*') ||
        $request->routeIs('login') ||
        $request->routeIs('register')
    ) {
        $defaultTheme = 'partner';
        $forceDefaultTheme = false;
    } elseif (str_starts_with($path, '/ops') || $request->routeIs('ops.*')) {
        $defaultTheme = 'carbon';
        $forceDefaultTheme = false;
    }
@endphp
<style>
    html[data-theme="consortium"] {
        --theme-accent: #f53003;
        --theme-bg: #030303;
        --theme-surface: #090909;
        --theme-surface-muted: rgba(255, 255, 255, 0.03);
        --theme-nav-bg: rgba(3, 3, 3, 0.84);
        --theme-text: #ffffff;
        --theme-muted: #8e8e93;
        --theme-border: rgba(255, 255, 255, 0.06);
        --theme-on-accent: #ffffff;
        --theme-accent-gradient: linear-gradient(135deg, #f53003 0%, #ff7b00 100%);
        --theme-control-bg: #101010;
        --theme-control-hover-bg: rgba(245, 48, 3, 0.1);
        --theme-menu-active-bg: rgba(245, 48, 3, 0.12);
        --theme-menu-active-border: rgba(245, 48, 3, 0.45);
        --theme-card-shadow: 0 22px 60px rgba(0, 0, 0, 0.55);
        --theme-control-shadow: 0 0 18px rgba(245, 48, 3, 0.18);
        --theme-radius-sm: 6px;
        --theme-radius-md: 10px;
        --theme-radius-lg: 14px;
        --theme-radius-pill: 999px;
        --theme-border-width: 1px;
        --theme-backdrop-filter: blur(22px);
        --theme-menu-transform: translateX(3px);
        --theme-ui-font: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
        --theme-letter-spacing: -0.01em;
        --theme-text-transform: none;
    }

    html[data-theme="partner"] {
        --theme-accent: #ff9f0a;
        --theme-bg: #060608;
        --theme-surface: rgba(14, 14, 18, 0.82);
        --theme-surface-muted: rgba(255, 255, 255, 0.035);
        --theme-nav-bg: rgba(6, 6, 8, 0.86);
        --theme-text: #ffffff;
        --theme-muted: #9a9ab0;
        --theme-border: rgba(255, 255, 255, 0.06);
        --theme-on-accent: #111111;
        --theme-accent-gradient: linear-gradient(135deg, #ff9f0a 0%, #ffd166 100%);
        --theme-control-bg: rgba(10, 13, 24, 0.6);
        --theme-control-hover-bg: rgba(255, 159, 10, 0.12);
        --theme-menu-active-bg: rgba(255, 159, 10, 0.14);
        --theme-menu-active-border: rgba(255, 159, 10, 0.42);
        --theme-card-shadow: 0 24px 70px rgba(0, 0, 0, 0.48);
        --theme-control-shadow: 0 0 22px rgba(255, 159, 10, 0.2);
        --theme-radius-sm: 10px;
        --theme-radius-md: 14px;
        --theme-radius-lg: 20px;
        --theme-radius-pill: 999px;
        --theme-border-width: 1px;
        --theme-backdrop-filter: blur(26px) saturate(130%);
        --theme-menu-transform: translateX(4px);
        --theme-ui-font: 'Outfit', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        --theme-letter-spacing: -0.02em;
        --theme-text-transform: none;
    }

    html[data-theme="retro"] {
        --theme-accent: #7c3aed;
        --theme-bg: #eef0fc;
        --theme-surface: #ffffff;
        --theme-surface-muted: rgba(124, 58, 237, 0.06);
        --theme-nav-bg: rgba(255, 255, 255, 0.96);
        --theme-text: #000000;
        --theme-muted: #374151;
        --theme-border: #000000;
        --theme-on-accent: #ffffff;
        --theme-accent-gradient: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
        --theme-control-bg: #ffffff;
        --theme-control-hover-bg: rgba(124, 58, 237, 0.08);
        --theme-menu-active-bg: #7c3aed;
        --theme-menu-active-border: #000000;
        --theme-card-shadow: 8px 8px 0 #000000;
        --theme-control-shadow: 4px 4px 0 #000000;
        --theme-radius-sm: 0;
        --theme-radius-md: 0;
        --theme-radius-lg: 0;
        --theme-radius-pill: 0;
        --theme-border-width: 2px;
        --theme-backdrop-filter: none;
        --theme-menu-transform: translate(-2px, -2px);
        --theme-ui-font: 'Outfit', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        --theme-letter-spacing: -0.02em;
        --theme-text-transform: none;
    }

    html[data-theme="nordic"] {
        --theme-accent: #1e3f20;
        --theme-bg: #faf7f2;
        --theme-surface: #ffffff;
        --theme-surface-muted: rgba(30, 63, 32, 0.05);
        --theme-nav-bg: rgba(250, 247, 242, 0.96);
        --theme-text: #2b2b2b;
        --theme-muted: #6e706a;
        --theme-border: #e6dfd5;
        --theme-on-accent: #faf7f2;
        --theme-accent-gradient: linear-gradient(135deg, #1e3f20 0%, #426b45 100%);
        --theme-control-bg: #f5f2eb;
        --theme-control-hover-bg: rgba(30, 63, 32, 0.07);
        --theme-menu-active-bg: rgba(30, 63, 32, 0.1);
        --theme-menu-active-border: rgba(30, 63, 32, 0.28);
        --theme-card-shadow: 0 20px 45px rgba(30, 63, 32, 0.08);
        --theme-control-shadow: 0 8px 24px rgba(30, 63, 32, 0.1);
        --theme-radius-sm: 12px;
        --theme-radius-md: 18px;
        --theme-radius-lg: 26px;
        --theme-radius-pill: 999px;
        --theme-border-width: 1px;
        --theme-backdrop-filter: blur(18px);
        --theme-menu-transform: translateY(-1px);
        --theme-ui-font: 'Outfit', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        --theme-letter-spacing: -0.015em;
        --theme-text-transform: none;
    }

    html[data-theme="synthwave"] {
        --theme-accent: #ff007f;
        --theme-bg: #120e2e;
        --theme-surface: #1c1543;
        --theme-surface-muted: rgba(255, 0, 127, 0.08);
        --theme-nav-bg: rgba(18, 14, 46, 0.9);
        --theme-text: #ffffff;
        --theme-muted: #b9b2ff;
        --theme-border: rgba(255, 0, 127, 0.22);
        --theme-on-accent: #ffffff;
        --theme-accent-gradient: linear-gradient(135deg, #ff007f 0%, #00f0ff 100%);
        --theme-control-bg: rgba(18, 14, 46, 0.75);
        --theme-control-hover-bg: rgba(0, 240, 255, 0.1);
        --theme-menu-active-bg: rgba(255, 0, 127, 0.16);
        --theme-menu-active-border: rgba(0, 240, 255, 0.38);
        --theme-card-shadow: 0 24px 80px rgba(255, 0, 127, 0.18), 0 0 45px rgba(0, 240, 255, 0.1);
        --theme-control-shadow: 0 0 24px rgba(255, 0, 127, 0.28);
        --theme-radius-sm: 10px;
        --theme-radius-md: 16px;
        --theme-radius-lg: 22px;
        --theme-radius-pill: 999px;
        --theme-border-width: 1px;
        --theme-backdrop-filter: blur(24px) saturate(150%);
        --theme-menu-transform: translateX(3px);
        --theme-ui-font: 'Outfit', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        --theme-letter-spacing: 0;
        --theme-text-transform: none;
    }

    html[data-theme="carbon"] {
        --theme-accent: #facc15;
        --theme-bg: #070708;
        --theme-surface: #101012;
        --theme-surface-muted: rgba(250, 204, 21, 0.06);
        --theme-nav-bg: rgba(7, 7, 8, 0.94);
        --theme-text: #ffffff;
        --theme-muted: #a3a3aa;
        --theme-border: #222226;
        --theme-on-accent: #000000;
        --theme-accent-gradient: linear-gradient(135deg, #facc15 0%, #f59e0b 100%);
        --theme-control-bg: #151518;
        --theme-control-hover-bg: rgba(250, 204, 21, 0.08);
        --theme-menu-active-bg: rgba(250, 204, 21, 0.12);
        --theme-menu-active-border: rgba(250, 204, 21, 0.4);
        --theme-card-shadow: 0 18px 56px rgba(0, 0, 0, 0.65);
        --theme-control-shadow: 0 0 0 1px rgba(250, 204, 21, 0.18), 0 12px 30px rgba(0, 0, 0, 0.45);
        --theme-radius-sm: 4px;
        --theme-radius-md: 6px;
        --theme-radius-lg: 8px;
        --theme-radius-pill: 4px;
        --theme-border-width: 1px;
        --theme-backdrop-filter: blur(18px);
        --theme-menu-transform: translateX(2px);
        --theme-ui-font: 'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
        --theme-letter-spacing: -0.02em;
        --theme-text-transform: uppercase;
    }

    html[data-theme] {
        --brand-primary: var(--theme-accent);
        --brand-bg: var(--theme-bg);
        --brand-card: var(--theme-surface);
        --brand-text: var(--theme-text);
        --brand-subtext: var(--theme-muted);
        --brand-border: var(--theme-border);
        --primary: var(--theme-accent);
        --bg-primary: var(--theme-bg);
        --bg-card: var(--theme-surface);
        --text-primary: var(--theme-text);
        --text-muted: var(--theme-muted);
        --border-color: var(--theme-border);
        --bg-sidebar: var(--theme-nav-bg);
        --border-card: var(--theme-border);
        --shadow-neo: var(--theme-card-shadow);
        --shadow-neo-inset: inset 0 0 0 var(--theme-border-width) var(--theme-border);
        --bg: var(--theme-bg);
        --panel: var(--theme-surface);
        --ink: var(--theme-text);
        --muted: var(--theme-muted);
        --brand: var(--theme-accent);
        --brand-soft: var(--theme-surface-muted);
        --line: var(--theme-border);
        --shadow: var(--theme-card-shadow);
        --radius: var(--theme-radius-lg);
        scroll-padding-top: 88px;
        overscroll-behavior-y: none;
    }

    html[data-theme] body {
        background-color: var(--theme-bg) !important;
        color: var(--theme-text) !important;
        overscroll-behavior-y: none;
    }

    html[data-theme] body :is(nav, .nav, .top-bar, .sidebar, .dropdown-menu, #console-dropdown, .skin-switcher-pill, .filter-group, .tabs, .tab-list, .auth-card, .card-neo, .modal-card, .neo-table-container, .table-wrapper) {
        background: var(--theme-nav-bg) !important;
        border: var(--theme-border-width) solid var(--theme-border) !important;
        border-radius: var(--theme-radius-lg) !important;
        box-shadow: var(--theme-card-shadow) !important;
        color: var(--theme-text) !important;
        backdrop-filter: var(--theme-backdrop-filter) !important;
        -webkit-backdrop-filter: var(--theme-backdrop-filter) !important;
    }

    html[data-theme] body :is(nav, .nav, .top-bar) {
        border-left: 0 !important;
        border-right: 0 !important;
        border-top: 0 !important;
        border-radius: 0 !important;
    }

    html[data-theme] body :is(.sidebar) {
        border-top: 0 !important;
        border-left: 0 !important;
        border-bottom: 0 !important;
        border-radius: 0 !important;
    }

    html[data-theme] body :is(.nav-links a, .btn-nav-login, .menu-item, .dropdown-item, .skin-btn, .filter-btn, .tab-btn, [role="tab"], .sidebar-logo) {
        border: var(--theme-border-width) solid transparent !important;
        border-radius: var(--theme-radius-md) !important;
        color: var(--theme-muted) !important;
        font-family: var(--theme-ui-font) !important;
        letter-spacing: var(--theme-letter-spacing) !important;
        text-transform: var(--theme-text-transform) !important;
        transition: transform 0.2s ease, background-color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease, color 0.2s ease !important;
    }

    html[data-theme] body :is(.nav-links a:hover, .btn-nav-login:hover, .menu-item:hover, .dropdown-item:hover, .filter-btn:hover, .tab-btn:hover, [role="tab"]:hover, .sidebar-logo:hover) {
        color: var(--theme-text) !important;
        background: var(--theme-control-hover-bg) !important;
        border-color: var(--theme-border) !important;
        transform: var(--theme-menu-transform) !important;
    }

    html[data-theme] body :is(.menu-item.active, .dropdown-item.active, .filter-btn.active, .tab-btn.active, [role="tab"][aria-selected="true"], [aria-current="page"]) {
        background: var(--theme-menu-active-bg) !important;
        border-color: var(--theme-menu-active-border) !important;
        box-shadow: var(--theme-control-shadow) !important;
        color: var(--theme-text) !important;
    }

    html[data-theme] body :is(.menu-item.active svg, .menu-item.active i, .filter-btn.active i, .tab-btn.active i) {
        color: var(--theme-accent) !important;
        filter: drop-shadow(0 0 8px color-mix(in srgb, var(--theme-accent) 55%, transparent)) !important;
    }

    html[data-theme] body :is(.btn-nav-cta, .btn-primary, .btn-primary-neo, .skin-btn.active),
    html[data-theme="consortium"] body #skin-btn-consortium,
    html[data-theme="partner"] body #skin-btn-partner,
    html[data-theme="retro"] body #skin-btn-retro,
    html[data-theme="nordic"] body #skin-btn-nordic,
    html[data-theme="synthwave"] body #skin-btn-synthwave,
    html[data-theme="carbon"] body #skin-btn-carbon {
        background: var(--theme-accent-gradient) !important;
        border-color: var(--theme-accent) !important;
        border-radius: var(--theme-radius-md) !important;
        box-shadow: var(--theme-control-shadow) !important;
        color: var(--theme-on-accent) !important;
    }

    html[data-theme] body :is(.btn-nav-cta:hover, .btn-primary:hover, .btn-primary-neo:hover, .skin-btn.active:hover) {
        filter: brightness(1.05) saturate(1.08);
        transform: var(--theme-menu-transform) !important;
    }

    html[data-theme] body :is(.skin-switcher-pill, .filter-group) {
        border-radius: var(--theme-radius-pill) !important;
        padding: 4px !important;
        gap: 4px !important;
        box-shadow: var(--shadow-neo-inset) !important;
    }

    html[data-theme] body :is(.skin-btn, .filter-btn) {
        border-radius: var(--theme-radius-pill) !important;
        background: transparent !important;
    }

    html[data-theme] body :is(.card-neo, .auth-card, .modal-card) {
        background: var(--theme-surface) !important;
        border-color: var(--theme-border) !important;
    }

    html[data-theme] body :is(.sidebar-section-title, .section-label, .eyebrow) {
        color: var(--theme-muted) !important;
        font-family: var(--theme-ui-font) !important;
        letter-spacing: 0.11em !important;
        text-transform: uppercase !important;
    }

    html[data-theme] body :is(input, select, textarea, #storeSearch) {
        background-color: var(--theme-control-bg) !important;
        border-color: var(--theme-border) !important;
        border-radius: var(--theme-radius-md) !important;
        color: var(--theme-text) !important;
    }

    html[data-theme] body :is(input, textarea)::placeholder {
        color: var(--theme-muted) !important;
        opacity: 0.85;
    }

    html[data-theme] body :is(.neo-table th, .data-table th, .matrix-table th, .specs-table th) {
        background: var(--theme-control-bg) !important;
        border-color: var(--theme-border) !important;
        color: var(--theme-muted) !important;
        font-family: var(--theme-ui-font) !important;
        text-transform: uppercase !important;
        letter-spacing: 0.08em !important;
    }

    html[data-theme] body :is(.neo-table td, .data-table td, .matrix-table td, .specs-table td) {
        border-color: var(--theme-border) !important;
        color: var(--theme-text) !important;
    }

    html[data-theme="retro"] body :is(nav, .nav, .top-bar, .sidebar, .dropdown-menu, #console-dropdown, .skin-switcher-pill, .filter-group, .tabs, .tab-list, .auth-card, .card-neo, .modal-card, .neo-table-container, .table-wrapper) {
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }

    html[data-theme="retro"] body :is(.menu-item.active, .dropdown-item.active, .filter-btn.active, .tab-btn.active, [role="tab"][aria-selected="true"], [aria-current="page"]) {
        color: var(--theme-on-accent) !important;
    }

    html[data-theme="nordic"] body :is(nav, .nav, .top-bar, .sidebar, .dropdown-menu, #console-dropdown, .skin-switcher-pill, .filter-group, .tabs, .tab-list, .auth-card, .card-neo, .modal-card, .neo-table-container, .table-wrapper) {
        box-shadow: var(--theme-card-shadow) !important;
    }

    /* Meanly component contract: one standard for menus/cards/buttons across all pages. */
    html[data-theme] body {
        --theme-component-bg: var(--theme-surface);
        --theme-component-border: var(--theme-border);
        --theme-component-radius: var(--theme-radius-lg);
        --theme-component-shadow: var(--theme-card-shadow);
        --theme-button-bg: var(--theme-accent-gradient);
        --theme-button-text: var(--theme-on-accent);
        --theme-secondary-bg: var(--theme-control-bg);
        --theme-secondary-text: var(--theme-text);
        --theme-active-bg: var(--theme-menu-active-bg);
        --theme-active-text: var(--theme-text);
        --theme-active-border: var(--theme-menu-active-border);
    }

    html[data-theme="retro"] body {
        --theme-component-bg: #ffffff;
        --theme-component-shadow: 7px 7px 0 #000000;
        --theme-secondary-bg: #ffffff;
        --theme-secondary-text: #000000;
        --theme-active-text: #ffffff;
    }

    html[data-theme="nordic"] body {
        --theme-component-bg: #ffffff;
        --theme-secondary-bg: #f5f2eb;
        --theme-active-text: #1e3f20;
    }

    html[data-theme] body :is(
        nav,
        .nav,
        .top-bar,
        .sidebar,
        .dropdown-menu,
        #console-dropdown,
        .skin-switcher-pill,
        .filter-group,
        .tabs,
        .tab-list,
        .auth-card,
        #sovereign-auth-root .auth-card,
        .dev-mail-simulator,
        #sovereign-auth-root .dev-mail-simulator,
        .contract-box,
        #sovereign-auth-root .contract-box,
        details,
        #sovereign-auth-root details,
        .blueprint-console,
        #sovereign-auth-root .blueprint-console,
        .card-neo,
        .modal-card,
        .neo-table-container,
        .table-wrapper,
        .product-card,
        .feature-card,
        .stat-card,
        .platform-card
    ) {
        background: var(--theme-component-bg) !important;
        border: var(--theme-border-width) solid var(--theme-component-border) !important;
        border-radius: var(--theme-component-radius) !important;
        box-shadow: var(--theme-component-shadow) !important;
        color: var(--theme-text) !important;
    }

    html[data-theme] body :is(nav, .nav, .top-bar) {
        border-radius: 0 !important;
        box-shadow: 0 4px 0 color-mix(in srgb, var(--theme-border) 75%, transparent) !important;
    }

    html[data-theme] body :is(.sidebar) {
        border-radius: 0 !important;
        box-shadow: 4px 0 0 color-mix(in srgb, var(--theme-border) 75%, transparent) !important;
    }

    html[data-theme] body :is(
        .btn-nav-cta,
        .btn-primary,
        .btn-primary-neo,
        .btn-submit,
        #sovereign-auth-root .btn-submit,
        .fi-btn,
        #sovereign-auth-root .fi-btn,
        .sovereign-btn-trigger,
        #sovereign-auth-root .sovereign-btn-trigger,
        button[type="submit"],
        #storefront-btn-submit,
        .passkeys-submit
    ) {
        background: var(--theme-button-bg) !important;
        border: var(--theme-border-width) solid var(--theme-accent) !important;
        border-radius: var(--theme-radius-md) !important;
        box-shadow: var(--theme-control-shadow) !important;
        color: var(--theme-button-text) !important;
        font-family: var(--theme-ui-font) !important;
        letter-spacing: var(--theme-letter-spacing) !important;
    }

    html[data-theme] body :is(
        .btn-nav-login,
        .btn-secondary,
        .btn-neo,
        .btn-action,
        .sovereign-secondary-btn,
        #sovereign-auth-root .sovereign-secondary-btn,
        .icon-btn,
        .filter-btn,
        .skin-btn,
        .menu-item,
        .dropdown-item,
        .tab-btn,
        [role="tab"]
    ) {
        background: transparent !important;
        border: var(--theme-border-width) solid transparent !important;
        border-radius: var(--theme-radius-md) !important;
        box-shadow: none !important;
        color: var(--theme-muted) !important;
        font-family: var(--theme-ui-font) !important;
    }

    html[data-theme] body :is(
        .sovereign-secondary-btn,
        #sovereign-auth-root .sovereign-secondary-btn,
        .btn-secondary,
        .btn-neo,
        .btn-action
    ) {
        background: var(--theme-secondary-bg) !important;
        border-color: var(--theme-border) !important;
        color: var(--theme-secondary-text) !important;
    }

    html[data-theme] body :is(
        .menu-item.active,
        .dropdown-item.active,
        .filter-btn.active,
        .skin-btn.active,
        .skin-btn.active-skin,
        .tab-btn.active,
        [role="tab"][aria-selected="true"],
        [aria-current="page"]
    ),
    html[data-theme="consortium"] body #skin-btn-consortium,
    html[data-theme="partner"] body #skin-btn-partner,
    html[data-theme="retro"] body #skin-btn-retro,
    html[data-theme="nordic"] body #skin-btn-nordic,
    html[data-theme="synthwave"] body #skin-btn-synthwave,
    html[data-theme="carbon"] body #skin-btn-carbon {
        background: var(--theme-active-bg) !important;
        border-color: var(--theme-active-border) !important;
        box-shadow: var(--theme-control-shadow) !important;
        color: var(--theme-active-text) !important;
    }

    html[data-theme] body :is(.skin-btn.active, .skin-btn.active-skin),
    html[data-theme="consortium"] body #skin-btn-consortium,
    html[data-theme="partner"] body #skin-btn-partner,
    html[data-theme="retro"] body #skin-btn-retro,
    html[data-theme="nordic"] body #skin-btn-nordic,
    html[data-theme="synthwave"] body #skin-btn-synthwave,
    html[data-theme="carbon"] body #skin-btn-carbon {
        background: var(--theme-button-bg) !important;
        color: var(--theme-button-text) !important;
    }

    html[data-theme] body :is(.filter-group, .skin-switcher-pill) :is(.filter-btn, .skin-btn) {
        border-radius: var(--theme-radius-pill) !important;
        box-shadow: none !important;
    }

    html[data-theme="retro"] body :is(
        .auth-card,
        #sovereign-auth-root .auth-card,
        .dev-mail-simulator,
        #sovereign-auth-root .dev-mail-simulator,
        .card-neo,
        .modal-card,
        .product-card,
        .feature-card,
        .stat-card
    ) {
        border-width: 2px !important;
    }

    /* Standard app header: identical geometry and actions on landing/business/cabinet/product pages. */
    html[data-theme] body .meanly-standard-header {
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        z-index: 1000 !important;
        width: 100% !important;
        height: 72px !important;
        min-height: 72px !important;
        padding: 0 24px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        background: var(--theme-nav-bg) !important;
        border: 0 !important;
        border-bottom: var(--theme-border-width) solid var(--theme-border) !important;
        border-radius: 0 !important;
        box-shadow: 0 4px 0 color-mix(in srgb, var(--theme-border) 88%, transparent) !important;
        backdrop-filter: var(--theme-backdrop-filter) !important;
        -webkit-backdrop-filter: var(--theme-backdrop-filter) !important;
    }

    html[data-theme] body .meanly-standard-header > .nav-container {
        width: 100% !important;
        max-width: 1200px !important;
        height: 100% !important;
        margin: 0 auto !important;
        display: grid !important;
        grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr) !important;
        align-items: center !important;
        gap: 24px !important;
    }

    html[data-theme] body .meanly-standard-header .logo {
        justify-self: start !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 10px !important;
        color: var(--theme-text) !important;
        font-family: var(--theme-ui-font) !important;
        font-size: 18px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        letter-spacing: -0.04em !important;
        text-decoration: none !important;
        white-space: nowrap !important;
    }

    html[data-theme] body .meanly-standard-header .logo-mark {
        width: 12px !important;
        height: 12px !important;
        flex: 0 0 12px !important;
        background: var(--theme-accent-gradient) !important;
        border: var(--theme-border-width) solid var(--theme-border) !important;
        border-radius: var(--theme-radius-sm) !important;
        box-shadow: var(--theme-control-shadow) !important;
    }

    html[data-theme] body .meanly-standard-header .nav-links {
        justify-self: center !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 28px !important;
        color: var(--theme-muted) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
    }

    html[data-theme] body .meanly-standard-header .nav-actions {
        justify-self: end !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 12px !important;
        min-width: 0 !important;
    }

    html[data-theme] body .meanly-standard-header :is(.nav-links a, .btn-nav-login) {
        min-height: 38px !important;
        padding: 0 12px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: var(--theme-radius-md) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        color: var(--theme-muted) !important;
        text-decoration: none !important;
    }

    html[data-theme] body .meanly-standard-header .nav-links :is(a, a.nav-link-b2b, a[style]) {
        color: var(--theme-muted) !important;
        background: transparent !important;
        border-color: transparent !important;
    }

    html[data-theme] body .meanly-standard-header .nav-links :is(a:hover, a.nav-link-b2b:hover, a[style]:hover) {
        color: var(--theme-text) !important;
        background: var(--theme-control-hover-bg) !important;
        border-color: var(--theme-border) !important;
    }

    html[data-theme] body .meanly-standard-header .nav-links a.active {
        color: var(--theme-text) !important;
        background: var(--theme-control-hover-bg) !important;
        border-color: var(--theme-border) !important;
    }

    html[data-theme] body .meanly-standard-header .btn-nav-cta {
        min-height: 42px !important;
        padding: 0 20px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: var(--theme-radius-md) !important;
        font-size: 13px !important;
        font-weight: 900 !important;
        line-height: 1 !important;
        text-decoration: none !important;
    }

    html[data-theme] body .meanly-standard-header .nav-user-chip {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        min-height: 38px !important;
        padding: 0 10px !important;
        border: var(--theme-border-width) solid transparent !important;
        border-radius: var(--theme-radius-md) !important;
        color: var(--theme-muted) !important;
        font-size: 13px !important;
        font-weight: 800 !important;
        white-space: nowrap !important;
    }

    @media (max-width: 900px) {
        html[data-theme] body .meanly-standard-header {
            padding: 0 14px !important;
        }

        html[data-theme] body .meanly-standard-header > .nav-container {
            grid-template-columns: auto minmax(0, 1fr) auto !important;
            gap: 10px !important;
        }

        html[data-theme] body .meanly-standard-header .nav-links {
            justify-content: flex-start !important;
            gap: 8px !important;
            min-width: 0 !important;
            overflow-x: auto !important;
            scrollbar-width: none !important;
        }

        html[data-theme] body .meanly-standard-header .nav-links::-webkit-scrollbar {
            display: none !important;
        }

        html[data-theme] body .meanly-standard-header .nav-actions {
            gap: 8px !important;
        }

        html[data-theme] body .meanly-standard-header :is(.nav-links a, .btn-nav-login) {
            padding: 0 10px !important;
            white-space: nowrap !important;
        }

        html[data-theme] body .meanly-standard-header .btn-nav-cta {
            padding: 0 14px !important;
            white-space: nowrap !important;
        }
    }

    @media (max-width: 640px) {
        html[data-theme] body .meanly-standard-header {
            height: auto !important;
            min-height: 72px !important;
            padding: 10px 14px !important;
        }

        html[data-theme] body .meanly-standard-header > .nav-container {
            height: auto !important;
            grid-template-columns: 1fr !important;
            justify-items: start !important;
            gap: 8px !important;
        }

        html[data-theme] body .meanly-standard-header .nav-links,
        html[data-theme] body .meanly-standard-header .nav-actions {
            width: 100% !important;
            justify-self: start !important;
            justify-content: flex-start !important;
            flex-wrap: wrap !important;
            overflow: visible !important;
        }

        html[data-theme] body .meanly-standard-header + main.shell {
            padding-top: 176px !important;
        }
    }

    /* Premium Neobrutalist Footer */
    html[data-theme] body .marketplace-footer {
        width: min(1180px, calc(100vw - 32px)) !important;
        margin: 40px auto !important;
        border: 4px solid var(--line) !important;
        border-radius: var(--radius) !important;
        background: var(--panel) !important;
        box-shadow: 6px 6px 0 var(--line) !important;
        overflow: hidden !important;
        text-align: left !important;
        box-sizing: border-box !important;
    }
    html[data-theme] body .marketplace-footer * {
        box-sizing: border-box !important;
    }
    html[data-theme] body .footer-grid {
        display: grid !important;
        grid-template-columns: minmax(260px, 1.2fr) repeat(3, minmax(0, 1fr)) !important;
        gap: 24px !important;
        padding: 26px !important;
    }
    html[data-theme] body .footer-logo {
        display: inline-flex !important;
        align-items: center !important;
        gap: 9px !important;
        font-weight: 950 !important;
        letter-spacing: -.05em !important;
        margin-bottom: 14px !important;
        text-decoration: none !important;
        color: var(--ink) !important;
    }
    html[data-theme] body .footer-brand-block p,
    html[data-theme] body .footer-proof-block p {
        margin: 0 !important;
        color: var(--muted) !important;
        font-weight: 800 !important;
        line-height: 1.45 !important;
        font-size: 14px !important;
    }
    html[data-theme] body .footer-links-block {
        display: flex !important;
        flex-direction: column !important;
        gap: 10px !important;
    }
    html[data-theme] body .footer-title {
        font-family: var(--theme-ui-font), "JetBrains Mono", ui-monospace, monospace !important;
        font-size: 11px !important;
        font-weight: 900 !important;
        letter-spacing: .08em !important;
        text-transform: uppercase !important;
        color: var(--brand) !important;
    }
    html[data-theme] body .footer-links-block a {
        font-weight: 900 !important;
        color: var(--ink) !important;
        text-decoration: none !important;
        font-size: 14px !important;
        transition: color 0.15s ease !important;
    }
    html[data-theme] body .footer-links-block a:hover {
        color: var(--brand) !important;
    }
    html[data-theme] body .footer-bottom {
        display: flex !important;
        justify-content: space-between !important;
        gap: 16px !important;
        padding: 14px 26px !important;
        border-top: 3px solid var(--line) !important;
        background: var(--brand-soft) !important;
        color: var(--muted) !important;
        font-size: 12px !important;
        font-weight: 900 !important;
        text-transform: uppercase !important;
        letter-spacing: .04em !important;
    }
    @media (max-width: 820px) {
        html[data-theme] body .footer-grid {
            grid-template-columns: 1fr !important;
        }
        html[data-theme] body .footer-bottom {
            flex-direction: column !important;
            gap: 8px !important;
        }
    }
</style>

<script>
    (function() {
        function getCookie(name) {
            var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));
            return m ? decodeURIComponent(m[1]) : null;
        }

        var THEMES = @json(array_keys($supportedThemes ?? config('app.theme_labels', [])));
        var serverTheme = @json($currentTheme ?? null);
        var cookieDomain = @json(config('session.domain') ?? null);
        var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
        var defaultTheme = @json($defaultTheme);
        var forceDefaultTheme = @json($forceDefaultTheme);

        function normalize(theme) {
            theme = String(theme || '').toLowerCase();
            return THEMES.indexOf(theme) !== -1 ? theme : defaultTheme;
        }

        function persist(theme) {
            document.cookie = 'theme=' + theme + '; path=/; max-age=31536000; SameSite=Lax' + domainSuffix;
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }

        function syncHoliday() {
            var holiday = getCookie('holiday');

            [document.documentElement, document.body].forEach(function(el) {
                if (!el) return;
                if (holiday) {
                    el.setAttribute('data-holiday', holiday);
                } else {
                    el.removeAttribute('data-holiday');
                }
            });

            document.querySelectorAll('[data-theme-root], #sovereign-auth-root').forEach(function(el) {
                if (holiday) {
                    el.setAttribute('data-holiday', holiday);
                } else {
                    el.removeAttribute('data-holiday');
                }
            });
        }

        function apply(theme) {
            theme = normalize(theme);
            document.documentElement.setAttribute('data-theme', theme);
            if (document.body) document.body.setAttribute('data-theme', theme);

            document.querySelectorAll('[data-theme-root], #sovereign-auth-root').forEach(function(el) {
                el.setAttribute('data-theme', theme);
            });

            document.querySelectorAll('.skin-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.id === 'skin-btn-' + theme);
            });

            persist(theme);
            syncHoliday();
            return theme;
        }

        window.MeanlyTheme = {
            apply: apply,
            current: function() {
                return normalize(document.documentElement.getAttribute('data-theme') || getCookie('theme') || serverTheme);
            },
            supported: THEMES
        };

        var localTheme = null;
        try { localTheme = localStorage.getItem('theme'); } catch (e) {}
        var raw = forceDefaultTheme ? defaultTheme : (localTheme || getCookie('theme') || serverTheme || defaultTheme);
        var savedTheme = apply(raw);

        document.addEventListener('DOMContentLoaded', function() {
            apply(savedTheme);

            if (document.body) {
                new MutationObserver(function(records) {
                    records.forEach(function(record) {
                        if (record.attributeName === 'data-theme') {
                            var theme = normalize(document.body.getAttribute('data-theme'));
                            if (document.documentElement.getAttribute('data-theme') !== theme) {
                                apply(theme);
                            }
                        }
                    });
                }).observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });
            }
        });
    })();
</script>
