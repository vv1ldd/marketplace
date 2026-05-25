@php
    $currentPanel = filament()->getCurrentPanel()->getId();
    $panelName = match($currentPanel) {
        'ops' => 'Operations',
        'partner' => 'Partner',
        'audit' => 'Audit',
        default => ucfirst($currentPanel)
    };
@endphp

<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper" data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" data-holiday="{{ request()->cookie('holiday') }}">
        @include('partials.theme-sync-body')
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap');

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

            /* --- 🍃 Theme 4: Nordic (Warm Eco / Scandinavian Minimalist) --- */
            .sovereign-auth-wrapper[data-theme="nordic"] {
                --brand-primary: #1e3f20;
                --brand-primary-glow: rgba(30, 63, 32, 0.3);
                --brand-bg: #faf7f2;
                --brand-card: #ffffff;
                --brand-text: #2b2b2b;
                --brand-subtext: #6e706a;
                --brand-border: #e6dfd5;
                --cursor-btn-bg: #f5f2eb;
                --cursor-btn-hover: rgba(30, 63, 32, 0.05);
                background-color: #faf7f2 !important;
            }

            /* --- 🟣 Theme 5: Synthwave (Retrofuturism / Pink-Purple Neon) --- */
            .sovereign-auth-wrapper[data-theme="synthwave"] {
                --brand-primary: #ff007f;
                --brand-primary-glow: rgba(255, 0, 127, 0.45);
                --brand-bg: #120e2e;
                --brand-card: #1c1543;
                --brand-text: #ffffff;
                --brand-subtext: #8e89c5;
                --brand-border: rgba(255, 0, 127, 0.15);
                --cursor-btn-bg: rgba(18, 14, 46, 0.55);
                --cursor-btn-hover: rgba(255, 0, 127, 0.1);
                background-color: #120e2e !important;
            }

            /* --- 🏁 Theme 6: Carbon (High-Performance Stealth / Motorsport Yellow) --- */
            .sovereign-auth-wrapper[data-theme="carbon"] {
                --brand-primary: #facc15;
                --brand-primary-glow: rgba(250, 204, 21, 0.45);
                --brand-bg: #070708;
                --brand-card: #101012;
                --brand-text: #ffffff;
                --brand-subtext: #8b8b92;
                --brand-border: #222226;
                --cursor-btn-bg: #151518;
                --cursor-btn-hover: rgba(250, 204, 21, 0.1);
                background-color: #070708 !important;
            }

            /* 🚀 Fullscreen Escape from Filament Frame */
            .fi-simple-main {
                background-color: var(--brand-bg) !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100vw !important;
                min-height: 100vh !important;
                height: auto !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                max-width: 100vw !important;
                transition: background-color 0.3s ease;
            }

            .fi-simple-main-container {
                box-shadow: none !important;
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            .fi-logo, .fi-simple-header { display: none !important; }

            .sovereign-auth-wrapper {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background-color: var(--brand-bg);
                z-index: 99999;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
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
                text-align: center; /* Centered like Cursor login */
                width: 100%;
                max-width: 440px;
                margin-top: 0;
                margin-bottom: 2rem;
                position: relative;
                z-index: 10;
                box-shadow: 0 20px 40px rgba(0,0,0,0.6);
                transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
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

            /* ⌨️ Form Fields Overrides */
            .fi-fo-field-wrp { text-align: left !important; margin-bottom: 1.25rem !important; }
            .fi-input-wrp { background-color: var(--cursor-btn-bg) !important; border: 1px solid var(--brand-border) !important; border-radius: 8px !important; box-shadow: none !important; }
            .fi-input { color: var(--brand-text) !important; font-size: 14px !important; }
            .fi-fo-field-wrp-label label, .fi-fo-field-wrp-label label * { color: #bbbbbb !important; font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; margin-bottom: 0.5rem !important; }

            /* 🕹️ Sovereign Solid Primary Button - Theme Inherited */
            .fi-btn, [type="submit"], button, .sovereign-btn-trigger,
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
                box-shadow: 0 4px 15px var(--brand-primary-glow) !important;
            }

            .fi-btn:hover, [type="submit"]:hover, button:hover, .sovereign-btn-trigger:hover,
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
                background: var(--brand-primary) !important;
                box-shadow: 0 0 15px rgba(255, 159, 10, 0.5) !important;
            }
            .sovereign-auth-wrapper[data-theme="partner"] .auth-interaction a {
                color: var(--brand-primary) !important;
            }
            .sovereign-auth-wrapper[data-theme="partner"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="partner"] [type="submit"],
            .sovereign-auth-wrapper[data-theme="partner"] button,
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
            .sovereign-auth-wrapper[data-theme="retro"] .fi-btn, 
            .sovereign-auth-wrapper[data-theme="retro"] [type="submit"], 
            .sovereign-auth-wrapper[data-theme="retro"] button, 
            .sovereign-auth-wrapper[data-theme="retro"] .sovereign-btn-trigger,
            .sovereign-auth-wrapper[data-theme="retro"] .auth-interaction div[onclick] > div {
                background-color: var(--brand-primary) !important;
                color: #ffffff !important;
                border: 2px solid #000000 !important;
                border-radius: 0px !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .fi-btn:hover, 
            .sovereign-auth-wrapper[data-theme="retro"] [type="submit"]:hover, 
            .sovereign-auth-wrapper[data-theme="retro"] button:hover, 
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

            /* Nordic Theme Overrides */
            .sovereign-auth-wrapper[data-theme="nordic"] .auth-card {
                background: #ffffff !important;
                border: 1px solid #e6dfd5 !important;
                box-shadow: 0 20px 40px rgba(30, 63, 32, 0.05) !important;
                color: #2b2b2b !important;
                border-radius: 20px !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .logo-mark {
                background: #1e3f20 !important;
                border-radius: 12px !important;
                box-shadow: 0 4px 10px rgba(30, 63, 32, 0.15) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .logo-header {
                color: #1e3f20 !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .auth-title {
                color: #1e3f20 !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .auth-subtitle {
                color: #6e706a !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] a {
                color: #1e3f20 !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="nordic"] [type="submit"],
            .sovereign-auth-wrapper[data-theme="nordic"] button,
            .sovereign-auth-wrapper[data-theme="nordic"] .sovereign-btn-trigger,
            .sovereign-auth-wrapper[data-theme="nordic"] .auth-interaction div[onclick] > div {
                background-color: #1e3f20 !important;
                border-color: #1e3f20 !important;
                color: #faf7f2 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="nordic"] [type="submit"]:hover,
            .sovereign-auth-wrapper[data-theme="nordic"] button:hover,
            .sovereign-auth-wrapper[data-theme="nordic"] .sovereign-btn-trigger:hover,
            .sovereign-auth-wrapper[data-theme="nordic"] .auth-interaction div[onclick] > div:hover {
                background-color: #152d16 !important;
                border-color: #152d16 !important;
                box-shadow: 0 6px 16px rgba(30, 63, 32, 0.3) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .skin-switcher-pill {
                background: #ffffff !important;
                border: 1px solid #e6dfd5 !important;
                box-shadow: none !important;
                border-radius: 100px !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .skin-btn {
                border-radius: 100px !important;
                color: #6e706a !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .footer-brand {
                color: #6e706a !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] #skin-btn-nordic {
                background: var(--brand-primary) !important;
                color: #ffffff !important;
                box-shadow: 0 2px 10px rgba(30, 63, 32, 0.3) !important;
                font-weight: 900;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .ambient-glows {
                display: none !important;
            }

            /* Synthwave Theme Overrides */
            .sovereign-auth-wrapper[data-theme="synthwave"] .auth-card {
                background: #1c1543 !important;
                border: 1px solid rgba(255, 0, 127, 0.2) !important;
                box-shadow: 0 20px 40px rgba(0,0,0,0.6), 0 0 20px rgba(255, 0, 127, 0.1) !important;
                border-radius: 16px !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .logo-mark {
                background: linear-gradient(135deg, #ff007f, #00f0ff) !important;
                border-radius: 8px !important;
                box-shadow: 0 0 15px rgba(255, 0, 127, 0.6) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] a,
            .sovereign-auth-wrapper[data-theme="synthwave"] .auth-interaction a {
                color: #00f0ff !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .logo-header {
                color: #ffffff !important;
                text-shadow: 0 0 8px rgba(0, 240, 255, 0.6) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="synthwave"] [type="submit"],
            .sovereign-auth-wrapper[data-theme="synthwave"] button,
            .sovereign-auth-wrapper[data-theme="synthwave"] .sovereign-btn-trigger,
            .sovereign-auth-wrapper[data-theme="synthwave"] .auth-interaction div[onclick] > div {
                background-color: #ff007f !important;
                border-color: #ff007f !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                box-shadow: 0 0 15px rgba(255, 0, 127, 0.4) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="synthwave"] [type="submit"]:hover,
            .sovereign-auth-wrapper[data-theme="synthwave"] button:hover,
            .sovereign-auth-wrapper[data-theme="synthwave"] .sovereign-btn-trigger:hover,
            .sovereign-auth-wrapper[data-theme="synthwave"] .auth-interaction div[onclick] > div:hover {
                background-color: #e60072 !important;
                border-color: #e60072 !important;
                box-shadow: 0 0 25px rgba(255, 0, 127, 0.7) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .skin-switcher-pill {
                background: #120e2e !important;
                border: 1px solid rgba(255, 0, 127, 0.2) !important;
                box-shadow: none !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .skin-btn {
                color: #8e89c5 !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .footer-brand {
                color: #8e89c5 !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] #skin-btn-synthwave {
                background: var(--brand-primary) !important;
                color: #ffffff !important;
                box-shadow: 0 2px 10px rgba(255, 0, 127, 0.4) !important;
                font-weight: 900;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .glow-1 {
                background: radial-gradient(circle, rgba(255, 0, 127, 0.15) 0%, rgba(0,0,0,0) 70%) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .glow-2 {
                background: radial-gradient(circle, rgba(0, 240, 255, 0.12) 0%, rgba(0,0,0,0) 70%) !important;
            }

            /* Carbon Theme Overrides */
            .sovereign-auth-wrapper[data-theme="carbon"] .auth-card {
                background: #101012 !important;
                border: 2px solid #222226 !important;
                border-radius: 4px !important;
                box-shadow: 0 20px 40px rgba(0,0,0,0.8) !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .logo-mark {
                background: #facc15 !important;
                border-radius: 4px !important;
                box-shadow: 0 4px 10px rgba(250, 204, 21, 0.2) !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] a,
            .sovereign-auth-wrapper[data-theme="carbon"] .auth-interaction a {
                color: #facc15 !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="carbon"] [type="submit"],
            .sovereign-auth-wrapper[data-theme="carbon"] button,
            .sovereign-auth-wrapper[data-theme="carbon"] .sovereign-btn-trigger,
            .sovereign-auth-wrapper[data-theme="carbon"] .auth-interaction div[onclick] > div {
                background-color: #facc15 !important;
                border-color: #facc15 !important;
                color: #000000 !important;
                border-radius: 4px !important;
                font-weight: 800 !important;
                box-shadow: none !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="carbon"] [type="submit"]:hover,
            .sovereign-auth-wrapper[data-theme="carbon"] button:hover,
            .sovereign-auth-wrapper[data-theme="carbon"] .sovereign-btn-trigger:hover,
            .sovereign-auth-wrapper[data-theme="carbon"] .auth-interaction div[onclick] > div:hover {
                background-color: #eab308 !important;
                border-color: #eab308 !important;
                box-shadow: 0 0 15px rgba(250, 204, 21, 0.4) !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .skin-switcher-pill {
                background: #070708 !important;
                border: 2px solid #222226 !important;
                border-radius: 4px !important;
                box-shadow: none !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .skin-btn {
                border-radius: 4px !important;
                color: #8b8b92 !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .footer-brand {
                color: #8b8b92 !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] #skin-btn-carbon {
                background: var(--brand-primary) !important;
                color: #000000 !important;
                box-shadow: 0 2px 10px rgba(250, 204, 21, 0.3) !important;
                font-weight: 900;
                border-radius: 4px !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .glow-1 {
                background: radial-gradient(circle, rgba(250, 204, 21, 0.05) 0%, rgba(0,0,0,0) 70%) !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .glow-2 {
                display: none !important;
            }
        </style>


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
                Рады видеть! Чтобы продолжить работу с платформой {{ $panelName }}, авторизуйтесь в системе.
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

                @if ($currentPanel === 'client')
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
                @endif

                @if ($currentPanel === 'partner')
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
                @endif
            </div>

            <div class="footer-links">
                <span>Terms of Service</span>
                <span>Privacy Policy</span>
            </div>
        </div>


        <div class="footer-brand">
            {{ strtoupper($currentPanel) }}.MEANLY.SYSTEMS
        </div>
    </div>
</x-filament-panels::page.simple>
