@php
    $currentPanel = filament()->getCurrentPanel()->getId();
@endphp
 
<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            :root {
                --primary: #10b981;
                --primary-hover: #059669;
                --bg: #090e1a;
                --card-bg: #111827;
                --border: rgba(255, 255, 255, 0.1);
            }
 
            .fi-simple-main, .fi-simple-page, .fi-simple-main-container {
                background-color: var(--bg) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
            }
 
            .fi-logo, .fi-simple-header { display: none !important; }
 
            .sovereign-auth-wrapper {
                width: 100%;
                max-width: 440px;
                padding: 1rem;
            }
 
            .auth-card {
                background: var(--card-bg);
                border: 1px solid var(--border);
                padding: 3.5rem 2.5rem;
                border-radius: 24px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                text-align: center;
                font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
 
            .node-info {
                font-size: 11px;
                font-weight: 700;
                color: var(--primary);
                text-transform: uppercase;
                letter-spacing: 0.05em;
                margin-bottom: 1.5rem;
            }
 
            .auth-title {
                font-size: 26px;
                font-weight: 800;
                color: #fff;
                margin-bottom: 0.75rem;
                letter-spacing: -0.02em;
            }
 
            .auth-subtitle {
                font-size: 15px;
                color: #94a3b8;
                line-height: 1.5;
                margin-bottom: 3rem;
            }
 
            /* 🟢 Styling the native Spatie button */
            .fi-btn, [type="submit"], button.btn-login {
                width: 100% !important;
                height: 60px !important;
                background-color: var(--primary) !important;
                color: #fff !important;
                border-radius: 14px !important;
                font-weight: 700 !important;
                font-size: 16px !important;
                border: none !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                gap: 12px !important;
                transition: all 0.2s ease !important;
                box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2) !important;
                text-transform: uppercase !important;
            }
 
            .fi-btn:hover, button.btn-login:hover {
                background-color: var(--primary-hover) !important;
                transform: translateY(-1px) !important;
                box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.3) !important;
            }
 
            .footer-text {
                margin-top: 3rem;
                font-size: 12px;
                color: #4b5563;
                text-transform: uppercase;
                letter-spacing: 0.1em;
            }
 
            /* Hide the default Spatie error/info if any, to keep it clean */
            .spatie-passkeys-error { color: #ef4444; font-size: 12px; margin-top: 1rem; }
        </style>
 
        <div class="auth-card">
            <div class="node-info">
                Identity Protection Active
            </div>
 
            <h1 class="auth-title">Consortium Login</h1>
            <p class="auth-subtitle">
                Используйте суверенный ключ для входа на узел <span style="color: #fff; font-weight: 600;">{{ strtoupper($currentPanel) }}</span>.
            </p>
 
            {{-- 🛡️ Using NATIVE Spatie component for guaranteed flow --}}
            <x-passkeys::authenticate />
 
            <div class="footer-text">
                {{ strtoupper($currentPanel) }}.MEANLY.TEST
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
