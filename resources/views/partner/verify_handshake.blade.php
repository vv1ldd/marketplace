<!DOCTYPE html>
<html lang="ru">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подтверждение профиля — Meanly</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap');
        
        :root {
            --brand-primary: #f53003;
            --brand-bg: #050505;
            --brand-card: #0a0a0a;
            --brand-text: #ffffff;
            --brand-subtext: #888888;
            --brand-border: #1a1a1a;
            --btn-bg: #111111;
            --btn-hover: #1a1a1a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--brand-bg);
            color: var(--brand-text);
            font-family: 'Instrument Sans', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            padding: 3rem 2.5rem;
            border-radius: 12px;
            text-align: center;
            width: 100%;
            max-width: 520px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }

        .logo-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--brand-text);
            letter-spacing: -0.02em;
            margin-bottom: 2rem;
        }

        .logo-mark { width: 12px; height: 12px; background: var(--brand-primary); border-radius: 3px; }

        .auth-title { font-size: 24px; font-weight: 600; color: var(--brand-text); margin-bottom: 0.75rem; letter-spacing: -0.02em; }
        .auth-subtitle { font-size: 14px; color: var(--brand-subtext); line-height: 1.5; margin-bottom: 2rem; }

        /* 📜 Sovereignty Contract / Declaration Style */
        .contract-box {
            background: #000;
            border: 1px solid var(--brand-border);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: left;
            font-size: 13px;
            line-height: 1.6;
            color: #ccc;
            margin-bottom: 1.5rem;
            max-height: 160px;
            overflow-y: auto;
            border-left: 3px solid var(--brand-primary);
        }

        .contract-title {
            font-size: 10px;
            font-weight: 700;
            color: var(--brand-primary);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        /* 💻 Monospaced JSON Blueprint Style */
        .blueprint-console {
            background: #020202;
            border: 1px solid var(--brand-border);
            border-radius: 8px;
            padding: 1.25rem;
            text-align: left;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11px;
            line-height: 1.5;
            color: #00ff66;
            margin-bottom: 2rem;
            max-height: 180px;
            overflow-y: auto;
            box-shadow: inset 0 0 10px rgba(0,255,102,0.05);
        }

        .blueprint-title {
            font-size: 9px;
            color: #555;
            border-bottom: 1px solid var(--brand-border);
            padding-bottom: 6px;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .blueprint-console pre { margin: 0; white-space: pre-wrap; font-family: inherit; }

        /* 🕹️ Custom Biometric Trigger Button */
        .btn-submit {
            width: 100%;
            height: 48px;
            background-color: var(--brand-primary);
            color: var(--brand-text);
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(245, 48, 3, 0.2);
            letter-spacing: 0.02em;
            margin-bottom: 1rem;
        }

        .btn-submit:hover:not(:disabled) {
            background-color: #ff3e10;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(245, 48, 3, 0.3);
        }

        .btn-submit:disabled {
            background-color: #333;
            color: #666;
            cursor: not-allowed;
            box-shadow: none;
        }

        /* 🟢 Status Pill */
        .status-pill {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 13px;
            color: var(--brand-subtext);
            margin: 1.5rem 0;
            padding: 8px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--brand-border);
            transition: all 0.3s ease;
        }

        .status-pill.active {
            color: #fff;
            background: rgba(245, 48, 3, 0.05);
            border-color: rgba(245, 48, 3, 0.2);
        }

        .pulse-indicator {
            width: 8px;
            height: 8px;
            background-color: var(--brand-subtext);
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .status-pill.active .pulse-indicator {
            background-color: var(--brand-primary);
            animation: statusPulse 1.5s infinite ease-in-out;
        }

        @keyframes statusPulse {
            0% { transform: scale(0.9); opacity: 0.6; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.6; }
        }

        .footer-brand {
            margin-top: 2.5rem;
            font-size: 11px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        .footer-brand {
            margin-top: 2.5rem;
            font-size: 11px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
        }

        /* 🎨 MULTI-SKIN THEME OVERRIDES */

        /* 🌟 Theme 1: Partner (Modern Glassmorphic Gold/Amber) */
        body[data-theme="partner"] {
            --brand-primary: #ff9f0a;
            --brand-bg: #060608;
            --brand-card: rgba(14, 14, 18, 0.65);
            --brand-text: #ffffff;
            --brand-subtext: #9a9ab0;
            --brand-border: rgba(255, 255, 255, 0.04);
            background: #060608 !important;
        }
        body[data-theme="partner"] .logo-mark {
            background: #ff9f0a !important;
            box-shadow: 0 0 20px rgba(255, 159, 10, 0.5) !important;
        }
        body[data-theme="partner"] .auth-card {
            background: rgba(14, 14, 18, 0.65) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        body[data-theme="partner"] .contract-box {
            background: rgba(10, 13, 24, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            border-left: 3px solid #ff9f0a !important;
        }
        body[data-theme="partner"] .contract-title {
            color: #ff9f0a !important;
        }
        body[data-theme="partner"] details {
            background: rgba(10, 13, 24, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
        }
        body[data-theme="partner"] .btn-submit {
            background: #ff9f0a !important;
            color: #000000 !important;
            box-shadow: 0 4px 12px rgba(255, 159, 10, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="partner"] .btn-submit:hover:not(:disabled) {
            background: #ffa825 !important;
            box-shadow: 0 6px 20px rgba(255, 159, 10, 0.4) !important;
        }
        body[data-theme="partner"] .status-pill {
            background: rgba(255, 255, 255, 0.01) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
        }
        body[data-theme="partner"] .status-pill.active {
            color: #ffffff !important;
            background: rgba(255, 159, 10, 0.05) !important;
            border-color: rgba(255, 159, 10, 0.2) !important;
        }
        body[data-theme="partner"] .status-pill.active .pulse-indicator {
            background-color: #ff9f0a !important;
        }

        /* 🚩 Theme 2: Consortium Flagship (Flat Dark Neobrutalism) */
        body[data-theme="consortium"] {
            --brand-primary: #f53003;
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            background: #030303 !important;
        }
        body[data-theme="consortium"] .logo-mark {
            background: #f53003 !important;
            box-shadow: 0 0 15px rgba(245, 48, 3, 0.5) !important;
        }
        body[data-theme="consortium"] .auth-card {
            background: #090909 !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        body[data-theme="consortium"] .btn-submit {
            background: #f53003 !important;
            color: #ffffff !important;
        }

        /* ⚡ Theme 3: Consortium Retro (Light Neo-Brutalism - Stark & Bold) */
        body[data-theme="retro"] {
            --brand-primary: #7c3aed;
            --brand-bg: #f3f4f6;
            --brand-card: #ffffff;
            --brand-text: #000000;
            --brand-subtext: #4b5563;
            --brand-border: #000000;
            background: #f3f4f6 !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .logo-mark {
            background: #7c3aed !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .auth-card {
            border: 3px solid #000000 !important;
            box-shadow: 8px 8px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .auth-title {
            color: #000000 !important;
        }
        body[data-theme="retro"] .contract-box {
            background: #f9fafb !important;
            border: 2px solid #000000 !important;
            border-left: 6px solid var(--brand-primary) !important;
            color: #1f2937 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .contract-title {
            color: var(--brand-primary) !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] details {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            color: #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] details summary {
            color: #4b5563 !important;
        }
        body[data-theme="retro"] details pre {
            color: #000000 !important;
        }
        body[data-theme="retro"] .status-pill {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            color: #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .status-pill.active {
            background: rgba(124, 58, 237, 0.05) !important;
            border-color: var(--brand-primary) !important;
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .status-pill.active .pulse-indicator {
            background-color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .btn-submit {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }

        /* 🍃 Theme 4: Nordic (Warm Eco / Scandinavian Minimalist) */
        body[data-theme="nordic"] {
            --brand-primary: #1e3f20;
            --brand-bg: #faf7f2;
            --brand-card: #ffffff;
            --brand-text: #2b2b2b;
            --brand-subtext: #6e706a;
            --brand-border: #e6dfd5;
            background: #faf7f2 !important;
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .logo-mark {
            background: #1e3f20 !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 10px rgba(30, 63, 32, 0.15) !important;
        }
        body[data-theme="nordic"] .auth-card {
            background: #ffffff !important;
            border: 1px solid #e6dfd5 !important;
            box-shadow: 0 20px 40px rgba(30, 63, 32, 0.05) !important;
        }
        body[data-theme="nordic"] .contract-box {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
            border-left: 3px solid #1e3f20 !important;
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .contract-title {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] details {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .btn-submit {
            background: #1e3f20 !important;
            color: #faf7f2 !important;
            box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="nordic"] .btn-submit:hover:not(:disabled) {
            background: #152d16 !important;
            box-shadow: 0 6px 20px rgba(30, 63, 32, 0.4) !important;
        }
        body[data-theme="nordic"] .status-pill {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .status-pill.active {
            color: #1e3f20 !important;
            background: rgba(30, 63, 32, 0.05) !important;
            border-color: rgba(30, 63, 32, 0.2) !important;
        }
        body[data-theme="nordic"] .status-pill.active .pulse-indicator {
            background-color: #1e3f20 !important;
        }

        /* 🟣 Theme 5: Synthwave (Retrofuturism / Pink-Purple Neon) */
        body[data-theme="synthwave"] {
            --brand-primary: #ff007f;
            --brand-bg: #120e2e;
            --brand-card: #1c1543;
            --brand-text: #ffffff;
            --brand-subtext: #8e89c5;
            --brand-border: rgba(255, 0, 127, 0.15);
            background: #120e2e !important;
        }
        body[data-theme="synthwave"] .logo-mark {
            background: linear-gradient(135deg, #ff007f, #00f0ff) !important;
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.5) !important;
        }
        body[data-theme="synthwave"] .auth-card {
            background: #1c1543 !important;
            border: 1px solid rgba(255, 0, 127, 0.15) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        body[data-theme="synthwave"] .contract-box {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
            border-left: 3px solid #ff007f !important;
        }
        body[data-theme="synthwave"] .contract-title {
            color: #ff007f !important;
        }
        body[data-theme="synthwave"] details {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .btn-submit {
            background: #ff007f !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255, 0, 127, 0.4) !important;
            font-weight: 800;
        }
        body[data-theme="synthwave"] .btn-submit:hover:not(:disabled) {
            background: #e60072 !important;
            box-shadow: 0 6px 20px rgba(255, 0, 127, 0.6) !important;
        }
        body[data-theme="synthwave"] .status-pill {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .status-pill.active {
            color: #ffffff !important;
            background: rgba(255, 0, 127, 0.1) !important;
            border-color: rgba(255, 0, 127, 0.3) !important;
        }
        body[data-theme="synthwave"] .status-pill.active .pulse-indicator {
            background-color: #ff007f !important;
        }

        /* 🏁 Theme 6: Carbon (High-Performance Stealth / Motorsport Yellow) */
        body[data-theme="carbon"] {
            --brand-primary: #facc15;
            --brand-bg: #070708;
            --brand-card: #101012;
            --brand-text: #ffffff;
            --brand-subtext: #8b8b92;
            --brand-border: #222226;
            background: #070708 !important;
        }
        body[data-theme="carbon"] .logo-mark {
            background: #facc15 !important;
            border-radius: 4px !important;
            box-shadow: 0 0 20px rgba(250, 204, 21, 0.5) !important;
        }
        body[data-theme="carbon"] .auth-card {
            background: #101012 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8) !important;
        }
        body[data-theme="carbon"] .contract-box {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-left: 4px solid #facc15 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .contract-title {
            color: #facc15 !important;
        }
        body[data-theme="carbon"] details {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .btn-submit {
            background: #facc15 !important;
            color: #000000 !important;
            box-shadow: 0 4px 12px rgba(250, 204, 21, 0.2) !important;
            border-radius: 4px !important;
            font-weight: 900;
        }
        body[data-theme="carbon"] .btn-submit:hover:not(:disabled) {
            background: #eab308 !important;
            box-shadow: 0 6px 20px rgba(250, 204, 21, 0.4) !important;
        }
        body[data-theme="carbon"] .status-pill {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .status-pill.active {
            color: #ffffff !important;
            background: rgba(250, 204, 21, 0.05) !important;
            border-color: rgba(250, 204, 21, 0.2) !important;
        }
        body[data-theme="carbon"] .status-pill.active .pulse-indicator {
            background-color: #facc15 !important;
        }

        /* 🎉 Sons Birthday / Holiday (Albiceleste) Overrides */
        body[data-holiday="sons-birthday"] {
            --brand-primary: #74acdf !important;
            --brand-border-hover: rgba(116, 172, 223, 0.45) !important;
        }
        body[data-holiday="sons-birthday"] .logo-mark {
            background: linear-gradient(135deg, #74acdf 0%, #ffffff 100%) !important;
            box-shadow: 0 0 20px rgba(116, 172, 223, 0.6) !important;
        }
        body[data-holiday="sons-birthday"] .btn-submit {
            background: #74acdf !important;
            color: #000000 !important;
            border-color: #74acdf !important;
            box-shadow: 0 4px 15px rgba(116, 172, 223, 0.4) !important;
        }
        body[data-holiday="sons-birthday"] .btn-submit:hover:not(:disabled) {
            background: #85bded !important;
            box-shadow: 0 6px 20px rgba(116, 172, 223, 0.6) !important;
        }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>
@include('partials.theme-sync-body')
 
<div class="auth-card">
    <div class="logo-header">
        <div class="logo-mark"></div>
        MEANLY
    </div>

    <h1 class="auth-title">Подтверждение почты</h1>
    <p class="auth-subtitle">
        Для завершения регистрации подтвердите ваш почтовый ящик и настройте безопасный вход по TouchID/FaceID.
    </p>

    <!-- 📜 The Sovereignty Declaration Contract -->
    <div class="contract-box">
        <div class="contract-title">Соглашение о безопасном доступе</div>
        Настоящим подтверждается владение адресом <strong>{{ $email }}</strong> и настройка аппаратного ключа доступа для мгновенной и безопасной авторизации в личном кабинете.
    </div>

    <!-- 💻 The Cryptographic Intent Blueprint Console (details block) -->
    <details style="text-align: left; background: #020202; border: 1px solid var(--brand-border); border-radius: 8px; padding: 1.25rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 11px; line-height: 1.5; color: #00ff66; margin-bottom: 2rem; box-shadow: inset 0 0 10px rgba(0,255,102,0.05); cursor: pointer;">
        <summary style="font-size: 9px; color: #555; text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em; outline: none; list-style: none;">
            [ Показать технические детали (L1 Blueprint) ]
        </summary>
        <div style="margin-top: 10px; border-top: 1px solid var(--brand-border); padding-top: 10px;">
            <pre style="margin: 0; white-space: pre-wrap; font-family: inherit;">{{ $rawBlueprintJson }}</pre>
        </div>
    </details>

    <!-- 🟢 Status Indicator -->
    <div id="status-pill" class="status-pill">
        <div class="pulse-indicator"></div>
        <span id="status-text">Ожидание подтверждения...</span>
    </div>

    <!-- 🕹️ Activation Button -->
    <button type="button" id="enroll-btn" class="btn-submit" onclick="initiateEnrollment()">
        Создать ключ доступа
    </button>

    <div class="footer-brand">
        {{ (session('partner_registration')['is_b2b'] ?? false) ? 'PARTNER.MEANLY.SYSTEMS' : 'CLIENT.MEANLY.SYSTEMS' }}
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@7.2.0/dist/bundle/index.umd.min.js"></script>
<script>
    const { startRegistration } = SimpleWebAuthnBrowser;
    const enrollBtn = document.getElementById('enroll-btn');
    const statusPill = document.getElementById('status-pill');
    const statusText = document.getElementById('status-text');

    async function initiateEnrollment() {
        if (enrollBtn.disabled) return;

        enrollBtn.disabled = true;
        statusPill.classList.add('active');
        statusText.innerText = "Подготовка к настройке входа...";

        try {
            // 1. Fetch options for passkey registration
            const optionsRes = await fetch('{{ route('partner.register.options') }}', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ email: '{{ $email }}' })
            });

            const data = await optionsRes.json();
            if (data.error) throw new Error(data.error);

            statusText.innerText = "Приложите палец к TouchID...";

            // 2. Perform simplewebauthn browser registration
            const attestationResponse = await startRegistration(data.options);

            statusText.innerText = "Привязка ключа безопасности...";

            // 3. Send attestation response back to anchor L1 identity
            const saveRes = await fetch('{{ route('partner.register.identity.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': data.new_csrf || '{{ csrf_token() }}'
                },
                body: JSON.stringify(attestationResponse)
            });

            const saveResult = await saveRes.json();
            if (saveResult.error) throw new Error(saveResult.error);

            statusText.innerText = "Email подтвержден! Ключ безопасности привязан!";
            
            const isB2b = {{ (session('partner_registration')['is_b2b'] ?? false) ? 'true' : 'false' }};
            setTimeout(() => {
                window.location.href = isB2b ? '{{ route('partner.register.offer') }}' : '/vault';
            }, 1000);

        } catch (err) {
            console.error(err);
            statusPill.classList.remove('active');
            
            let userFriendlyMessage = err.message;
            if (err.name === 'NotAllowedError' || err.message.toLowerCase().includes('user denied') || err.message.toLowerCase().includes('not allowed') || err.message.toLowerCase().includes('abort')) {
                userFriendlyMessage = "Действие отменено пользователем 🛡️";
            }
            
            statusText.innerText = "Прервано: " + userFriendlyMessage;
            enrollBtn.disabled = false;
        }
    }
</script>
</body>
</html>
