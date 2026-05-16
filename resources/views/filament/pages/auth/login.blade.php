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
 
            /* 🏛️ Aligning with the Landing Style */
            .fi-simple-main, .fi-simple-page, .fi-simple-main-container {
                background-color: var(--brand-bg) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
            }
 
            .fi-logo, .fi-simple-header { display: none !important; }
 
            .sovereign-auth-wrapper {
                width: 100%;
                max-width: 440px;
                padding: 1.5rem;
            }
 
            .auth-card {
                background: var(--brand-card);
                border: 1px solid var(--brand-border);
                padding: 3.5rem 2.5rem;
                border-radius: 12px;
                box-shadow: 0px 0px 1px 0px rgba(0,0,0,0.03), 0px 1px 2px 0px rgba(0,0,0,0.06);
                text-align: left;
            }
 
            .auth-title {
                font-size: 24px;
                font-weight: 700;
                color: var(--brand-text);
                margin-bottom: 0.5rem;
                letter-spacing: -0.02em;
            }
 
            .auth-subtitle {
                font-size: 14px;
                color: #706f6c;
                line-height: 1.6;
                margin-bottom: 2.5rem;
            }
 
            @media (prefers-color-scheme: dark) {
                .auth-subtitle { color: #A1A09A; }
            }
 
            /* 🔴 Brand Button Style */
            .fi-btn, [type="submit"], button {
                width: 100% !important;
                height: 48px !important;
                background-color: var(--brand-primary) !important;
                color: #fff !important;
                border-radius: 6px !important;
                font-weight: 600 !important;
                font-size: 14px !important;
                border: 1px solid var(--brand-primary) !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 8px !important;
                transition: all 0.2s ease !important;
                text-transform: none !important;
                box-shadow: none !important;
            }
 
            button:hover {
                filter: brightness(1.1) !important;
                transform: translateY(-1px) !important;
            }
 
            .footer-brand {
                margin-top: 2.5rem;
                font-size: 11px;
                color: #706f6c;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                font-weight: 700;
                text-align: center;
            }
 
            .node-tag {
                display: inline-block;
                background: #fafafa;
                border: 1px solid #e3e3e0;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 10px;
                margin-bottom: 1.5rem;
                color: #706f6c;
            }
 
            @media (prefers-color-scheme: dark) {
                .node-tag { background: #161615; border-color: #3E3E3A; color: #A1A09A; }
            }
        </style>
 
        <div class="auth-card">
            <div class="node-tag">
                IDENTITY_ANCHOR_NODE: {{ strtoupper($currentPanel) }}
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
