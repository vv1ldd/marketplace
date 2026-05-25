<!DOCTYPE html>
<html lang="ru">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Подключение Passkey — Meanly</title>
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
            padding: 4rem 3rem;
            border-radius: 12px;
            text-align: center;
            width: 100%;
            max-width: 480px;
            position: relative;
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
            margin-bottom: 3rem;
        }

        /* 👆 Biometric Touchpoint & Animations */
        .biometric-touchpoint {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 3rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #0d0d0d;
            border: 1px solid var(--brand-border);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .biometric-touchpoint::before {
            content: '';
            position: absolute;
            top: -8px; left: -8px; right: -8px; bottom: -8px;
            border: 1px dashed rgba(245, 48, 3, 0.3);
            border-radius: 50%;
            animation: pulse-ring 4s linear infinite;
        }

        .biometric-touchpoint:hover {
            border-color: var(--brand-primary);
            box-shadow: 0 0 20px rgba(245, 48, 3, 0.15);
            transform: scale(1.05);
        }

        .fingerprint-icon {
            width: 64px;
            height: 64px;
            color: var(--brand-primary);
            transition: all 0.3s;
        }

        .biometric-touchpoint.scanning .fingerprint-icon {
            animation: breathe-fingerprint 1.5s ease-in-out infinite alternate;
        }

        .scanner-bar {
            position: absolute;
            left: 20px;
            width: calc(100% - 40px);
            height: 2px;
            background: var(--brand-primary);
            box-shadow: 0 0 8px var(--brand-primary);
            opacity: 0;
            top: 20px;
            pointer-events: none;
        }

        .biometric-touchpoint.scanning .scanner-bar {
            opacity: 1;
            animation: scan-action 2s linear infinite;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #0d0d0d;
            border: 1px solid var(--brand-border);
            padding: 8px 16px;
            border-radius: 20px;
            margin-bottom: 2rem;
            font-size: 12px;
            color: #666;
            transition: all 0.3s;
        }

        .status-pill.active {
            color: var(--brand-primary);
            border-color: rgba(245, 48, 3, 0.2);
            background: rgba(245, 48, 3, 0.03);
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: #222;
            border-radius: 50%;
        }

        .status-pill.active .status-dot {
            background: var(--brand-primary);
            box-shadow: 0 0 8px var(--brand-primary);
            animation: blink-dot 1s infinite alternate;
        }
 
        .btn-submit {
            width: 100%;
            height: 48px;
            background-color: var(--cursor-btn-bg) !important;
            color: var(--brand-text) !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
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
            opacity: 0.5;
            cursor: not-allowed;
        }
 
        .footer-brand {
            margin-top: 3rem;
            font-size: 11px;
            color: #222;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            text-align: center;
        }

        @keyframes pulse-ring {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes scan-action {
            0% { top: 20px; }
            50% { top: 100px; }
            100% { top: 20px; }
        }

        @keyframes breathe-fingerprint {
            0% { transform: scale(1); filter: drop-shadow(0 0 2px rgba(245, 48, 3, 0.2)); }
            100% { transform: scale(0.95); filter: drop-shadow(0 0 10px rgba(245, 48, 3, 0.6)); }
        }

        @keyframes blink-dot {
            0% { opacity: 0.4; }
            100% { opacity: 1; }
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
        body[data-theme="partner"] .biometric-touchpoint {
            background: rgba(10, 13, 24, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
        }
        body[data-theme="partner"] .biometric-touchpoint::before {
            border: 1px dashed rgba(255, 159, 10, 0.3) !important;
        }
        body[data-theme="partner"] .biometric-touchpoint:hover {
            border-color: #ff9f0a !important;
            box-shadow: 0 0 20px rgba(255, 159, 10, 0.15) !important;
        }
        body[data-theme="partner"] .fingerprint-icon {
            color: #ff9f0a !important;
        }
        body[data-theme="partner"] .scanner-bar {
            background: #ff9f0a !important;
            box-shadow: 0 0 8px #ff9f0a !important;
        }
        body[data-theme="partner"] .status-pill {
            background: rgba(255, 255, 255, 0.01) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
        }
        body[data-theme="partner"] .status-pill.active {
            color: #ff9f0a !important;
            border-color: rgba(255, 159, 10, 0.2) !important;
            background: rgba(255, 159, 10, 0.03) !important;
        }
        body[data-theme="partner"] .status-pill.active .status-dot {
            background: #ff9f0a !important;
            box-shadow: 0 0 8px #ff9f0a !important;
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
        body[data-theme="retro"] .biometric-touchpoint {
            background: #f9fafb !important;
            border: 2px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .biometric-touchpoint::before {
            border: 2px dashed #000000 !important;
        }
        body[data-theme="retro"] .biometric-touchpoint:hover {
            border-color: var(--brand-primary) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .fingerprint-icon {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .scanner-bar {
            background: var(--brand-primary) !important;
            box-shadow: 0 0 8px var(--brand-primary) !important;
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
        body[data-theme="retro"] .status-pill.active .status-dot {
            background: var(--brand-primary) !important;
            box-shadow: 0 0 8px var(--brand-primary) !important;
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
        body[data-theme="nordic"] .biometric-touchpoint {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .biometric-touchpoint::before {
            border: 1px dashed rgba(30, 63, 32, 0.3) !important;
        }
        body[data-theme="nordic"] .biometric-touchpoint:hover {
            border-color: #1e3f20 !important;
            box-shadow: 0 0 20px rgba(30, 63, 32, 0.15) !important;
        }
        body[data-theme="nordic"] .fingerprint-icon {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .scanner-bar {
            background: #1e3f20 !important;
            box-shadow: 0 0 8px #1e3f20 !important;
        }
        body[data-theme="nordic"] .status-pill {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .status-pill.active {
            color: #1e3f20 !important;
            border-color: rgba(30, 63, 32, 0.2) !important;
            background: rgba(30, 63, 32, 0.03) !important;
        }
        body[data-theme="nordic"] .status-pill.active .status-dot {
            background: #1e3f20 !important;
            box-shadow: 0 0 8px #1e3f20 !important;
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
        body[data-theme="synthwave"] .biometric-touchpoint {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .biometric-touchpoint::before {
            border: 1px dashed rgba(255, 0, 127, 0.3) !important;
        }
        body[data-theme="synthwave"] .biometric-touchpoint:hover {
            border-color: #ff007f !important;
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.15) !important;
        }
        body[data-theme="synthwave"] .fingerprint-icon {
            color: #ff007f !important;
        }
        body[data-theme="synthwave"] .scanner-bar {
            background: #ff007f !important;
            box-shadow: 0 0 8px #ff007f !important;
        }
        body[data-theme="synthwave"] .status-pill {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .status-pill.active {
            color: #ff007f !important;
            border-color: rgba(255, 0, 127, 0.3) !important;
            background: rgba(255, 0, 127, 0.03) !important;
        }
        body[data-theme="synthwave"] .status-pill.active .status-dot {
            background: #ff007f !important;
            box-shadow: 0 0 8px #ff007f !important;
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
        body[data-theme="carbon"] .biometric-touchpoint {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .biometric-touchpoint::before {
            border: 2px dashed rgba(250, 204, 21, 0.3) !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .biometric-touchpoint:hover {
            border-color: #facc15 !important;
            box-shadow: 0 0 20px rgba(250, 204, 21, 0.15) !important;
        }
        body[data-theme="carbon"] .fingerprint-icon {
            color: #facc15 !important;
        }
        body[data-theme="carbon"] .scanner-bar {
            background: #facc15 !important;
            box-shadow: 0 0 8px #facc15 !important;
        }
        body[data-theme="carbon"] .status-pill {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .status-pill.active {
            color: #facc15 !important;
            border-color: rgba(250, 204, 21, 0.2) !important;
            background: rgba(250, 204, 21, 0.03) !important;
        }
        body[data-theme="carbon"] .status-pill.active .status-dot {
            background: #facc15 !important;
            box-shadow: 0 0 8px #facc15 !important;
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
            border-radius: 0px !important;
        }

        /* --- 🗓️ Sovereign Holiday Calendar — Full Overrides --- */
        /* Q1: January */
        body[data-holiday="new-year"]          { --brand-primary: #059669 !important; }
        /* Q1: February */
        body[data-holiday="valentine"]         { --brand-primary: #e11d48 !important; }
        body[data-holiday="defender-day"]      { --brand-primary: #64748b !important; }
        /* Q1: March */
        body[data-holiday="womens-day"]        { --brand-primary: #eab308 !important; }
        /* Q2: April */
        body[data-holiday="cosmonautics-day"]  { --brand-primary: #6366f1 !important; }
        body[data-holiday="doctor-day"]        { --brand-primary: #06b6d4 !important; }
        /* Q2: May */
        body[data-holiday="may-day"]           { --brand-primary: #ef4444 !important; }
        body[data-holiday="victory-day"]       { --brand-primary: #dc2626 !important; }
        body[data-holiday="orchid-day"]        { --brand-primary: #d946ef !important; }
        body[data-holiday="sons-birthday"]     { --brand-primary: #74acdf !important; }
        /* Q2: June */
        body[data-holiday="russia-day"]        { --brand-primary: #3b82f6 !important; }
        /* Q3: August */
        body[data-holiday="babel-library"]     { --brand-primary: #d97706 !important; }
        /* Q4: October */
        body[data-holiday="little-prince"]     { --brand-primary: #e11d48 !important; }
        /* Q4: November */
        body[data-holiday="national-unity"]    { --brand-primary: #f97316 !important; }
        /* Q4: December */
        body[data-holiday="constitution-day"]  { --brand-primary: #8b5cf6 !important; }
        body[data-holiday="new-year-eve"]      { --brand-primary: #a78bfa !important; }

        /* Dynamic Holiday Styles — apply to all events */
        body[data-holiday] .logo-mark {
            background: var(--brand-primary) !important;
            box-shadow: 0 0 20px var(--brand-primary) !important;
        }
        body[data-holiday] .btn-submit {
            background: var(--brand-primary) !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 4px 15px var(--brand-primary) !important;
        }
        body[data-holiday] .btn-submit:hover:not(:disabled) {
            opacity: 0.9 !important;
            box-shadow: 0 6px 20px var(--brand-primary) !important;
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
        <h1 class="auth-title">Подключение Passkey</h1>
        <p class="auth-subtitle">
            Создайте защищенный ключ входа с помощью Face ID, Touch ID или PIN вашего устройства.
        </p>

        <div class="status-pill" id="status-pill">
            <div class="status-dot"></div>
            <span id="status-text">Ожидание прикосновения</span>
        </div>

        <div class="biometric-touchpoint" id="touch-trigger" onclick="initiateEnrollment()">
            <div class="scanner-bar"></div>
            <svg class="fingerprint-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2a10 10 0 0 0-10 10c0 1 .1 2 .3 3"/>
                <path d="M12 10a2 2 0 0 0-2 2c0 3 2 3 2 6"/>
                <path d="M14 13.1a2 2 0 0 0-.1-2.1c-.5-.8-1.5-1-2.3-.5-.4.2-.6.6-.6 1.1"/>
                <path d="M2 12a10 10 0 0 1 18.3-5.5"/>
                <path d="M12 22a10 10 0 0 0 8-4"/>
                <path d="M8 12a4 4 0 0 1 8 0c0 1.5-.8 2.5-1.5 3.5-.8 1-1.5 2-1.5 3.5"/>
                <path d="M18 15a8 8 0 0 0-1.8-6.5"/>
                <path d="M6 15a8 8 0 0 1 1.8-6.5"/>
            </svg>
        </div>
 
        <button type="button" id="enroll-btn" class="btn-submit" onclick="initiateEnrollment()">
            Активировать биометрию 🛡️
        </button>

        <!-- 📱 QR Code fall-back for mobile enrollment -->
        <div class="qr-wrapper" style="margin-top: 2.5rem; padding-top: 2rem; border-top: 1px solid var(--brand-border); display: flex; align-items: center; justify-content: center; gap: 1.5rem; text-align: left;">
            <div class="qr-code-box" style="background: #fff; padding: 6px; border-radius: 8px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=110x110&data={{ urlencode($qrUrl) }}" alt="QR Code" style="width: 110px; height: 110px; display: block;">
            </div>
            <div class="qr-info">
                <h4 style="font-size: 13px; font-weight: 600; color: var(--brand-text); margin-bottom: 4px;">Активировать с телефона</h4>
                <p style="font-size: 11px; color: var(--brand-subtext); line-height: 1.4;">Отсканируйте код камерой смартфона, чтобы мгновенно перенести сессию и использовать TouchID / FaceID.</p>
            </div>
        </div>
    </div>
 
    <div class="footer-brand">
        {{ (session('partner_registration')['is_b2b'] ?? false) ? 'PARTNER.MEANLY.SYSTEMS' : 'CLIENT.MEANLY.SYSTEMS' }}
    </div>
</div>
 
<script src="https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@7.2.0/dist/bundle/index.umd.min.js"></script>
<script>
    const { startRegistration } = SimpleWebAuthnBrowser;
    const touchTrigger = document.getElementById('touch-trigger');
    const enrollBtn = document.getElementById('enroll-btn');
    const statusPill = document.getElementById('status-pill');
    const statusText = document.getElementById('status-text');

    async function initiateEnrollment() {
        if (enrollBtn.disabled) return;

        enrollBtn.disabled = true;
        touchTrigger.classList.add('scanning');
        statusPill.classList.add('active');
        statusText.innerText = "Подготовка защищенного входа...";

        try {
            // 1. Fetch options for passkey registration
            const optionsRes = await fetch('{{ route('partner.register.options') }}', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ email: '{{ session('partner_registration')['email'] ?? '' }}' })
            });

            const data = await optionsRes.json();
            if (data.error) throw new Error(data.error);

            statusText.innerText = "Сканируйте отпечаток Touch ID...";

            // 2. Perform simplewebauthn browser registration
            const attestationResponse = await startRegistration(data.options);

            statusText.innerText = "Привязка ключа к профилю...";

            // 3. Send attestation response back to anchor L1 identity
            const saveRes = await fetch('{{ route('partner.register.identity.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(attestationResponse)
            });

            const saveResult = await saveRes.json();
            if (saveResult.error) throw new Error(saveResult.error);

            statusText.innerText = "Passkey подключен!";
            
            const isB2b = {{ (session('partner_registration')['is_b2b'] ?? false) ? 'true' : 'false' }};
            setTimeout(() => {
                window.location.href = isB2b ? '{{ route('partner.register.offer') }}' : '/cabinet';
            }, 1000);

        } catch (err) {
            console.error(err);
            statusPill.classList.remove('active');
            touchTrigger.classList.remove('scanning');
            
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
