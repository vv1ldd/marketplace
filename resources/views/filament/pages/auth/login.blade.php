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
                box-shadow: none !important; /* 🚫 No more stupid shadows */
                text-align: left;
                width: 100%;
                max-width: 440px;
                position: relative;
            }
 
            .logo-header {
                position: absolute;
                top: -5rem;
                left: 0;
                display: flex;
                align-items: center;
                gap: 0.6rem;
                font-weight: 800;
                font-size: 1.1rem;
                color: var(--brand-text);
                letter-spacing: -0.02em;
            }
 
            .logo-mark {
                width: 12px;
                height: 12px;
                background: var(--brand-primary);
                border-radius: 3px;
            }
 
            .auth-title {
                font-size: 28px;
                font-weight: 600;
                color: var(--brand-text);
                margin-bottom: 1rem;
                letter-spacing: -0.03em;
            }
 
            .auth-subtitle {
                font-size: 15px;
                color: var(--brand-subtext);
                line-height: 1.6;
                margin-bottom: 3.5rem;
            }
 
            /* 💊 Cursor-style Pill Button (White & Solid) */
            .fi-btn, [type="submit"], button {
                width: 100% !important;
                height: 48px !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                border-radius: 100px !important;
                font-weight: 600 !important;
                font-size: 14px !important;
                border: none !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                transition: opacity 0.2s ease !important;
                text-transform: none !important;
                margin-top: 1rem;
            }
 
            button:hover {
                opacity: 0.9 !important;
                transform: none !important;
            }
 
            /* Label for the passkey button inside the component */
            button span, .auth-interaction button {
                font-family: inherit !important;
            }
 
            .footer-brand {
                margin-top: 4rem;
                font-size: 11px;
                color: #222;
                text-transform: uppercase;
                letter-spacing: 0.1em;
                font-weight: 800;
                text-align: left;
            }
 
            .node-tag {
                display: inline-block;
                background: rgba(255, 255, 255, 0.03);
                border: 1px solid var(--brand-border);
                padding: 4px 12px;
                border-radius: 100px;
                font-size: 10px;
                margin-bottom: 2.5rem;
                color: var(--brand-subtext);
                font-weight: 700;
                letter-spacing: 0.05em;
            }
        </style>
 
        <div class="auth-card">
            <div class="logo-header">
                <div class="logo-mark"></div>
                MEANLY
            </div>
 
            <div class="node-tag">
                ENVIRONMENT: {{ strtoupper($panelName) }}
            </div>
 
            <h1 class="auth-title">С возвращением.</h1>
            <p class="auth-subtitle">
                Ваша инфраструктура готова к работе. Используйте суверенный ключ доступа для входа в среду {{ $panelName }}.
            </p>
 
            <div class="auth-interaction">
                <x-passkeys::authenticate />
            </div>
 
            <div class="footer-brand">
                {{ strtoupper($currentPanel) }}.MEANLY.SYSTEMS
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
