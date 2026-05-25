<!DOCTYPE html>
<html lang="ru">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оферта — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #f53003;
            --brand-bg: #050505;
            --brand-card: #0a0a0a;
            --brand-text: #ffffff;
            --brand-subtext: #888888;
            --brand-border: #1a1a1a;
            --cursor-btn-bg: #111111;
            --cursor-btn-hover: #1a1a1a;
        }
 
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif !important;
            background-color: var(--brand-bg);
            color: var(--brand-text);
            line-height: 1.6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
        }
 
        .auth-wrapper {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
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
            margin-bottom: 3.5rem;
        }
 
        .logo-mark {
            width: 12px;
            height: 12px;
            background: var(--brand-primary);
            border-radius: 3px;
        }
 
        .auth-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            padding: 3.5rem 3rem;
            border-radius: 12px;
            width: 100%;
            max-width: 640px;
            position: relative;
            text-align: center;
        }
 
        .auth-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--brand-text);
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }
 
        .auth-subtitle {
            font-size: 14px;
            color: var(--brand-subtext);
            line-height: 1.5;
            margin-bottom: 2.5rem;
        }
 
        .agreement-box {
            background: #0d0d0d;
            border: 1px solid var(--brand-border);
            border-radius: 8px;
            padding: 2rem;
            height: 380px;
            overflow-y: auto;
            text-align: left;
            font-size: 13px;
            line-height: 1.7;
            color: #ccc;
            margin-bottom: 2rem;
        }
 
        .agreement-box::-webkit-scrollbar { width: 4px; }
        .agreement-box::-webkit-scrollbar-thumb { background: #222; border-radius: 10px; }
 
        .compliance-alert {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            background: rgba(16, 185, 129, 0.03);
            border: 1px solid rgba(16, 185, 129, 0.1);
            padding: 1.25rem;
            border-radius: 10px;
            text-align: left;
            margin-bottom: 2rem;
        }
 
        .check-mark {
            width: 20px;
            height: 20px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            flex-shrink: 0;
        }
 
        .alert-content h4 {
            font-size: 12px;
            font-weight: 800;
            color: #10b981;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
 
        .alert-content p {
            font-size: 12px;
            color: #555;
        }
 
        .confirmation-label {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            text-align: left;
            margin-bottom: 2.5rem;
            font-size: 13px;
            color: var(--brand-subtext);
            line-height: 1.5;
        }
 
        .confirmation-label input {
            margin-top: 4px;
            accent-color: var(--brand-primary);
            flex-shrink: 0;
        }
 
        .btn-submit {
            width: 100%;
            height: 52px;
            background-color: var(--cursor-btn-bg) !important;
            color: var(--brand-text) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 15px !important;
            border: 1px solid var(--brand-border) !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            transition: all 0.2s ease !important;
        }
 
        .btn-submit:hover:not(:disabled) {
            background-color: var(--cursor-btn-hover) !important;
            border-color: #333 !important;
        }
 
        .btn-submit:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
 
        .qr-section {
            margin-top: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            padding-top: 2rem;
            border-top: 1px solid var(--brand-border);
        }
 
        .qr-code {
            width: 80px;
            height: 80px;
            background: white;
            padding: 6px;
            border-radius: 6px;
            filter: invert(0.9);
        }
 
        .qr-text {
            font-size: 11px;
            color: #444;
            text-align: left;
            max-width: 240px;
        }
 
        .footer-brand {
            margin-top: 3rem;
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
        body[data-theme="partner"] .agreement-box {
            background: rgba(10, 13, 24, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            color: #ccc !important;
        }
        body[data-theme="partner"] .agreement-box::-webkit-scrollbar-thumb {
            background: rgba(255, 159, 10, 0.3) !important;
        }
        body[data-theme="partner"] .btn-submit {
            background: #ff9f0a !important;
            color: #000000 !important;
            border-color: #ff9f0a !important;
            box-shadow: 0 4px 12px rgba(255, 159, 10, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="partner"] .btn-submit:hover:not(:disabled) {
            background: #ffa825 !important;
            box-shadow: 0 6px 20px rgba(255, 159, 10, 0.4) !important;
        }
        body[data-theme="partner"] .confirmation-label input {
            accent-color: #ff9f0a !important;
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
            background: var(--brand-primary) !important;
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
        body[data-theme="retro"] .agreement-box {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            color: #1f2937 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .agreement-box::-webkit-scrollbar-thumb {
            background: #000000 !important;
        }
        body[data-theme="retro"] .agreement-box h3 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .compliance-alert {
            background: rgba(124, 58, 237, 0.05) !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .check-mark {
            background: var(--brand-primary) !important;
            border: 1px solid #000000 !important;
        }
        body[data-theme="retro"] .alert-content h4 {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .alert-content p {
            color: #4b5563 !important;
        }
        body[data-theme="retro"] .confirmation-label {
            color: #000000 !important;
        }
        body[data-theme="retro"] .confirmation-label input {
            accent-color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .btn-submit {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .btn-submit:hover:not(:disabled) {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
            background-color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .qr-section {
            border-top: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .qr-code {
            border: 2px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
            filter: none !important;
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
        body[data-theme="nordic"] .agreement-box {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .agreement-box::-webkit-scrollbar-thumb {
            background: #1e3f20 !important;
        }
        body[data-theme="nordic"] .agreement-box h3 {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .compliance-alert {
            background: rgba(30, 63, 32, 0.05) !important;
            border: 1px solid rgba(30, 63, 32, 0.1) !important;
            border-radius: 10px !important;
        }
        body[data-theme="nordic"] .check-mark {
            background: #1e3f20 !important;
        }
        body[data-theme="nordic"] .alert-content h4 {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .alert-content p {
            color: #6e706a !important;
        }
        body[data-theme="nordic"] .confirmation-label {
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .confirmation-label input {
            accent-color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .btn-submit {
            background: #1e3f20 !important;
            color: #faf7f2 !important;
            border-color: #1e3f20 !important;
            box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="nordic"] .btn-submit:hover:not(:disabled) {
            background: #152d16 !important;
            box-shadow: 0 6px 20px rgba(30, 63, 32, 0.4) !important;
        }
        body[data-theme="nordic"] .qr-section {
            border-top: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .qr-code {
            border: 1px solid #e6dfd5 !important;
            box-shadow: none !important;
            filter: none !important;
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
            background: #ff007f !important;
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.5) !important;
        }
        body[data-theme="synthwave"] .auth-card {
            background: #1c1543 !important;
            border: 1px solid rgba(255, 0, 127, 0.15) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        body[data-theme="synthwave"] .agreement-box {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
            color: #ffffff !important;
        }
        body[data-theme="synthwave"] .agreement-box::-webkit-scrollbar-thumb {
            background: #ff007f !important;
        }
        body[data-theme="synthwave"] .agreement-box h3 {
            color: #ffffff !important;
        }
        body[data-theme="synthwave"] .compliance-alert {
            background: rgba(255, 0, 127, 0.05) !important;
            border: 1px solid rgba(255, 0, 127, 0.1) !important;
        }
        body[data-theme="synthwave"] .check-mark {
            background: #ff007f !important;
        }
        body[data-theme="synthwave"] .alert-content h4 {
            color: #ff007f !important;
        }
        body[data-theme="synthwave"] .alert-content p {
            color: #8e89c5 !important;
        }
        body[data-theme="synthwave"] .confirmation-label {
            color: #ffffff !important;
        }
        body[data-theme="synthwave"] .confirmation-label input {
            accent-color: #ff007f !important;
        }
        body[data-theme="synthwave"] .btn-submit {
            background: #ff007f !important;
            color: #ffffff !important;
            border-color: #ff007f !important;
            box-shadow: 0 4px 12px rgba(255, 0, 127, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="synthwave"] .btn-submit:hover:not(:disabled) {
            background: #e60072 !important;
            box-shadow: 0 6px 20px rgba(255, 0, 127, 0.4) !important;
        }
        body[data-theme="synthwave"] .qr-section {
            border-top: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .qr-code {
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
            filter: invert(0.9) !important;
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
        body[data-theme="carbon"] .agreement-box {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            color: #ffffff !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .agreement-box::-webkit-scrollbar-thumb {
            background: #facc15 !important;
        }
        body[data-theme="carbon"] .agreement-box h3 {
            color: #ffffff !important;
        }
        body[data-theme="carbon"] .compliance-alert {
            background: rgba(250, 204, 21, 0.03) !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .check-mark {
            background: #facc15 !important;
            color: #000000 !important;
        }
        body[data-theme="carbon"] .alert-content h4 {
            color: #facc15 !important;
        }
        body[data-theme="carbon"] .alert-content p {
            color: #8b8b92 !important;
        }
        body[data-theme="carbon"] .confirmation-label {
            color: #ffffff !important;
        }
        body[data-theme="carbon"] .confirmation-label input {
            accent-color: #facc15 !important;
        }
        body[data-theme="carbon"] .btn-submit {
            background: #facc15 !important;
            color: #000000 !important;
            border-color: #facc15 !important;
            box-shadow: 0 4px 12px rgba(250, 204, 21, 0.2) !important;
            border-radius: 4px !important;
            font-weight: 900;
        }
        body[data-theme="carbon"] .btn-submit:hover:not(:disabled) {
            background: #eab308 !important;
            box-shadow: 0 6px 20px rgba(250, 204, 21, 0.4) !important;
        }
        body[data-theme="carbon"] .qr-section {
            border-top: 2px solid #222226 !important;
        }
        body[data-theme="carbon"] .qr-code {
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
            filter: none !important;
        }
            color: #000000 !important;
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
<div class="auth-wrapper">
    <div class="logo-header">
        <div class="logo-mark"></div>
        MEANLY
    </div>
 
    <div class="auth-card">
        <h1 class="auth-title">{{ $agreementTitle ?? 'Публичная оферта' }}</h1>
        <p class="auth-subtitle">
            Вы почти у цели. Подтвердите согласие с правилами работы в экосистеме.
        </p>
 
        <div class="agreement-box">
            <h3 style="color: #fff; margin-bottom: 1rem;">{{ $agreementTitle ?? 'Договор на оказание услуг по размещению Товарных предложений' }}</h3>
            <p style="color: #666; font-size: 11px; margin-bottom: 1.5rem;">Дата размещения: {{ \App\Models\Agreement::where('is_active', true)->latest('published_at')->first()?->published_at->format('d.m.Y') ?? date('d.m.Y') }} г.</p>
            <div style="white-space: pre-wrap;">{{ $agreementText }}</div>
        </div>
 
        <div class="compliance-alert">
            <div class="check-mark">✓</div>
            <div class="alert-content">
                <h4>Полномочия авторизованы</h4>
                <p>Ваша личность подтверждена на основе предоставленных данных.</p>
            </div>
        </div>
 
        <label class="confirmation-label">
            <input type="checkbox" id="legal-confirm" onchange="toggleSignButton()">
            <span>
                @if(($agreementType ?? null) === 'b2b_npd')
                    Я подтверждаю статус самозанятого, применение НПД и принимаю условия Оферты от имени <strong>{{ session('partner_registration')['legal_name'] ?? 'самозанятого профиля' }}</strong>.
                @else
                    Я подтверждаю свои полномочия на подписание документов от имени <strong>{{ session('partner_registration')['legal_name'] ?? 'организации' }}</strong> и принимаю условия Оферты.
                @endif
            </span>
        </label>
 
        <div id="offer-actions">
            <button type="button" id="sign-offer-btn" class="btn-submit" disabled>
                Подписать оферту ✍️
            </button>
            <p id="status-msg" style="margin-top: 1rem; font-size: 12px; color: var(--brand-primary); display: none;"></p>
        </div>
 
        <div class="qr-section">
            <div class="qr-code">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(url()->current()) }}" alt="QR Code" style="width: 100%; height: 100%;">
            </div>
            <div class="qr-text">
                <strong>Подписание через FaceID / TouchID</strong><br>
                Отсканируйте код камерой смартфона, чтобы использовать биометрию для подписи.
            </div>
        </div>
    </div>
 
    <div class="footer-brand">
        PARTNER.MEANLY.SYSTEMS
    </div>
</div>
 
<script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
<script>
    const { startAuthentication } = SimpleWebAuthnBrowser;
    const signBtn = document.getElementById('sign-offer-btn');
    const legalConfirm = document.getElementById('legal-confirm');
    const statusMsg = document.getElementById('status-msg');
 
    function toggleSignButton() {
        signBtn.disabled = !legalConfirm.checked;
    }
 
    signBtn.addEventListener('click', async () => {
        const optionsRaw = @json($signingOptions);
        let options = typeof optionsRaw === 'string' ? JSON.parse(optionsRaw) : optionsRaw;
        
        signBtn.disabled = true;
        signBtn.innerText = "Подписание... 🛡️";
        statusMsg.style.display = 'block';
        statusMsg.innerText = "Формирование криптографического интента...";
 
        try {
            if (!options || !options.challenge) {
                throw new Error('Контекст Passkey-подписи устарел. Обновите страницу и попробуйте снова.');
            }
            options.rpId = window.location.hostname;
            const assertionResponse = await startAuthentication({ optionsJSON: options });
            const signRes = await fetch("{{ route('partner.register.agreement.sign') }}", {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ assertion: assertionResponse })
            });
 
            const result = await signRes.json();
            if (result.success) {
                statusMsg.innerText = "Оферта подписана! Переходим в терминал...";
                setTimeout(() => window.location.href = result.redirect, 1000);
            } else {
                throw new Error(result.error || 'Ошибка при финализации подписи');
            }
        } catch (error) {
            signBtn.disabled = false;
            signBtn.innerText = "Попробовать снова ✍️";
            statusMsg.innerText = "Ошибка: " + error.message;
        }
    });
</script>
</body>
</html>
