<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Вход в Meanly</title>

    <!-- 🧠 SimpleWebAuthn Script Integration -->
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
    <script>
        window.startAuthentication = SimpleWebAuthnBrowser.startAuthentication;
        window.startRegistration = SimpleWebAuthnBrowser.startRegistration;
        window.browserSupportsWebAuthn = SimpleWebAuthnBrowser.browserSupportsWebAuthn;
    </script>

    <!-- ⚡ Alpine.js Core -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- 🎨 Modern Typography -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap">

    <style>
        :root {
            --brand-primary: #f53003;
            --brand-primary-glow: rgba(245, 48, 3, 0.45);
            --brand-bg: #050505;
            --brand-card: #0a0a0a;
            --brand-text: #ffffff;
            --brand-subtext: #888888;
            --brand-border: #1a1a1a;
            --cursor-btn-bg: #111111;
            --cursor-btn-hover: #1a1a1a;
        }

        /* --- 🌟 Theme 1: Partner (Modern Glassmorphic Gold/Amber) --- */
        .sovereign-auth-wrapper[data-theme="partner"] {
            --brand-primary: #ff9f0a;
            --brand-primary-glow: rgba(255, 159, 10, 0.45);
            --brand-bg: #060608;
            --brand-card: rgba(14, 14, 18, 0.65);
            --brand-text: #ffffff;
            --brand-subtext: #9a9ab0;
            --brand-border: rgba(255, 255, 255, 0.04);
            --cursor-btn-bg: rgba(10, 13, 24, 0.55);
            --cursor-btn-hover: rgba(255, 159, 10, 0.1);
            background-color: #060608 !important;
        }

        /* --- 🚩 Theme 2: Consortium Flagship (DEFAULT) --- */
        .sovereign-auth-wrapper[data-theme="consortium"] {
            --brand-primary: #f53003;
            --brand-primary-glow: rgba(245, 48, 3, 0.45);
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            --cursor-btn-bg: #111111;
            --cursor-btn-hover: #1a1a1a;
            background-color: #030303 !important;
        }

        /* --- ⚡ Theme 3: Consortium Retro (Light Neo-Brutalism - Stark & Bold) --- */
        .sovereign-auth-wrapper[data-theme="retro"] {
            --brand-primary: #7c3aed;
            --brand-primary-glow: rgba(124, 58, 237, 0.45);
            --brand-bg: #f3f4f6;
            --brand-card: #ffffff;
            --brand-text: #000000;
            --brand-subtext: #4b5563;
            --brand-border: #000000;
            --cursor-btn-bg: #ffffff;
            --cursor-btn-hover: rgba(124, 58, 237, 0.1);
            background-color: #f3f4f6 !important;
        }

        body, html {
            margin: 0;
            padding: 0;
            width: 100vw;
            min-height: 100vh;
            background-color: #030303;
            overflow-x: hidden;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
        }

        .sovereign-auth-wrapper {
            position: relative;
            width: 100vw;
            min-height: 100vh;
            background-color: var(--brand-bg);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            box-sizing: border-box;
            transition: background-color 0.3s ease;
        }

        /* Ambient Glows */
        .ambient-glows {
            position: absolute;
            top: 0; left: 0; right: 0; height: 100vh;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-1 {
            position: absolute; top: -10%; left: 20%; width: 60vw; height: 60vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.04) 0%, rgba(0,0,0,0) 70%);
            filter: blur(80px);
            transition: background 0.3s ease;
        }
        .glow-2 {
            position: absolute; top: 30%; right: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.03) 0%, rgba(0,0,0,0) 75%);
            filter: blur(100px);
            transition: background 0.3s ease;
        }

        .sovereign-auth-wrapper[data-theme="partner"] .glow-1 {
            background: radial-gradient(circle, rgba(255, 159, 10, 0.09) 0%, rgba(0,0,0,0) 70%) !important;
        }
        .sovereign-auth-wrapper[data-theme="partner"] .glow-2 {
            background: radial-gradient(circle, rgba(139, 92, 246, 0.07) 0%, rgba(0,0,0,0) 70%) !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .ambient-glows {
            display: none !important;
        }

        .auth-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            padding: 4rem 3rem;
            border-radius: 12px;
            text-align: center;
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 10;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-sizing: border-box;
        }

        .logo-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--brand-text);
            letter-spacing: -0.02em;
            margin-bottom: 4rem;
        }

        .logo-mark {
            width: 12px;
            height: 12px;
            background: var(--brand-primary);
            border-radius: 3px;
            box-shadow: 0 0 15px rgba(245, 48, 3, 0.5);
            transition: all 0.3s ease;
        }

        .auth-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--brand-text);
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }

        .auth-subtitle {
            font-size: 15px;
            color: var(--brand-subtext);
            line-height: 1.5;
            margin-bottom: 2.5rem;
        }

        /* 🕹️ Sovereign Solid Primary Button - Theme Inherited */
        .sovereign-btn-trigger,
        .auth-interaction div[onclick] > div {
            width: 100% !important;
            height: 48px !important;
            background-color: var(--brand-primary) !important;
            color: #ffffff !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            border: 1px solid var(--brand-primary) !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
            text-transform: none !important;
            text-decoration: none !important;
            margin-top: 0.5rem;
            user-select: none;
            box-sizing: border-box;
            box-shadow: 0 4px 15px var(--brand-primary-glow) !important;
        }

        .sovereign-btn-trigger:hover,
        .auth-interaction div[onclick] > div:hover {
            background-color: var(--brand-primary) !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 0 20px var(--brand-primary-glow) !important;
            filter: brightness(1.1) !important;
            transform: translateY(-1px) !important;
        }

        /* 🛡️ Safety: Never show scripts or hidden forms */
        .auth-interaction script, .auth-interaction form {
            display: none !important;
        }

        .auth-interaction {
            width: 100%;
            margin-top: 1rem;
        }

        /* Target the specific passkey trigger inside the component */
        .auth-interaction div[onclick] {
            all: unset;
            display: block;
            width: 100%;
        }

        .footer-brand {
            margin-top: 1rem;
            font-size: 11px;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            text-align: center;
            z-index: 10;
        }

        .footer-links {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            font-size: 12px;
            color: #777777;
        }

        [x-cloak] {
            display: none !important;
        }

        /* 🎨 Premium Theme Switcher Nav Pill */
        .skin-switcher-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--brand-border);
            border-radius: 100px;
            padding: 4px;
            gap: 4px;
            box-shadow: inset 1px 1px 4px rgba(0,0,0,0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-bottom: 2rem;
            z-index: 10;
        }

        .skin-btn {
            background: transparent;
            border: none;
            color: var(--brand-subtext);
            font-size: 0.65rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            width: auto !important;
            height: auto !important;
        }

        .skin-btn:hover {
            color: var(--brand-text);
            background: rgba(255,255,255,0.02);
        }

        .sovereign-auth-wrapper[data-theme="partner"] #skin-btn-partner {
            background: var(--brand-primary) !important;
            color: #000000 !important;
            box-shadow: 0 2px 10px rgba(255, 159, 10, 0.3) !important;
            font-weight: 900;
        }
        .sovereign-auth-wrapper[data-theme="consortium"] #skin-btn-consortium {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(245, 48, 3, 0.4) !important;
            font-weight: 900;
        }
        .sovereign-auth-wrapper[data-theme="retro"] #skin-btn-retro {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 900;
            border: 2px solid #000000 !important;
        }

        /* --- Theme Specific Overrides for Login --- */
        
        /* Partner (Glassmorphism & Gold) */
        .sovereign-auth-wrapper[data-theme="partner"] .auth-card {
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }
        .sovereign-auth-wrapper[data-theme="partner"] .logo-mark {
            background: #ff9f0a !important;
            box-shadow: 0 0 15px rgba(255, 159, 10, 0.5) !important;
        }
        .sovereign-auth-wrapper[data-theme="partner"] .auth-interaction a {
            color: #ff9f0a !important;
        }
        .sovereign-auth-wrapper[data-theme="partner"] .sovereign-btn-trigger,
        .sovereign-auth-wrapper[data-theme="partner"] .auth-interaction div[onclick] > div {
            color: #000000 !important;
        }

        /* Retro (Neobrutalism purple/light) */
        .sovereign-auth-wrapper[data-theme="retro"] .auth-card {
            border: 3px solid #000000 !important;
            box-shadow: 8px 8px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .logo-mark {
            background: var(--brand-primary) !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: none !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .logo-header {
            color: #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .auth-title {
            color: #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .auth-subtitle {
            color: #4b5563 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .sovereign-btn-trigger,
        .sovereign-auth-wrapper[data-theme="retro"] .auth-interaction div[onclick] > div {
            background-color: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .sovereign-btn-trigger:hover,
        .sovereign-auth-wrapper[data-theme="retro"] .auth-interaction div[onclick] > div:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
            background-color: var(--brand-primary) !important;
            border-color: #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .footer-links span,
        .sovereign-auth-wrapper[data-theme="retro"] .footer-brand {
            color: #4b5563 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .auth-interaction a {
            color: var(--brand-primary) !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .skin-switcher-pill {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: none !important;
            border-radius: 0px !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .skin-btn {
            border-radius: 0px !important;
            color: #000000 !important;
        }

        /* 🕹️ Premium Secondary Buttons */
        .sovereign-secondary-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 42px;
            padding: 0 1.25rem;
            background-color: var(--cursor-btn-bg) !important;
            color: var(--brand-primary) !important;
            border: 1px solid var(--brand-border) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            box-sizing: border-box;
        }
        .sovereign-secondary-btn:hover {
            background-color: var(--brand-primary) !important;
            color: #ffffff !important;
            border-color: var(--brand-primary) !important;
        }
        .sovereign-auth-wrapper[data-theme="partner"] .sovereign-secondary-btn:hover {
            color: #000000 !important;
        }

        /* Retro Secondary Button Override */
        .sovereign-auth-wrapper[data-theme="retro"] .sovereign-secondary-btn {
            background-color: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 3px 3px 0px #000000 !important;
        }
        .sovereign-auth-wrapper[data-theme="retro"] .sovereign-secondary-btn:hover {
            transform: translate(-1px, -1px) !important;
            box-shadow: 4px 4px 0px #000000 !important;
            background-color: var(--brand-primary) !important;
            color: #ffffff !important;
            border-color: #000000 !important;
        }

        /* --- 🗓️ Sovereign Holiday Calendar — Full Overrides --- */
        /* Q1: January */
        .sovereign-auth-wrapper[data-holiday="new-year"]          { --brand-primary: #059669 !important; }
        /* Q1: February */
        .sovereign-auth-wrapper[data-holiday="valentine"]         { --brand-primary: #e11d48 !important; }
        .sovereign-auth-wrapper[data-holiday="defender-day"]      { --brand-primary: #64748b !important; }
        /* Q1: March */
        .sovereign-auth-wrapper[data-holiday="womens-day"]        { --brand-primary: #eab308 !important; }
        /* Q2: April */
        .sovereign-auth-wrapper[data-holiday="cosmonautics-day"]  { --brand-primary: #6366f1 !important; }
        .sovereign-auth-wrapper[data-holiday="doctor-day"]        { --brand-primary: #06b6d4 !important; }
        /* Q2: May */
        .sovereign-auth-wrapper[data-holiday="may-day"]           { --brand-primary: #ef4444 !important; }
        .sovereign-auth-wrapper[data-holiday="victory-day"]       { --brand-primary: #dc2626 !important; }
        .sovereign-auth-wrapper[data-holiday="orchid-day"]        { --brand-primary: #d946ef !important; }
        .sovereign-auth-wrapper[data-holiday="sons-birthday"]     { --brand-primary: #74acdf !important; }
        /* Q2: June */
        .sovereign-auth-wrapper[data-holiday="russia-day"]        { --brand-primary: #3b82f6 !important; }
        /* Q3: August */
        .sovereign-auth-wrapper[data-holiday="babel-library"]     { --brand-primary: #d97706 !important; }
        /* Q4: October */
        .sovereign-auth-wrapper[data-holiday="little-prince"]     { --brand-primary: #e11d48 !important; }
        /* Q4: November */
        .sovereign-auth-wrapper[data-holiday="national-unity"]    { --brand-primary: #f97316 !important; }
        /* Q4: December */
        .sovereign-auth-wrapper[data-holiday="constitution-day"]  { --brand-primary: #8b5cf6 !important; }
        .sovereign-auth-wrapper[data-holiday="new-year-eve"]      { --brand-primary: #a78bfa !important; }

        /* Dynamic Holiday Styles — apply to all events */
        .sovereign-auth-wrapper[data-holiday] .logo-mark {
            background: var(--brand-primary) !important;
            box-shadow: 0 0 15px var(--brand-primary) !important;
        }
        .sovereign-auth-wrapper[data-holiday] a {
            color: var(--brand-primary) !important;
        }
    </style>

</head>
<body>
@include('partials.theme-sync-body')
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper" data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>
        


        <div class="ambient-glows">
            <div class="glow-1"></div>
            <div class="glow-2"></div>
        </div>

        <div class="auth-card">
            <a href="/" class="logo-header" style="text-decoration: none;">
                <div class="logo-mark"></div>
                MEANLY
            </a>

            <h1 class="auth-title">Добро пожаловать</h1>
            <p class="auth-subtitle">
                Рады видеть! Авторизуйтесь с помощью защищенного Passkey ключа, чтобы войти в соответствующую панель управления.
            </p>

            <div class="auth-interaction">
                <!-- Passkey Authentication (100% Passwordless) -->
                <div class="space-y-4">
                    <x-passkeys::authenticate>
                        <div class="sovereign-btn-trigger">
                            Войти с помощью Passkey 🛡️
                        </div>
                    </x-passkeys::authenticate>
                </div>

                <div class="secondary-actions-wrapper" style="margin-top: 2rem; display: flex; flex-direction: column; gap: 10px;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--brand-subtext); text-transform: uppercase; letter-spacing: 0.05em; text-align: center; margin-bottom: 2px;">
                        Еще нет профиля?
                    </div>
                    <div style="display: flex; justify-content: center; width: 100%;">
                        <a href="/register" class="sovereign-secondary-btn" style="flex: 1;">
                            Создать профиль
                        </a>
                    </div>
                </div>
            </div>

            <div class="footer-links">
                <span>Terms of Service</span>
                <span>Privacy Policy</span>
            </div>
        </div>

        <div class="footer-brand">
            MEANLY.SYSTEMS
        </div>
    </div>


</body>
</html>
