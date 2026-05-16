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
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap');
 
            :root {
                --brand-primary: #f53003;
                --brand-bg: #050505;
                --brand-card: #0a0a0a;
                --brand-text: #ffffff;
                --brand-subtext: #888888;
                --brand-border: #1a1a1a;
                --cursor-btn-bg: #111111;
                --cursor-btn-hover: #1a1a1a;
            }
 
            /* 🚀 Fullscreen Escape from Filament Frame */
            .fi-simple-main {
                background-color: var(--brand-bg) !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                max-width: 100vw !important;
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
                width: 100%;
                height: 100vh;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                background-color: var(--brand-bg);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
                padding: 2rem;
            }
 
            .auth-card {
                background: var(--brand-card);
                border: 1px solid var(--brand-border);
                padding: 4rem 3rem;
                border-radius: 12px;
                text-align: center; /* Centered like Cursor login */
                width: 100%;
                max-width: 440px;
                position: relative;
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
 
            /* 🕹️ Cursor-style Solid Button */
            .fi-btn, [type="submit"], button {
                width: 100% !important;
                height: 44px !important;
                background-color: var(--cursor-btn-bg) !important;
                color: var(--brand-text) !important;
                border-radius: 6px !important;
                font-weight: 500 !important;
                font-size: 14px !important;
                border: 1px solid var(--brand-border) !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
                transition: background-color 0.2s ease !important;
                text-transform: none !important;
                margin-top: 0.5rem;
            }
 
            button:hover {
                background-color: var(--cursor-btn-hover) !important;
                opacity: 1 !important;
            }
 
            /* Add a small key icon effect via CSS if possible, but component has its own */
            button svg {
                width: 18px !important;
                height: 18px !important;
                opacity: 0.8;
            }
 
            .footer-brand {
                margin-top: 3rem;
                font-size: 11px;
                color: #222;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                text-align: center;
            }
 
            .footer-links {
                margin-top: 1.5rem;
                display: flex;
                justify-content: center;
                gap: 1.5rem;
                font-size: 11px;
                color: #444;
            }
        </style>
 
        <div class="auth-card">
            <div class="logo-header">
                <div class="logo-mark"></div>
                MEANLY
            </div>
 
            <h1 class="auth-title">Welcome to {{ $panelName }}</h1>
            <p class="auth-subtitle">
                The new way to manage sovereign infrastructure.
            </p>
 
            <div class="auth-interaction">
                <x-passkeys::authenticate />
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
