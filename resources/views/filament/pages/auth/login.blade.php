@php
    $currentPanel = filament()->getCurrentPanel()->getId();
    $useTerminal = in_array($currentPanel, ['kernel', 'treasury', 'ops', 'audit', 'admin']);
@endphp
 
<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700;800&family=Inter:wght@400;700;900&display=swap');
 
            :root {
                --amber: #f59e0b;
                --amber-glow: rgba(245, 158, 11, 0.4);
                --bg: #080b10;
                --bg-card: #0f1420;
                --text: #f1f5f9;
                --text-dim: #64748b;
                --green: #10b981;
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
 
            /* 💻 TERMINAL MODE STYLES */
            @if($useTerminal)
            .sovereign-auth-wrapper {
                width: 100%;
                max-width: 900px;
                padding: 1.5rem;
                font-family: 'JetBrains Mono', monospace;
            }
            .auth-card {
                background: #0c111d;
                border: 1px solid rgba(245, 158, 11, 0.15);
                border-radius: 12px;
                padding: 0 !important;
                overflow: hidden;
                box-shadow: 0 0 40px rgba(0,0,0,0.8);
            }
            .terminal-header {
                background: rgba(255,255,255,0.03);
                padding: 0.75rem 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.05);
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 11px;
                color: var(--text-dim);
                text-transform: uppercase;
            }
            .terminal-body { padding: 2.5rem; }
            .crt-overlay::before {
                content: " "; display: block; position: fixed;
                top: 0; left: 0; bottom: 0; right: 0;
                background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.25) 50%), linear-gradient(90deg, rgba(255, 0, 0, 0.06), rgba(0, 255, 0, 0.02), rgba(0, 0, 255, 0.06));
                z-index: 100; background-size: 100% 4px, 3px 100%;
                pointer-events: none; opacity: 0.2;
            }
            @else
            /* 🛡️ CLASSIC SOVEREIGN MODE STYLES */
            .auth-container {
                display: flex; flex-direction: column; align-items: center; justify-content: center;
                width: 100%; max-width: 440px; padding: 2rem; font-family: 'Inter', sans-serif;
            }
            .auth-card {
                background: var(--bg-card); border: 1px solid rgba(255,255,255,0.07);
                padding: 3.5rem 2.5rem; border-radius: 24px; width: 100%;
                box-shadow: 0 30px 60px rgba(0,0,0,0.5); text-align: center;
            }
            @endif
 
            .auth-header h1 { 
                font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; 
                margin-bottom: 0.75rem; color: var(--text);
            }
            .auth-header p { color: #94a3b8; font-size: 0.9rem; line-height: 1.5; margin-bottom: 2.5rem; }
 
            .btn-passkey {
                width: 100%; height: 58px; background: var(--amber); color: #000;
                border: none; border-radius: 12px; font-weight: 800; font-size: 1.05rem;
                cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 12px;
            }
            .btn-passkey:hover { background: #fcd34d; transform: translateY(-2px); box-shadow: 0 10px 20px var(--amber-glow); }
 
            .intent-document {
                background: #ffffff03; border: 1px solid #ffffff07; border-radius: 16px;
                padding: 2rem; margin-bottom: 2.5rem; text-align: left;
            }
        </style>
 
        <div class="{{ $useTerminal ? 'crt-overlay' : 'auth-container' }}">
            <div class="auth-card">
                @if($useTerminal)
                <div class="terminal-header">
                    <div><span style="color:var(--green)">●</span> CONSORTIUM_{{ strtoupper($currentPanel) }}_NODE_V1.0</div>
                    <div>IDENTITY_ANCHOR: ACTIVE</div>
                </div>
                @endif
 
                <div class="{{ $useTerminal ? 'terminal-body' : '' }}">
                    <div class="auth-header">
                        <h1>{{ $useTerminal ? 'Sovereign Terminal' : 'Sovereign Entry' }}</h1>
                        <p>{{ $useTerminal ? 'Подпишите интент доступа к узлу ' . $currentPanel : 'Сформируйте криптографическую подпись для входа' }}</p>
                    </div>
 
                    <div class="intent-document">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: 0.7rem; color: var(--text-dim); text-transform: uppercase;">
                            <span>Документ-намерение</span>
                            <span id="current-ts">{{ now()->format('H:i:s.v') }}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; font-family: 'JetBrains Mono', monospace;">
                            Я, владелец суверенной идентичности, подтверждаю намерение получить доступ к среде <b>{{ strtoupper($currentPanel) }}</b>.
                            <br><br>
                            <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; font-size: 0.7rem; color: var(--text-dim);">
                                ORIGIN: {{ request()->ip() }}<br>
                                CHALLENGE: <span id="anchor-challenge">PENDING...</span>
                            </div>
                        </div>
                    </div>
 
                    <x-passkeys::authenticate>
                        <button type="button" class="btn-passkey" onclick="authenticateWithPasskey()">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3m0 0a10.003 10.003 0 0110 10c0 2.49-.913 4.766-2.427 6.51l-.822.948m-4.751-2.455A9.054 9.054 0 0015.5 15.5m-5 0A9.054 9.054 0 0115.5 15.5M15.5 15.5l.054.09" />
                            </svg>
                            {{ $useTerminal ? 'SIGN_AND_ACCESS' : 'Подписать и войти' }}
                        </button>
                    </x-passkeys::authenticate>
 
                    @if($useTerminal)
                    <div style="margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; display: flex; align-items: center; gap: 10px; font-family: 'JetBrains Mono'; font-size: 13px;">
                        <span style="color: var(--amber)">visitor@consortium:~$</span>
                        <input type="text" id="terminal-input" placeholder="type 'help'..." style="background:transparent; border:none; color:var(--amber); outline:none; flex-grow:1;">
                    </div>
                    @endif
                </div>
            </div>
        </div>
 
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
                    intentEntropy = { ts: '{{ now()->toISOString() }}', ip: '{{ request()->ip() }}', challenge: currentOptions.challenge };
                    document.getElementById('anchor-challenge').innerText = currentOptions.challenge.substring(0, 16) + '...';
                } catch (e) { console.error(e); }
            }
 
            async function authenticateWithPasskey() {
                try {
                    if (!currentOptions) await refreshEntropy();
                    const response = await startAuthentication({ optionsJSON: currentOptions });
                    const form = document.getElementById('passkey-login-form');
                    document.getElementById('entropy-input').value = JSON.stringify(intentEntropy);
                    document.getElementById('response-input').value = JSON.stringify(response);
                    form.submit();
                } catch (e) { alert('Критическая ошибка: ' + e.message); }
            }
 
            @if($useTerminal)
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const val = document.getElementById('terminal-input').value.toLowerCase();
                    if (val === 'sign' || val === 'login') authenticateWithPasskey();
                    if (val === 'help') alert('COMMANDS: [sign] - Biometric Handshake | [status] - System Health');
                    document.getElementById('terminal-input').value = '';
                }
            });
            @endif
        </script>
 
        <form id="passkey-login-form" method="POST" action="/passkeys/authenticate" style="display: none;">
            @csrf
            <input type="hidden" name="remember" value="true">
            <input type="hidden" name="intent_entropy" id="entropy-input">
            <input type="hidden" name="start_authentication_response" id="response-input">
        </form>
    </div>
</x-filament-panels::page.simple>
