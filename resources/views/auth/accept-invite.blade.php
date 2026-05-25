<!DOCTYPE html>
<html lang="ru">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Принять приглашение | MEANLY</title>

    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
    <script>
        window.startAuthentication = SimpleWebAuthnBrowser.startAuthentication;
        window.startRegistration = SimpleWebAuthnBrowser.startRegistration;
        window.browserSupportsWebAuthn = SimpleWebAuthnBrowser.browserSupportsWebAuthn;
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap">

    <style>
        :root {
            --brand-primary: #f53003;
            --brand-primary-glow: rgba(245, 48, 3, 0.4);
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
        }

        * { box-sizing: border-box; }

        body, html {
            margin: 0; padding: 0;
            width: 100vw; min-height: 100vh;
            background-color: var(--brand-bg);
            overflow-x: hidden;
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }

        .invite-wrapper {
            position: relative;
            width: 100vw; min-height: 100vh;
            background-color: var(--brand-bg);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            padding: 2rem 1rem;
        }

        .ambient-glows {
            position: absolute; top: 0; left: 0; right: 0; height: 100vh;
            pointer-events: none; z-index: 0; overflow: hidden;
        }
        .glow-1 {
            position: absolute; top: -10%; left: 20%; width: 60vw; height: 60vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.05) 0%, transparent 70%);
            filter: blur(80px);
        }
        .glow-2 {
            position: absolute; top: 40%; right: -5%; width: 40vw; height: 40vw;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.04) 0%, transparent 70%);
            filter: blur(100px);
        }

        .invite-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            padding: 3rem 2.5rem;
            border-radius: 16px;
            width: 100%; max-width: 480px;
            position: relative; z-index: 10;
            box-shadow: 0 24px 60px rgba(0,0,0,0.7);
        }

        .logo-header {
            display: flex; align-items: center; gap: 0.6rem;
            font-weight: 800; font-size: 1.05rem;
            color: var(--brand-text); letter-spacing: -0.02em;
            margin-bottom: 2.5rem;
            text-decoration: none;
        }
        .logo-mark {
            width: 11px; height: 11px;
            background: var(--brand-primary); border-radius: 3px;
            box-shadow: 0 0 14px rgba(245, 48, 3, 0.5);
        }

        /* Invite source badge */
        .invite-badge {
            display: flex; align-items: center; gap: 12px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 12px; padding: 14px 16px;
            margin-bottom: 2rem;
        }
        .invite-badge-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(245, 48, 3, 0.1);
            border: 1px solid rgba(245, 48, 3, 0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .invite-badge-content { flex: 1; }
        .invite-badge-label {
            font-size: 10px; font-weight: 700; color: #555;
            text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 3px;
        }
        .invite-badge-company {
            font-size: 14px; font-weight: 700; color: #fff;
        }
        .invite-badge-role {
            display: inline-block;
            background: rgba(245, 48, 3, 0.12);
            border: 1px solid rgba(245, 48, 3, 0.22);
            color: #f53003; font-size: 11px; font-weight: 700;
            padding: 2px 9px; border-radius: 100px; margin-top: 4px;
        }

        .card-title {
            font-size: 22px; font-weight: 700;
            color: var(--brand-text); letter-spacing: -0.03em;
            margin-bottom: 0.5rem;
        }
        .card-subtitle {
            font-size: 14px; color: var(--brand-subtext); line-height: 1.55;
            margin-bottom: 2rem;
        }

        /* Form fields */
        .field-label {
            font-size: 11px; font-weight: 700; color: #555;
            text-transform: uppercase; letter-spacing: 0.05em;
            margin-bottom: 6px; display: block;
        }
        .field-input {
            width: 100% !important; height: 44px !important;
            padding: 0 1rem !important;
            background: rgba(255,255,255,0.02) !important;
            border: 1px solid rgba(255,255,255,0.06) !important;
            border-radius: 10px !important;
            color: #fff !important; font-family: inherit !important;
            font-size: 14px !important; outline: none !important;
            transition: border-color 0.2s !important;
        }
        .field-input:focus {
            border-color: var(--brand-primary) !important;
            background: rgba(255,255,255,0.03) !important;
            box-shadow: 0 0 0 1px var(--brand-primary) !important;
        }
        .field-input:disabled {
            opacity: 0.5 !important; cursor: not-allowed !important;
        }
        .field-group { margin-bottom: 1rem; }

        /* CTA button */
        .btn-primary {
            width: 100%; height: 48px;
            background: var(--brand-primary);
            color: #fff; border: none; border-radius: 10px;
            font-family: inherit; font-size: 14px; font-weight: 700;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            gap: 10px; transition: all 0.2s; margin-top: 0.5rem;
            box-shadow: 0 4px 16px var(--brand-primary-glow);
        }
        .btn-primary:hover:not(:disabled) {
            filter: brightness(1.1); transform: translateY(-1px);
            box-shadow: 0 6px 24px var(--brand-primary-glow);
        }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Steps */
        .steps-indicator {
            display: flex; gap: 6px; margin-bottom: 2rem;
        }
        .step-dot {
            height: 3px; flex: 1; border-radius: 100px;
            background: rgba(255,255,255,0.07);
            transition: background 0.3s;
        }
        .step-dot.active { background: var(--brand-primary); }
        .step-dot.done { background: rgba(245, 48, 3, 0.35); }

        /* Error state */
        .error-card {
            text-align: center; padding: 2rem 1.5rem;
        }
        .error-icon { font-size: 40px; margin-bottom: 1rem; }
        .error-title { font-size: 18px; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .error-body { font-size: 14px; color: var(--brand-subtext); }

        /* Success state */
        .success-state { text-align: center; padding: 1rem 0; }
        .success-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(74, 222, 128, 0.1);
            border: 1px solid rgba(74, 222, 128, 0.2);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem; font-size: 28px;
        }
        .success-title { font-size: 20px; font-weight: 700; color: #fff; margin-bottom: 0.5rem; }
        .success-body { font-size: 14px; color: var(--brand-subtext); margin-bottom: 1.5rem; }

        /* Error message */
        .msg-error {
            font-size: 12px; color: #ef4444;
            margin-top: 6px; display: block;
        }

        .footer-brand {
            margin-top: 1.5rem; font-size: 10px; color: #2a2a2a;
            text-transform: uppercase; letter-spacing: 0.08em;
            font-weight: 800; text-align: center; z-index: 10;
        }
    </style>
</head>
<body>
@include('partials.theme-sync-body')

<div class="invite-wrapper">
    <div class="ambient-glows">
        <div class="glow-1"></div>
        <div class="glow-2"></div>
    </div>

    <div class="invite-card">
        <a href="/" class="logo-header">
            <div class="logo-mark"></div>
            MEANLY
        </a>

        @if($error)
            {{-- Invalid / expired invite --}}
            <div class="error-card">
                <div class="error-icon">🔗</div>
                <div class="error-title">Ссылка недействительна</div>
                <div class="error-body">{{ $error }}</div>
            </div>
        @else
            {{-- Valid invite --}}
            <div x-data="inviteFlow()" x-init="init()">

                {{-- Steps indicator --}}
                <div class="steps-indicator">
                    <div class="step-dot" :class="{ active: step === 1, done: step > 1 }"></div>
                    <div class="step-dot" :class="{ active: step === 2, done: step > 2 }"></div>
                    <div class="step-dot" :class="{ active: step === 3 }"></div>
                </div>

                {{-- Invite source badge --}}
                <div class="invite-badge">
                    <div class="invite-badge-icon">🏢</div>
                    <div class="invite-badge-content">
                        <div class="invite-badge-label">Приглашение от</div>
                        <div class="invite-badge-company">{{ $partnerName }}</div>
                        <div class="invite-badge-role">{{ $roleLabel }}</div>
                    </div>
                </div>

                {{-- Step 1: Enter email --}}
                <div x-show="step === 1" x-cloak>
                    <div class="card-title">Добро пожаловать!</div>
                    <p class="card-subtitle">
                        Введите ваш email, чтобы создать защищённый аккаунт и принять приглашение.
                    </p>

                    <div class="field-group">
                        <label class="field-label">Email</label>
                        <input
                            type="email"
                            x-model="email"
                            class="field-input"
                            placeholder="you@example.com"
                            @keydown.enter="step1Submit()"
                            {{ isset($inviteeEmail) && $inviteeEmail ? 'value="'.$inviteeEmail.'" readonly' : '' }}
                        >
                        <span class="msg-error" x-show="errors.email" x-text="errors.email"></span>
                    </div>
                    <div class="field-group">
                        <label class="field-label">Ваше имя (необязательно)</label>
                        <input
                            type="text"
                            x-model="name"
                            class="field-input"
                            placeholder="Иван Иванов"
                            @keydown.enter="step1Submit()"
                            value="{{ $inviteeName ?? '' }}"
                        >
                    </div>

                    <button class="btn-primary" @click="step1Submit()" :disabled="loading">
                        <span x-show="!loading">Продолжить →</span>
                        <span x-show="loading">Загрузка...</span>
                    </button>

                    <span class="msg-error" x-show="errors.global" x-text="errors.global"></span>
                </div>

                {{-- Step 2: Create Passkey --}}
                <div x-show="step === 2" x-cloak>
                    <div class="card-title">Создайте Passkey 🛡️</div>
                    <p class="card-subtitle">
                        Ваш аккаунт защищён криптографическим ключом — без пароля.
                        Нажмите кнопку и подтвердите через Face ID, Touch ID или PIN.
                    </p>

                    <button class="btn-primary" @click="step2CreatePasskey()" :disabled="loading">
                        <span x-show="!loading">🔑 Создать Passkey</span>
                        <span x-show="loading">Ожидание подтверждения...</span>
                    </button>

                    <span class="msg-error" x-show="errors.global" x-text="errors.global"></span>
                </div>

                {{-- Step 3: Success --}}
                <div x-show="step === 3" x-cloak class="success-state">
                    <div class="success-icon">🎉</div>
                    <div class="success-title">Вы в команде!</div>
                    <p class="success-body">Аккаунт создан, Passkey зарегистрирован.<br>Перенаправляем в рабочее пространство...</p>
                    <button class="btn-primary" @click="window.location.href='/partner'">
                        Перейти в панель →
                    </button>
                </div>

            </div>
        @endif
    </div>

    <div class="footer-brand">MEANLY.SYSTEMS</div>
</div>

<script>
function inviteFlow() {
    return {
        step: 1,
        loading: false,
        email: '{{ $inviteeEmail ?? '' }}',
        name: '{{ $inviteeName ?? '' }}',
        csrfToken: document.querySelector('meta[name="csrf-token"]').content,
        errors: {},
        passkeyAttestation: null,

        init() {
            if (!window.browserSupportsWebAuthn || !window.browserSupportsWebAuthn()) {
                this.errors.global = 'Ваш браузер не поддерживает Passkey. Обновите браузер или используйте Safari / Chrome.';
            }
        },

        async step1Submit() {
            this.errors = {};
            if (!this.email || !this.email.includes('@')) {
                this.errors.email = 'Введите корректный email';
                return;
            }

            this.loading = true;
            try {
                const res = await fetch('/invite/{{ $token }}/options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ email: this.email, name: this.name }),
                });

                const data = await res.json();

                if (!res.ok) {
                    this.errors.global = data.error || 'Ошибка сервера.';
                    return;
                }

                // Update CSRF token
                if (data.new_csrf) {
                    this.csrfToken = data.new_csrf;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', data.new_csrf);
                }

                // Trigger WebAuthn registration
                const options = data.options;
                options.user.id = Uint8Array.from(atob(options.user.id), c => c.charCodeAt(0));
                options.challenge = Uint8Array.from(atob(options.challenge.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
                if (options.excludeCredentials) {
                    options.excludeCredentials = options.excludeCredentials.map(c => ({
                        ...c,
                        id: Uint8Array.from(atob(c.id.replace(/-/g, '+').replace(/_/g, '/')), ch => ch.charCodeAt(0))
                    }));
                }

                this.step = 2;

            } catch (err) {
                this.errors.global = 'Сетевая ошибка: ' + err.message;
            } finally {
                this.loading = false;
            }
        },

        async step2CreatePasskey() {
            this.errors = {};
            this.loading = true;

            try {
                // Re-fetch options for WebAuthn (they were already stored in session)
                const optionsRes = await fetch('/invite/{{ $token }}/options', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ email: this.email, name: this.name }),
                });

                const optionsData = await optionsRes.json();
                if (!optionsRes.ok) throw new Error(optionsData.error || 'Ошибка получения параметров.');

                if (optionsData.new_csrf) {
                    this.csrfToken = optionsData.new_csrf;
                    document.querySelector('meta[name="csrf-token"]').setAttribute('content', optionsData.new_csrf);
                }

                const opts = optionsData.options;

                // Perform WebAuthn registration
                const attestation = await window.startRegistration({ optionsJSON: opts });

                // Submit attestation to server
                const submitRes = await fetch('/invite/{{ $token }}/accept', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ passkey_attestation: JSON.stringify(attestation) }),
                });

                const submitData = await submitRes.json();

                if (!submitRes.ok) {
                    this.errors.global = submitData.error || 'Ошибка регистрации.';
                    return;
                }

                this.step = 3;

                // Auto-redirect after 2.5s
                setTimeout(() => {
                    window.location.href = submitData.redirect || '/partner';
                }, 2500);

            } catch (err) {
                if (err.name === 'NotAllowedError') {
                    this.errors.global = 'Действие отменено. Попробуйте ещё раз.';
                } else {
                    this.errors.global = err.message || 'Ошибка при создании ключа.';
                }
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
</body>
</html>
