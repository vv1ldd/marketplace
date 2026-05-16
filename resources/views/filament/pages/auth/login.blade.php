<x-filament-panels::page.simple>
    <div id="sovereign-auth-root" class="sovereign-auth-wrapper">
        <style>
            /* 🌑 Unified Sovereign Identity Styles */
            :root {
                --amber: #f59e0b;
                --bg: #080b10;
                --bg-card: #0f1420;
                --text: #f1f5f9;
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

            .auth-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                width: 100%;
                max-width: 440px;
                padding: 2rem;
                font-family: 'Inter', sans-serif;
            }

            .auth-card {
                background: var(--bg-card);
                border: 1px solid rgba(255,255,255,0.07);
                padding: 3.5rem 2.5rem;
                border-radius: 24px;
                width: 100%;
                box-shadow: 0 30px 60px rgba(0,0,0,0.5);
                text-align: center;
                position: relative;
            }

            .auth-header h1 { 
                font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; 
                margin-bottom: 0.75rem; color: var(--text);
            }
            .auth-header p { 
                color: #94a3b8; font-size: 0.9rem; line-height: 1.5;
                margin-bottom: 2.5rem; 
            }

            .btn-passkey {
                width: 100%;
                height: 58px;
                background: var(--amber);
                color: #000;
                border: none;
                border-radius: 12px;
                font-weight: 800;
                font-size: 1.05rem;
                cursor: pointer;
                transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }
            .btn-passkey:hover {
                background: #fcd34d;
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(245,158,11,0.2);
            }

            .status-meta {
                margin-top: 2rem;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            .status-dot {
                width: 6px; height: 6px;
                background: var(--amber);
                border-radius: 50%;
                animation: sovereign-pulse 2s infinite;
            }
            .status-text {
                font-size: 10px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.25em;
                color: #64748b;
            }

            @keyframes sovereign-pulse {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.4; transform: scale(1.1); }
            }

            @keyframes sweep {
                0% { transform: translateX(-100%); }
                100% { transform: translateX(100%); }
            }
        </style>

        <script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
        <script>
            // 🛡️ Global state for options
            let currentOptions = null;
            let intentEntropy = {};

            const { startAuthentication } = SimpleWebAuthnBrowser;

            // 🚀 Initialize Entropy on Load
            window.addEventListener('DOMContentLoaded', async () => {
                await refreshEntropy();
            });

            async function refreshEntropy() {
                try {
                    const response = await fetch('/passkeys/authentication-options', {
                        credentials: 'include',
                        headers: { 'Accept': 'application/json' }
                    });
                    
                    let options = await response.json();
                    if (typeof options === 'string') options = JSON.parse(options);
                    currentOptions = options;

                    // 🛠️ Capture high-precision entropy package
                    intentEntropy = {
                        ts: document.getElementById('current-ts').innerText,
                        ip: document.getElementById('anchor-ip').innerText,
                        ua: document.getElementById('anchor-ua').innerText,
                        challenge: options.challenge
                    };

                    // 🛠️ Update UI
                    document.getElementById('anchor-challenge').innerText = options.challenge.substring(0, 16) + '...';

                    // 📜 Calculate visual hash
                    const intentText = document.querySelector('.intent-document').innerText;
                    const encoder = new TextEncoder();
                    const data = encoder.encode(intentText + JSON.stringify(intentEntropy));
                    const hashBuffer = await crypto.subtle.digest('SHA-256', data);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
                    
                    document.getElementById('intent-id-display').innerText = hashHex.substring(0, 12).toUpperCase();
                    document.getElementById('doc-hash').innerText = 'INTENT_HASH: ' + hashHex.substring(0, 32).toUpperCase() + '...';
                    document.getElementById('doc-hash-container').style.display = 'flex';
                } catch (e) {
                    console.error('🛑 Entropy Failure:', e);
                }
            }

            // 🛡️ Biometric Identity Handshake
            async function authenticateWithPasskey(remember = false) {
                try {
                    console.log('🚀 Starting Passkey Auth Flow (v13.3.0)...');
                    
                    if (!currentOptions) {
                        await refreshEntropy();
                    }

                    // 🔑 CRYPTOGRAPHIC SIGNATURE
                    const startAuthenticationResponse = await startAuthentication({ optionsJSON: currentOptions });
                    console.log('✅ Assertion success:', startAuthenticationResponse);

                    // 🚀 Populate and Submit
                    const form = document.getElementById('passkey-login-form');
                    document.getElementById('remember-input').value = remember;
                    document.getElementById('entropy-input').value = JSON.stringify(intentEntropy);
                    document.getElementById('response-input').value = JSON.stringify(startAuthenticationResponse);
                    form.submit();
                } catch (e) {
                    console.error('❌ Passkey Critical Failure:', e);
                    alert('Критическая ошибка ключа: ' + e.message);
                }
            }
        </script>
        
        <form id="passkey-login-form" method="POST" action="/passkeys/authenticate" style="display: none;">
            @csrf
            <input type="hidden" name="remember" id="remember-input">
            <input type="hidden" name="intent_entropy" id="entropy-input">
            <input type="hidden" name="start_authentication_response" id="response-input">
        </form>

        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <h1>Sovereign Entry</h1>
                    <p id="auth-subtitle">Сформируйте криптографическую подпись для подтверждения входа</p>
                </div>

                <!-- 📜 Intent Document Preview -->
                <div class="intent-document" style="background: #ffffff03; border: 1px solid #ffffff07; border-radius: 16px; padding: 2rem; margin-bottom: 2.5rem; text-align: left; box-shadow: inset 0 0 40px rgba(0,0,0,0.2);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 1rem;">
                        <div>
                            <div style="font-weight: 900; color: #f1f5f9; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.1em;">Документ-намерение</div>
                            <div style="font-size: 0.65rem; color: #64748b; margin-top: 2px;">ID: <span id="intent-id-display">GENERATING...</span></div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.65rem; color: #64748b; text-transform: uppercase;">Системное время</div>
                            <div style="font-size: 0.7rem; color: #94a3b8; font-weight: 700;" id="current-ts">{{ now()->format('H:i:s.v') }}</div>
                        </div>
                    </div>
                    
                    <div style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.6; font-family: 'JetBrains Mono', monospace;">
                        Я, владелец суверенной идентичности, подтверждаю свое намерение получить доступ к операционной среде Terminal Consortium. 
                        <br><br>
                        <!-- 🔗 Contextual Anchors -->
                        <div style="background: rgba(0,0,0,0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-size: 0.7rem; color: #64748b; border: 1px solid rgba(255,255,255,0.03);">
                            <div style="margin-bottom: 4px;"><span style="color: #475569;">NETWORK_ORIGIN:</span> <span id="anchor-ip">{{ request()->ip() }}</span></div>
                            <div style="margin-bottom: 4px;"><span style="color: #475569;">AGENT_FINGERPRINT:</span> <span id="anchor-ua">{{ substr(request()->userAgent(), 0, 40) }}...</span></div>
                            <div style="margin-bottom: 4px;"><span style="color: #475569;">ENTROPY_CHALLENGE:</span> <span id="anchor-challenge">PENDING...</span></div>
                        </div>

                        Настоящим я подтверждаю, что:
                        <ul style="margin: 0.5rem 0; padding-left: 1.2rem; color: #94a3b8;">
                            <li>Владею приватным ключом (ED25519)</li>
                            <li>Принимаю условия оферты</li>
                            <li>Действую от лица зарегистрированного юр. лица</li>
                        </ul>
                    </div>

                    <div id="doc-hash-container" style="margin-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; display: flex; align-items: center; gap: 10px; color: #10b981; font-weight: 700; font-size: 0.7rem; text-transform: uppercase;">
                         <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                         <span id="doc-hash">ГОТОВ К ПОДПИСАНИЮ</span>
                    </div>
                </div>

                <!-- 🔑 Passkey Primary Action (Signature Ceremony) -->
                <div id="passkey-section">
                    <x-passkeys::authenticate>
                        <button type="button" class="btn-passkey" onclick="authenticateWithPasskey()" style="position: relative; overflow: hidden;">
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.05), transparent); animation: sweep 3s infinite;"></div>
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A10.003 10.003 0 0012 3m0 0a10.003 10.003 0 0110 10c0 2.49-.913 4.766-2.427 6.51l-.822.948m-4.751-2.455A9.054 9.054 0 0015.5 15.5m-5 0A9.054 9.054 0 0115.5 15.5M15.5 15.5l.054.09" />
                            </svg>
                            Подписать и войти
                        </button>
                    </x-passkeys::authenticate>

                    <div class="status-meta" style="justify-content: center; margin-top: 1.5rem;">
                        <div class="status-dot" style="background: #10b981;"></div>
                        <span class="status-text" style="color: #10b981;">L1 Identity Anchoring Active</span>
                    </div>

                    <div style="margin-top: 1rem; font-size: 0.65rem; color: #475569; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;">
                        Доказательство владения открытым ключом (ED25519)
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page.simple>
