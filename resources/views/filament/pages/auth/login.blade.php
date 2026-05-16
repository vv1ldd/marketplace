@php
    $currentPanel = filament()->getCurrentPanel()->getId();
@endphp
 
<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
 
            :root {
                --primary: #10b981;
                --primary-glow: rgba(16, 185, 129, 0.4);
                --bg: #030712;
                --card-bg: rgba(17, 24, 39, 0.7);
                --glass-border: rgba(255, 255, 255, 0.08);
            }
 
            .fi-simple-main, .fi-simple-page, .fi-simple-main-container {
                background: radial-gradient(circle at top center, #111827 0%, #030712 100%) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
            }
 
            .fi-logo, .fi-simple-header { display: none !important; }
 
            .sovereign-auth-wrapper {
                width: 100%;
                max-width: 480px;
                padding: 2rem;
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
 
            .auth-card {
                background: var(--card-bg);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: 1px solid var(--glass-border);
                padding: 4rem 3rem;
                border-radius: 32px;
                box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.8);
                text-align: center;
                position: relative;
            }
 
            /* ✨ Subtle Animated Border Gradient */
            .auth-card::after {
                content: ""; position: absolute; inset: -1px;
                background: linear-gradient(135deg, rgba(16, 185, 129, 0.3), transparent 40%, transparent 60%, rgba(16, 185, 129, 0.2));
                border-radius: 32px; z-index: -1;
            }
 
            .badge-sovereign {
                display: inline-flex; align-items: center; gap: 8px;
                padding: 0.6rem 1.2rem; background: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 99px;
                color: var(--primary); font-size: 11px; font-weight: 700;
                text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2.5rem;
            }
 
            .badge-dot { width: 6px; height: 6px; background: var(--primary); border-radius: 50%; box-shadow: 0 0 10px var(--primary); }
 
            .auth-title {
                font-size: 32px; font-weight: 800; color: #fff;
                margin-bottom: 1rem; letter-spacing: -0.03em;
            }
 
            .auth-subtitle {
                font-size: 16px; color: #94a3b8; line-height: 1.6;
                margin-bottom: 3.5rem; font-weight: 500;
            }
 
            /* 🟢 Landing Style Premium Button */
            .fi-btn, [type="submit"], button {
                width: 100% !important; height: 64px !important;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
                color: #fff !important; border-radius: 16px !important;
                font-weight: 700 !important; font-size: 17px !important;
                border: none !important; cursor: pointer !important;
                display: flex !important; align-items: center !important; justify-content: center !important;
                gap: 12px !important; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.3) !important;
                text-transform: none !important;
            }
 
            button:hover {
                transform: translateY(-2px) !important;
                box-shadow: 0 25px 50px -12px rgba(16, 185, 129, 0.4) !important;
            }
 
            .footer-node {
                margin-top: 3.5rem; font-size: 12px; color: #64748b;
                display: flex; flex-direction: column; gap: 4px;
            }
 
            .node-url { color: #fff; font-weight: 700; font-size: 11px; letter-spacing: 0.05em; }
        </style>
 
        <div class="auth-card">
            <div class="badge-sovereign">
                <span class="badge-dot"></span>
                Sovereign Entry Active
            </div>
 
            <h1 class="auth-title">Consortium Login</h1>
            <p class="auth-subtitle">
                Безопасный доступ к узлу управления через ваш персональный криптографический ключ.
            </p>
 
            <div class="login-interaction-zone">
                <x-passkeys::authenticate />
            </div>
 
            <div class="footer-node">
                <span>IDENTITY_ANCHOR_NODE</span>
                <span class="node-url">{{ strtoupper($currentPanel) }}.MEANLY.TEST</span>
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
