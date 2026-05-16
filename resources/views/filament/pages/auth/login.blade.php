@php
    $currentPanel = filament()->getCurrentPanel()->getId();
@endphp
 
<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap');
 
            :root {
                --brand-primary: #f53003;
                --brand-bg: #FDFDFC;
                --brand-text: #1b1b18;
                --brand-border: #e3e3e0;
                --brand-card: #ffffff;
            }
 
            @media (prefers-color-scheme: dark) {
                :root {
                    --brand-bg: #0a0a0a;
                    --brand-text: #EDEDEC;
                    --brand-border: #3E3E3A;
                    --brand-card: #161615;
                    --brand-primary: #FF4433;
                }
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
                align-items: center;
                justify-content: center;
                background-color: var(--brand-bg);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
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
            }
 
            .auth-title {
                font-size: 24px;
                font-weight: 700;
                color: var(--brand-text);
                margin-bottom: 0.5rem;
                letter-spacing: -0.02em;
            }
 
            .auth-subtitle {
                font-size: 15px;
                color: #706f6c;
                line-height: 1.6;
                margin-bottom: 3rem;
            }
 
            @media (prefers-color-scheme: dark) {
                .auth-subtitle { color: #A1A09A; }
                .auth-card { border-color: rgba(255,255,255,0.1); }
            }
 
            .fi-btn, [type="submit"], button {
                width: 100% !important;
                height: 52px !important;
                background-color: var(--brand-primary) !important;
                color: #fff !important;
                border-radius: 8px !important;
                font-weight: 600 !important;
                font-size: 15px !important;
                border: 1px solid var(--brand-primary) !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 10px !important;
                transition: all 0.2s ease !important;
                text-transform: none !important;
            }
 
            button:hover {
                filter: brightness(1.1) !important;
                transform: translateY(-1px) !important;
            }
 
            .footer-brand {
                margin-top: 3rem;
                font-size: 12px;
                color: #706f6c;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                text-align: center;
            }
 
            .node-tag {
                display: inline-block;
                background: rgba(112, 111, 108, 0.05);
                border: 1px solid var(--brand-border);
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 10px;
                margin-bottom: 2rem;
                color: #706f6c;
                font-weight: 700;
            }
 
            @media (prefers-color-scheme: dark) {
                .node-tag { color: #A1A09A; }
            }
        </style>
 
        <div class="auth-card">
            <div class="node-tag">
                ANCHOR: {{ strtoupper($currentPanel) }}
            </div>
 
            <h1 class="auth-title">Welcome back</h1>
            <p class="auth-subtitle">
                Войдите в свою учетную запись, используя суверенный ключ доступа к среде {{ strtoupper($currentPanel) }}.
            </p>
 
            <div class="auth-interaction">
                <x-passkeys::authenticate />
            </div>
 
            <div class="footer-brand">
                {{ strtoupper($currentPanel) }}.MEANLY.TEST
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
