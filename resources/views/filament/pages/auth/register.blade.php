@php
    $currentPanel = filament()->getCurrentPanel()->getId();
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

            /* Target any nested card wrapping element from Filament's simple layout */
            .fi-simple-main-container > * {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
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
                font-family: 'Instrument Sans', sans-serif !important;
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
                padding: 3.5rem 2.5rem;
                border-radius: 12px;
                text-align: center;
                width: 100%;
                max-width: 480px;
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
                margin-bottom: 3rem;
            }

            .logo-mark {
                width: 12px;
                height: 12px;
                background: var(--brand-primary);
                border-radius: 3px;
                box-shadow: 0 0 15px rgba(245, 48, 3, 0.5);
                transition: all 0.3s ease;
            }

            .auth-title { font-size: 24px; font-weight: 600; color: var(--brand-text); margin-bottom: 0.75rem; letter-spacing: -0.02em; }
            .auth-subtitle { font-size: 14px; color: var(--brand-subtext); line-height: 1.5; margin-bottom: 2.5rem; }
            .auth-subtitle a { color: var(--brand-primary); text-decoration: none; font-weight: 500; }

            /* ⌨️ Form Fields Overrides */
            .fi-fo-field-wrp { text-align: left !important; margin-bottom: 1.25rem !important; }
            .fi-input-wrp { background-color: var(--cursor-btn-bg) !important; border: 1px solid var(--brand-border) !important; border-radius: 8px !important; box-shadow: none !important; }
            .fi-input { color: var(--brand-text) !important; font-size: 14px !important; }
            .fi-fo-field-wrp-label label, .fi-fo-field-wrp-label label * { color: #bbbbbb !important; font-size: 11px !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 0.05em !important; margin-bottom: 0.5rem !important; }

            /* 🕹️ Segmented Control (ToggleButtons) styling */
            .fi-fo-toggle-buttons {
                display: flex !important;
                background: var(--cursor-btn-bg) !important;
                border: 1px solid var(--brand-border) !important;
                border-radius: 8px !important;
                padding: 4px !important;
                margin-bottom: 2rem !important;
            }
            .fi-fo-toggle-buttons-option {
                flex: 1 !important;
                text-align: center !important;
            }
            .fi-fo-toggle-buttons-option label {
                padding: 8px !important;
                border-radius: 6px !important;
                font-size: 13px !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                transition: all 0.2s ease !important;
                border: none !important;
                background: transparent !important;
                color: var(--brand-subtext) !important;
            }
            .fi-fo-toggle-buttons-option-active label {
                background: var(--brand-border) !important;
                color: var(--brand-text) !important;
            }

            /* 🕹️ Sovereign Solid Primary Button - Theme Inherited */
            .fi-btn, button[type="submit"] {
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
                transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1) !important;
                margin-top: 1rem !important;
                box-shadow: 0 4px 15px var(--brand-primary-glow) !important;
            }
            .fi-btn:hover, button[type="submit"]:hover {
                background-color: var(--brand-primary) !important;
                border-color: var(--brand-primary) !important;
                box-shadow: 0 0 20px var(--brand-primary-glow) !important;
                filter: brightness(1.1) !important;
                transform: translateY(-1px) !important;
            }

            #inn-result-pill {
                display: none;
                background: rgba(16, 185, 129, 0.05);
                border: 1px solid rgba(16, 185, 129, 0.15);
                border-radius: 8px;
                padding: 12px;
                margin-top: -0.5rem;
                margin-bottom: 1.5rem;
                text-align: left;
                font-size: 12px;
                color: #10b981;
                animation: fadeIn 0.3s ease forwards;
            }

            @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            @keyframes pulse { 
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 48, 3, 0.2); } 
                70% { transform: scale(1.05); box-shadow: 0 0 0 12px transparent; } 
                100% { transform: scale(1); box-shadow: 0 0 0 0 transparent; } 
            }

            .footer-brand {
                margin-top: 1rem;
                font-size: 11px;
                color: #666666;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                z-index: 10;
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

            /* --- Theme Specific Overrides for Register --- */
            
            /* Partner (Glassmorphism & Gold) */
            .sovereign-auth-wrapper[data-theme="partner"] .auth-card {
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
            }
            .sovereign-auth-wrapper[data-theme="partner"] .logo-mark {
                background: var(--brand-primary) !important;
                box-shadow: 0 0 15px rgba(255, 159, 10, 0.5) !important;
            }
            .sovereign-auth-wrapper[data-theme="partner"] a {
                color: var(--brand-primary) !important;
            }
            .sovereign-auth-wrapper[data-theme="partner"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="partner"] button[type="submit"] {
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
            .sovereign-auth-wrapper[data-theme="retro"] button[type="submit"] {
                background-color: var(--brand-primary) !important;
                color: #ffffff !important;
                border: 2px solid #000000 !important;
                border-radius: 0px !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .fi-btn:hover, 
            .sovereign-auth-wrapper[data-theme="retro"] button[type="submit"]:hover {
                transform: translate(-2px, -2px) !important;
                box-shadow: 6px 6px 0px #000000 !important;
                background-color: var(--brand-primary) !important;
                border-color: #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .footer-brand {
                color: #4b5563 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] a {
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
            .sovereign-auth-wrapper[data-theme="retro"] .sovereign-orb-outer {
                border-color: #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .sovereign-orb-inner {
                background: none !important;
                border-color: #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .sovereign-orb-inner svg {
                stroke: var(--brand-primary) !important;
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
            .sovereign-auth-wrapper[data-theme="nordic"] button[type="submit"] {
                background-color: #1e3f20 !important;
                border-color: #1e3f20 !important;
                color: #faf7f2 !important;
                border-radius: 8px !important;
                box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="nordic"] button[type="submit"]:hover {
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
            .sovereign-auth-wrapper[data-theme="nordic"] .sovereign-orb-outer {
                border-color: rgba(30, 63, 32, 0.2) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .sovereign-orb-inner {
                background: radial-gradient(circle, rgba(30, 63, 32, 0.05) 0%, transparent 70%) !important;
                border-color: rgba(30, 63, 32, 0.3) !important;
            }
            .sovereign-auth-wrapper[data-theme="nordic"] .sovereign-orb-inner svg {
                stroke: #1e3f20 !important;
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
            .sovereign-auth-wrapper[data-theme="synthwave"] a {
                color: #00f0ff !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .logo-header {
                color: #ffffff !important;
                text-shadow: 0 0 8px rgba(0, 240, 255, 0.6) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="synthwave"] button[type="submit"] {
                background-color: #ff007f !important;
                border-color: #ff007f !important;
                color: #ffffff !important;
                border-radius: 6px !important;
                box-shadow: 0 0 15px rgba(255, 0, 127, 0.4) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="synthwave"] button[type="submit"]:hover {
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
            .sovereign-auth-wrapper[data-theme="synthwave"] .sovereign-orb-outer {
                border-color: rgba(255, 0, 127, 0.3) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .sovereign-orb-inner {
                background: radial-gradient(circle, rgba(255, 0, 127, 0.15) 0%, transparent 70%) !important;
                border-color: rgba(255, 0, 127, 0.4) !important;
            }
            .sovereign-auth-wrapper[data-theme="synthwave"] .sovereign-orb-inner svg {
                stroke: #ff007f !important;
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
            .sovereign-auth-wrapper[data-theme="carbon"] a {
                color: #facc15 !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .fi-btn,
            .sovereign-auth-wrapper[data-theme="carbon"] button[type="submit"] {
                background-color: #facc15 !important;
                border-color: #facc15 !important;
                color: #000000 !important;
                border-radius: 4px !important;
                font-weight: 800 !important;
                box-shadow: none !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .fi-btn:hover,
            .sovereign-auth-wrapper[data-theme="carbon"] button[type="submit"]:hover {
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
            .sovereign-auth-wrapper[data-theme="carbon"] .sovereign-orb-outer {
                border-color: rgba(250, 204, 21, 0.2) !important;
                border-radius: 4px !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .sovereign-orb-inner {
                background: radial-gradient(circle, rgba(250, 204, 21, 0.08) 0%, transparent 70%) !important;
                border-color: rgba(250, 204, 21, 0.3) !important;
                border-radius: 4px !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .sovereign-orb-inner svg {
                stroke: #facc15 !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .glow-1 {
                background: radial-gradient(circle, rgba(250, 204, 21, 0.05) 0%, rgba(0,0,0,0) 70%) !important;
            }
            .sovereign-auth-wrapper[data-theme="carbon"] .glow-2 {
                display: none !important;
            }

            /* Dev Mail Simulator Base Styles */
            .dev-mail-simulator {
                margin-top: 2rem;
                padding: 2rem;
                background: #0c0c0c;
                border: 1px solid var(--brand-border);
                border-radius: 8px;
                text-align: left;
                position: relative;
                overflow: hidden;
                animation: fadeIn 0.4s ease forwards;
            }
            .dev-mail-badge {
                position: absolute;
                top: 12px;
                right: 12px;
                font-size: 9px;
                font-weight: 700;
                color: var(--brand-primary);
                background: var(--brand-primary-glow);
                border: 1px solid var(--brand-border);
                padding: 2px 6px;
                border-radius: 4px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            .dev-mail-title {
                font-size: 13px;
                font-weight: 600;
                color: var(--brand-text);
                margin-bottom: 0.5rem;
                text-transform: uppercase;
                letter-spacing: 0.02em;
            }
            .dev-mail-text {
                font-size: 12px;
                color: var(--brand-subtext);
                margin-bottom: 1.5rem;
                line-height: 1.4;
            }

            /* Dev Mail Simulator Theme Specific Overrides */
            .sovereign-auth-wrapper[data-theme="partner"] .dev-mail-simulator {
                background: rgba(14, 14, 18, 0.65) !important;
                backdrop-filter: blur(24px);
                -webkit-backdrop-filter: blur(24px);
                border: 1px solid rgba(255, 255, 255, 0.06) !important;
            }

            .sovereign-auth-wrapper[data-theme="retro"] .dev-mail-simulator {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                border-radius: 0px !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .dev-mail-badge {
                border: 2px solid #000000 !important;
                border-radius: 0px !important;
                background: var(--brand-primary) !important;
                color: #ffffff !important;
                box-shadow: none !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .dev-mail-title {
                color: #000000 !important;
                font-weight: 800 !important;
            }
            .sovereign-auth-wrapper[data-theme="retro"] .dev-mail-text {
                color: #4b5563 !important;
            }

            .sovereign-auth-wrapper[data-theme="nordic"] .dev-mail-simulator {
                background: #ffffff !important;
                border: 1px solid #e6dfd5 !important;
                border-radius: 12px !important;
            }

            .sovereign-auth-wrapper[data-theme="synthwave"] .dev-mail-simulator {
                background: #1c1543 !important;
                border: 1px solid rgba(255, 0, 127, 0.2) !important;
            }

            .sovereign-auth-wrapper[data-theme="carbon"] .dev-mail-simulator {
                background: #101012 !important;
                border: 2px solid #222226 !important;
                border-radius: 4px !important;
            }
        </style>

        <div class="ambient-glows">
            <div class="glow-1"></div>
            <div class="glow-2"></div>
        </div>



        <div class="auth-card">
            <div class="logo-header">
                <div class="logo-mark"></div>
                MEANLY
            </div>

            <!-- 🪐 Futuristic Cyber Shield Animation -->
            <div style="margin: 2.5rem 0; display: flex; justify-content: center; position: relative;">
                <div class="sovereign-orb-outer" style="width: 110px; height: 110px; border-radius: 50%; border: 1px dashed rgba(245, 48, 3, 0.3); display: flex; align-items: center; justify-content: center; animation: spin 20s linear infinite;">
                    <div class="sovereign-orb-inner" style="width: 80px; height: 80px; border-radius: 50%; background: radial-gradient(circle, rgba(245, 48, 3, 0.15) 0%, transparent 70%); border: 1px solid rgba(245, 48, 3, 0.4); display: flex; align-items: center; justify-content: center; animation: pulse 3s ease-in-out infinite;">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#f53003" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                </div>
            </div>

            @if ($step === 'email')
                <h1 class="auth-title">Создание аккаунта</h1>
                <p class="auth-subtitle" style="margin-bottom: 2rem; color: #888;">
                    Введите ваш email, чтобы подтвердить адрес и настроить безопасный вход по биометрии (TouchID/FaceID).
                </p>
     
                <form wire:submit="register">
                    {{ $this->form }}
     
                    <button type="submit" class="fi-btn" style="background-color: var(--brand-primary) !important; border-color: var(--brand-primary) !important; font-weight: 700 !important; font-size: 15px !important; letter-spacing: 0.02em;">
                        Создать аккаунт
                    </button>
                </form>
     
                <div class="secondary-actions-wrapper" style="margin-top: 2rem; display: flex; flex-direction: column; gap: 10px;">
                    <div style="font-size: 11px; font-weight: 700; color: var(--brand-subtext); text-transform: uppercase; letter-spacing: 0.05em; text-align: center; margin-bottom: 2px;">
                        Уже есть аккаунт?
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center; width: 100%;">
                        <a href="{{ route('login') }}" class="sovereign-secondary-btn" style="flex: 1;">
                            Войти с Passkey
                        </a>
                    </div>
                </div>
            @else
                <h1 class="auth-title">Подтвердите ваш email 📧</h1>
                <p class="auth-subtitle" style="margin-bottom: 2rem; color: #888;">
                    Мы отправили письмо со ссылкой для активации на указанный вами адрес электронной почты.
                </p>

                <!-- Gorgeous Glowing Collapsible JSON Blueprint Console (for devs/geeks) -->
                <details style="text-align: left; background: #000; border: 1px solid var(--brand-border); border-radius: 8px; padding: 1.25rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; line-height: 1.5; color: #00ff66; margin-bottom: 2rem; box-shadow: inset 0 0 10px rgba(0,255,102,0.05); cursor: pointer;">
                    <summary style="font-size: 10px; color: #555; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; outline: none; list-style: none;">
                        [ Показать технические детали (L1 Blueprint) ]
                    </summary>
                    <div style="margin-top: 10px; border-top: 1px solid var(--brand-border); padding-top: 10px;">
                        <pre style="margin: 0; white-space: pre-wrap; font-family: inherit;">{{ $rawBlueprint }}</pre>
                    </div>
                </details>

                <!-- Sovereign Dev Mail Simulator -->
                @if (app()->environment(['local', 'testing', 'dev', 'development']))
                <div class="dev-mail-simulator">
                    <div class="dev-mail-badge">
                        Симулятор почты (DEV)
                    </div>

                    <h4 class="dev-mail-title">Письмо получено</h4>
                    <p class="dev-mail-text">
                        Для подтверждения адреса почты и завершения регистрации нажмите кнопку ниже:
                    </p>

                    <a href="{{ $magicLink }}" class="fi-btn" style="text-decoration: none !important; font-weight: 700 !important; font-size: 14px !important; letter-spacing: 0.02em; display: flex !important; align-items: center !important; justify-content: center !important;">
                        Подтвердить почту и войти
                    </a>
                </div>
                @endif
            @endif
        </div>

        <div class="footer-brand">
            {{ strtoupper($currentPanel) }}.MEANLY.SYSTEMS
        </div>
    </div>
</x-filament-panels::page.simple>
