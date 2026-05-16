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
 
            /* 🏰 Clean Site Style */
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
                padding: 3rem 2.5rem;
                border-radius: 20px;
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
                font-size: 24px;
                font-weight: 800;
                color: #fff;
                margin-bottom: 0.75rem;
                letter-spacing: -0.02em;
            }
 
            .auth-subtitle {
                font-size: 14px;
                color: #94a3b8;
                line-height: 1.5;
                margin-bottom: 2.5rem;
            }
 
            /* 🟢 Native-look Premium Button */
            .btn-login {
                width: 100%;
                height: 56px;
                background-color: var(--primary);
                color: #fff;
                border-radius: 12px;
                font-weight: 700;
                font-size: 15px;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                transition: all 0.2s ease;
                box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.2);
            }
 
            .btn-login:hover {
                background-color: var(--primary-hover);
                transform: translateY(-1px);
                box-shadow: 0 20px 25px -5px rgba(16, 185, 129, 0.3);
            }
 
            .btn-login:active {
                transform: translateY(0);
            }
 
            .footer-text {
                margin-top: 2.5rem;
                font-size: 12px;
                color: #4b5563;
            }
        </style>
 
        <div class="auth-card">
            <div class="node-info">
                Sovereign Identity Protection
            </div>
 
            <h1 class="auth-title">Consortium Login</h1>
            <p class="auth-subtitle">
                Войдите в систему управления узлом <span style="color: #fff; font-weight: 600;">{{ strtoupper($currentPanel) }}</span> с помощью криптографического ключа.
            </p>
 
            <x-passkeys::authenticate>
                <button type="button" class="btn-login" onclick="authenticateWithPasskey()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3m0 0a10.003 10.003 0 0110 10c0 2.49-.913 4.766-2.427 6.51l-.822.948m-4.751-2.455A9.054 9.054 0 0015.5 15.5m-5 0A9.054 9.054 0 0115.5 15.5M15.5 15.5l.054.09" />
                    </svg>
                    ВХОД ЧЕРЕЗ PASSKEY
                </button>
            </x-passkeys::authenticate>
 
            <div class="footer-text">
                {{ strtoupper($currentPanel) }}.MEANLY.TEST
            </div>
        </div>
 
        <form id="passkey-login-form" method="POST" action="/passkeys/authenticate" style="display: none;">
            @csrf
            <input type="hidden" name="remember" value="true">
            <input type="hidden" name="intent_entropy" id="entropy-input">
            <input type="hidden" name="start_authentication_response" id="response-input">
        </form>
 
        <script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
        <script>
            let currentOptions = null;
            let intentEntropy = {};
            const { startAuthentication } = SimpleWebAuthnBrowser;
 
            window.addEventListener('DOMContentLoaded', async () => { await refreshEntropy(); });
 
            async function refreshEntropy() {
                try {
                    const response = await fetch('/passkeys/authentication-options', { credentials: 'include', headers: { 'Accept': 'application/json' } });
                    currentOptions = await response.json();
                    if (typeof currentOptions === 'string') currentOptions = JSON.parse(currentOptions);
                    intentEntropy = { ts: new Date().toISOString(), ip: "{{ request()->ip() }}", challenge: currentOptions.challenge };
                } catch (e) { console.error('Options Error:', e); }
            }
 
            async function authenticateWithPasskey() {
                try {
                    if (!currentOptions) await refreshEntropy();
                    const response = await startAuthentication({ optionsJSON: currentOptions });
                    
                    // 🛡️ CRITICAL: Fill and SUBMIT
                    document.getElementById('entropy-input').value = JSON.stringify(intentEntropy);
                    document.getElementById('response-input').value = JSON.stringify(response);
                    
                    console.log('🚀 Submitting authentication proof...');
                    document.getElementById('passkey-login-form').submit();
                } catch (e) { 
                    if (e.name !== 'AbortError') alert('Authentication Error: ' + e.message);
                }
            }
        </script>
    </div>
</x-filament-panels::page.simple>
