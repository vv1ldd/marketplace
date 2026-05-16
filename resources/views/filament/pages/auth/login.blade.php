@php
    $currentPanel = filament()->getCurrentPanel()->getId();
@endphp
 
<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Inter:wght@400;700;900&display=swap');
 
            :root {
                --amber: #f59e0b;
                --emerald: #10b981;
                --bg: #05070a;
                --bg-card: #0c111d;
                --text: #f1f5f9;
                --text-dim: #64748b;
            }
 
            .fi-simple-main, .fi-simple-page, .fi-simple-main-container {
                background-color: var(--bg) !important;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: 100vh !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
 
            .fi-logo, .fi-simple-header { display: none !important; }
 
            .sovereign-auth-wrapper {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem;
            }
 
            .auth-card {
                width: 100%;
                max-width: 460px;
                background: var(--bg-card);
                border: 1px solid rgba(16, 185, 129, 0.1);
                padding: 4rem 3rem;
                border-radius: 32px;
                box-shadow: 0 40px 100px rgba(0,0,0,0.8), 0 0 40px rgba(16, 185, 129, 0.05);
                text-align: center;
                font-family: 'Inter', sans-serif;
                position: relative;
                overflow: hidden;
            }
 
            .auth-card::before {
                content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px;
                background: linear-gradient(90deg, transparent, var(--emerald), transparent);
                opacity: 0.5;
            }
 
            .node-badge {
                display: inline-block; padding: 0.5rem 1.2rem;
                background: rgba(16, 185, 129, 0.07);
                color: var(--emerald);
                border: 1px solid rgba(16, 185, 129, 0.2);
                border-radius: 99px;
                font-size: 10px; font-weight: 800;
                text-transform: uppercase; letter-spacing: 0.15em;
                margin-bottom: 2rem;
            }
 
            .intent-box {
                background: rgba(0,0,0,0.25);
                border-radius: 16px;
                padding: 1.5rem;
                margin: 2.5rem 0;
                text-align: left;
                border: 1px solid rgba(255,255,255,0.03);
            }
 
            .intent-label {
                font-size: 9px; color: var(--text-dim); text-transform: uppercase;
                letter-spacing: 0.1em; margin-bottom: 0.75rem; display: block;
                font-family: 'JetBrains Mono', monospace;
            }
 
            .intent-text {
                font-size: 13px; color: #94a3b8; line-height: 1.6;
                font-family: 'JetBrains Mono', monospace;
            }
 
            .btn-sovereign {
                width: 100%; height: 68px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #fff; border: none; border-radius: 20px;
                font-weight: 900; font-size: 15px; cursor: pointer;
                display: flex; align-items: center; justify-content: center; gap: 14px;
                box-shadow: 0 15px 35px rgba(16, 185, 129, 0.25);
                transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                text-transform: uppercase; letter-spacing: 0.05em;
            }
 
            .btn-sovereign:hover {
                transform: translateY(-3px) scale(1.01);
                box-shadow: 0 20px 45px rgba(16, 185, 129, 0.35);
            }
 
            .btn-sovereign:active { transform: translateY(0) scale(0.98); }
 
            .footer-info {
                margin-top: 3rem; font-size: 11px; color: var(--text-dim);
                text-transform: uppercase; letter-spacing: 0.1em;
            }
        </style>
 
        <div class="auth-card">
            <div class="node-badge">
                IDENTITY_ANCHOR: ACTIVE
            </div>
 
            <h1 style="font-size: 2.4rem; font-weight: 900; color: #fff; letter-spacing: -0.03em; margin-bottom: 0.75rem;">Sovereign Entry</h1>
            <p style="color: var(--text-dim); font-size: 15px; max-width: 300px; margin: 0 auto;">Криптографическая подпись доступа к узлу {{ strtoupper($currentPanel) }}</p>
 
            <div class="intent-box">
                <span class="intent-label">Document of Intent</span>
                <div class="intent-text">
                    Я, владелец суверенной идентичности, подтверждаю намерение получить доступ к среде <b>{{ strtoupper($currentPanel) }}</b>.
                    <br><br>
                    <div style="font-size: 10px; color: #475569;">
                        CHALLENGE: <span id="visible-challenge">PENDING...</span>
                    </div>
                </div>
            </div>
 
            <x-passkeys::authenticate>
                <button type="button" class="btn-sovereign" onclick="authenticateWithPasskey()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3m0 0a10.003 10.003 0 0110 10c0 2.49-.913 4.766-2.427 6.51l-.822.948m-4.751-2.455A9.054 9.054 0 0015.5 15.5m-5 0A9.054 9.054 0 0115.5 15.5M15.5 15.5l.054.09" />
                    </svg>
                    SIGN_INTENT & ACCESS
                </button>
            </x-passkeys::authenticate>
 
            <div class="footer-info">
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
                    document.getElementById('visible-challenge').innerText = currentOptions.challenge.substring(0, 16) + '...';
                } catch (e) { console.error('Entropy Error:', e); }
            }
 
            async function authenticateWithPasskey() {
                try {
                    if (!currentOptions) await refreshEntropy();
                    const response = await startAuthentication({ optionsJSON: currentOptions });
                    document.getElementById('entropy-input').value = JSON.stringify(intentEntropy);
                    document.getElementById('response-input').value = JSON.stringify(response);
                    document.getElementById('passkey-login-form').submit();
                } catch (e) { 
                    if (e.name !== 'AbortError') alert('Auth Error: ' + e.message);
                }
            }
        </script>
    </div>
</x-filament-panels::page.simple>
