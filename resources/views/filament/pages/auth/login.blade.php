@php
    $currentPanel = filament()->getCurrentPanel()->getId();
    $isTerminalMode = request()->query('mode') === 'terminal';
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
                --emerald: #059669;
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
            }
 
            /* 💻 TERMINAL UI */
            .terminal-card {
                width: 100%;
                max-width: 900px;
                background: #0c111d;
                border: 1px solid rgba(245, 158, 11, 0.15);
                border-radius: 12px;
                overflow: hidden;
                box-shadow: 0 0 60px rgba(0,0,0,0.8);
                font-family: 'JetBrains Mono', monospace;
            }
 
            .terminal-header {
                background: rgba(255,255,255,0.03);
                padding: 0.75rem 1.5rem;
                border-bottom: 1px solid rgba(255,255,255,0.05);
                display: flex; justify-content: space-between; align-items: center;
                font-size: 11px; color: var(--text-dim); text-transform: uppercase;
            }
 
            .terminal-body { padding: 2.5rem; }
 
            /* 🛡️ CLASSIC UI */
            .classic-card {
                width: 100%;
                max-width: 440px;
                background: var(--bg-card);
                border: 1px solid rgba(255,255,255,0.07);
                padding: 3.5rem 2.5rem;
                border-radius: 24px;
                box-shadow: 0 30px 60px rgba(0,0,0,0.5);
                text-align: center;
                font-family: 'Inter', sans-serif;
            }
 
            .btn-passkey {
                width: 100%; height: 64px;
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: #fff; border: none; border-radius: 16px;
                font-weight: 800; font-size: 16px; cursor: pointer;
                display: flex; align-items: center; justify-content: center; gap: 14px;
                box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
                transition: all 0.3s ease;
                text-transform: uppercase;
            }
 
            .btn-passkey:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4); }
 
            .terminal-input-row {
                display: flex; align-items: center; gap: 12px; font-size: 14px;
                border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;
                margin-top: 2rem;
            }
            .prompt { color: var(--amber); font-weight: 800; }
        </style>
 
        @if($isTerminalMode)
            <div class="terminal-card">
                <div class="terminal-header">
                    <div><span style="color:var(--green)">●</span> CONSORTIUM_{{ strtoupper($currentPanel) }}_NODE_V1.0</div>
                    <div>IDENTITY_ANCHOR: ACTIVE</div>
                </div>
                <div class="terminal-body">
                    <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text);">Sovereign Terminal</h1>
                    <p style="color: var(--text-dim); font-size: 13px; margin-bottom: 2rem;">Подпишите интент доступа к узлу {{ $currentPanel }}</p>
 
                    <div style="background: rgba(0,0,0,0.3); border: 1px dashed rgba(245, 158, 11, 0.3); border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
                        <div style="font-size: 12px; line-height: 1.6; color: #94a3b8;">
                            Я, владелец суверенной идентичности, подтверждаю намерение получить доступ к среде {{ strtoupper($currentPanel) }}.
                            <br><br>
                            <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px; font-size: 0.75rem; color: var(--amber); border: 1px solid rgba(245,158,11,0.2);">
                                [CHALLENGE]: <span id="visible-challenge">GENERATING...</span>
                            </div>
                        </div>
                    </div>
 
                    <button type="button" class="btn-passkey" onclick="authenticateWithPasskey()" style="border-radius: 8px; height: 56px; background: var(--amber); color: #000; box-shadow: none;">
                         SIGN_AND_ACCESS
                    </button>
 
                    <div class="terminal-input-row">
                        <span class="prompt">visitor@consortium:~$</span>
                        <input type="text" id="terminal-input" onpaste="handlePaste(event)" autofocus style="background:transparent; border:none; color:var(--amber); outline:none; font-family:inherit; font-size:inherit; flex-grow:1;" placeholder="paste signature and press Enter...">
                        <button type="button" onclick="manualSubmit()" style="background: rgba(245,158,11,0.2); border: 1px solid var(--amber); color: var(--amber); padding: 4px 12px; border-radius: 4px; font-size: 10px; cursor: pointer; font-weight: 800;">SEND_PROOF</button>
                    </div>
                </div>
            </div>
        @else
            <div class="classic-card">
                <div style="margin-bottom: 3rem;">
                    <div style="display: inline-block; padding: 0.5rem 1rem; background: rgba(16, 185, 129, 0.1); color: var(--green); border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1.5rem;">
                        Sovereign Identity Access
                    </div>
                    <h1 style="font-size: 2.2rem; font-weight: 900; margin-bottom: 1rem; letter-spacing: -0.03em; color: #fff;">Consortium Entry</h1>
                    <p style="color: var(--text-dim); font-size: 15px; line-height: 1.6;">Вход в систему управления через криптографический ключ.</p>
                </div>
 
                <x-passkeys::authenticate>
                    <button type="button" class="btn-passkey" onclick="authenticateWithPasskey()">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3m0 0a10.003 10.003 0 0110 10c0 2.49-.913 4.766-2.427 6.51l-.822.948m-4.751-2.455A9.054 9.054 0 0015.5 15.5m-5 0A9.054 9.054 0 0115.5 15.5M15.5 15.5l.054.09" />
                        </svg>
                        ВХОД ЧЕРЕЗ PASSKEY
                    </button>
                </x-passkeys::authenticate>
 
                <div style="margin-top: 2.5rem; font-size: 12px; color: var(--text-dim);">
                    Узел: <span style="color: #fff; font-weight: 600;">{{ strtoupper($currentPanel) }}.MEANLY.TEST</span>
                </div>
 
                <div style="margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem;">
                    <a href="{{ request()->fullUrlWithQuery(['mode' => 'terminal']) }}" style="font-size: 11px; color: var(--amber); text-decoration: none; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; opacity: 0.7; transition: opacity 0.2s;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                        > Switch to Terminal Mode
                    </a>
                </div>
            </div>
        @endif
 
        <form id="terminal-login-form" method="POST" action="/terminal/authenticate" style="display: none;">
            @csrf
            <input type="hidden" name="challenge" id="terminal-challenge-input">
            <input type="hidden" name="signature" id="terminal-signature-input">
            <input type="hidden" name="email" value="admin@admin.com">
        </form>
 
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
 
            window.addEventListener('DOMContentLoaded', async () => {
                await refreshEntropy();
                @if($isTerminalMode)
                const termInput = document.getElementById('terminal-input');
                termInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') manualSubmit(); });
                @endif
            });
 
            async function refreshEntropy() {
                try {
                    const response = await fetch('/passkeys/authentication-options', { credentials: 'include', headers: { 'Accept': 'application/json' } });
                    currentOptions = await response.json();
                    if (typeof currentOptions === 'string') currentOptions = JSON.parse(currentOptions);
                    intentEntropy = { ts: new Date().toISOString(), ip: "{{ request()->ip() }}", challenge: currentOptions.challenge };
                    const vis = document.getElementById('visible-challenge');
                    if (vis) vis.innerText = currentOptions.challenge;
                } catch (e) { console.error('Entropy Error:', e); }
            }
 
            async function authenticateWithPasskey() {
                try {
                    if (!currentOptions) await refreshEntropy();
                    const response = await startAuthentication({ optionsJSON: currentOptions });
                    document.getElementById('entropy-input').value = JSON.stringify(intentEntropy);
                    document.getElementById('response-input').value = JSON.stringify(response);
                    document.getElementById('passkey-login-form').submit();
                } catch (e) { alert('Auth Error: ' + e.message); }
            }
 
            function handlePaste(e) { setTimeout(() => manualSubmit(), 100); }
 
            function manualSubmit() {
                const input = document.getElementById('terminal-input').value.trim();
                const vis = document.getElementById('visible-challenge');
                if (input.length > 32) {
                    document.getElementById('terminal-signature-input').value = input;
                    document.getElementById('terminal-challenge-input').value = vis ? vis.innerText : '';
                    document.getElementById('terminal-login-form').submit();
                }
            }
        </script>
    </div>
</x-filament-panels::page.simple>
