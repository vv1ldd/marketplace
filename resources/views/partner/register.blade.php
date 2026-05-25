<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль для юрлица — Meanly Business</title>
    
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand-primary: #f53003;
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            --brand-border-hover: rgba(255, 255, 255, 0.15);
            --glass-bg: rgba(9, 9, 9, 0.7);
            --glass-blur: 24px;
        }

        html { scroll-behavior: smooth; background: var(--brand-bg); }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--brand-bg);
            color: var(--brand-text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Glows */
        .ambient-glows {
            position: absolute;
            top: 0; left: 0; right: 0; height: 100vh;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-1 {
            position: absolute; top: -10%; left: 20%; width: 60vw; height: 60vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.04) 0%, rgba(0,0,0,0) 70%);
            filter: blur(80px);
        }
        .glow-2 {
            position: absolute; top: 30%; right: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.03) 0%, rgba(0,0,0,0) 75%);
            filter: blur(100px);
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            padding: 1.25rem 2rem;
            display: flex; align-items: center; justify-content: center;
            background: rgba(3, 3, 3, 0.7);
            backdrop-filter: blur(var(--glass-blur));
            border-bottom: 1px solid var(--brand-border);
        }
        .nav-container {
            width: 100%;
            max-width: 1200px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { 
            font-size: 1.3rem; 
            font-weight: 900; 
            letter-spacing: -0.04em; 
            color: var(--brand-text); 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 0.6rem; 
        }
        .logo-mark { 
            width: 12px; 
            height: 12px; 
            background: var(--brand-primary); 
            border-radius: 3px; 
            box-shadow: 0 0 15px rgba(245, 48, 3, 0.5);
        }
        .logo-sub {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.25em;
            color: var(--brand-primary);
            text-transform: uppercase;
            border-left: 1px solid var(--brand-border);
            padding-left: 0.6rem;
            margin-left: 0.2rem;
        }

        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8rem 1.5rem 4rem;
            position: relative;
            z-index: 10;
        }

        .auth-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            padding: 3rem;
            border-radius: 24px;
            width: 100%;
            max-width: 480px;
            z-index: 1;
            position: relative;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6);
        }

        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h1 { 
            font-size: 1.8rem; 
            font-weight: 900; 
            letter-spacing: -0.03em; 
            text-transform: uppercase;
            margin-bottom: 0.5rem; 
        }
        .auth-header p { color: var(--brand-subtext); font-size: 0.9rem; line-height: 1.5; }

        .form-group { margin-bottom: 1.5rem; }
        .form-label { 
            display: block; font-size: 11px; font-weight: 700; 
            color: var(--brand-subtext); text-transform: uppercase;
            letter-spacing: 0.05em; margin-bottom: 0.5rem; 
        }
        .form-input {
            width: 100%;
            background: #020202;
            border: 1px solid var(--brand-border);
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13.5px;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--brand-primary);
        }

        .btn-submit {
            width: 100%;
            background: var(--brand-primary);
            color: #fff;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(245, 48, 3, 0.4);
        }

        /* Radio Selector for signing roles */
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .radio-option {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            cursor: pointer;
            padding: 1rem;
            border-radius: 12px;
            background: rgba(255,255,255,0.01);
            border: 1px solid var(--brand-border);
            transition: all 0.2s;
        }
        .radio-option:hover {
            border-color: var(--brand-border-hover);
            background: rgba(255,255,255,0.03);
        }
        .radio-option input[type="radio"] {
            accent-color: var(--brand-primary);
            margin-top: 3px;
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
            --brand-border-hover: rgba(255, 159, 10, 0.2);
            --glass-bg: rgba(11, 11, 14, 0.7);
            background: #060608 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="partner"] .glow-1 {
            background: radial-gradient(circle, rgba(255, 159, 10, 0.09) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="partner"] .glow-2 {
            background: radial-gradient(circle, rgba(139, 92, 246, 0.07) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="partner"] .logo-mark {
            background: #ff9f0a !important;
            box-shadow: 0 0 20px rgba(255, 159, 10, 0.5) !important;
        }
        body[data-theme="partner"] .logo-sub {
            color: #ff9f0a !important;
        }
        body[data-theme="partner"] nav {
            background: rgba(6, 6, 8, 0.7) !important;
            border-bottom-color: rgba(255, 255, 255, 0.04) !important;
        }
        body[data-theme="partner"] .auth-card {
            background: rgba(14, 14, 18, 0.65) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
            backdrop-filter: blur(24px) !important;
            -webkit-backdrop-filter: blur(24px) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        body[data-theme="partner"] .form-input {
            background: rgba(10, 13, 24, 0.55) !important;
            border: 1px solid rgba(255, 255, 255, 0.06) !important;
            color: #ffffff !important;
        }
        body[data-theme="partner"] .form-input:focus {
            border-color: #ff9f0a !important;
        }
        body[data-theme="partner"] .btn-submit {
            background: #ff9f0a !important;
            color: #000000 !important;
            box-shadow: 0 4px 12px rgba(255, 159, 10, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="partner"] .btn-submit:hover {
            box-shadow: 0 6px 20px rgba(255, 159, 10, 0.4) !important;
        }
        body[data-theme="partner"] .radio-option {
            background: rgba(255, 255, 255, 0.01) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
        }
        body[data-theme="partner"] .radio-option input[type="radio"] {
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
            --brand-border-hover: rgba(255, 255, 255, 0.15);
            --glass-bg: rgba(9, 9, 9, 0.7);
            background: #030303 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="consortium"] .glow-1 {
            background: radial-gradient(circle, rgba(245, 48, 3, 0.04) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="consortium"] .logo-mark {
            background: #f53003 !important;
            box-shadow: 0 0 15px rgba(245, 48, 3, 0.5) !important;
        }
        body[data-theme="consortium"] .logo-sub {
            color: #f53003 !important;
        }
        body[data-theme="consortium"] .auth-card {
            background: #090909 !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
        }
        body[data-theme="consortium"] .form-input {
            background: #020202 !important;
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
            --brand-border-hover: #000000;
            --glass-bg: rgba(255, 255, 255, 0.9);
            background: #f3f4f6 !important;
            color: #000000 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="retro"] .glow-1,
        body[data-theme="retro"] .glow-2 {
            display: none !important;
        }
        body[data-theme="retro"] .logo-mark {
            background: var(--brand-primary) !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .logo-sub {
            color: var(--brand-primary) !important;
            border-left-color: #000000 !important;
        }
        body[data-theme="retro"] nav {
            background: #ffffff !important;
            border-bottom: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .logo {
            color: #000000 !important;
        }
        body[data-theme="retro"] .auth-card {
            border: 3px solid #000000 !important;
            box-shadow: 8px 8px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .auth-header h1 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .form-input {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .form-input:focus {
            border-color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .btn-submit {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .btn-submit:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .radio-option {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .radio-option input[type="radio"] {
            accent-color: var(--brand-primary) !important;
        }

        /* 🍃 Theme 4: Nordic (Warm Eco / Scandinavian Minimalist) */
        body[data-theme="nordic"] {
            --brand-primary: #1e3f20;
            --brand-bg: #faf7f2;
            --brand-card: #ffffff;
            --brand-text: #2b2b2b;
            --brand-subtext: #6e706a;
            --brand-border: #e6dfd5;
            --brand-border-hover: rgba(30, 63, 32, 0.2);
            --glass-bg: rgba(250, 247, 242, 0.95);
            background: #faf7f2 !important;
            color: #2b2b2b !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="nordic"] .glow-1,
        body[data-theme="nordic"] .glow-2 {
            display: none !important;
        }
        body[data-theme="nordic"] .logo-mark {
            background: #1e3f20 !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 10px rgba(30, 63, 32, 0.15) !important;
        }
        body[data-theme="nordic"] .logo-sub {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] nav {
            background: #faf7f2 !important;
            border-bottom: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .logo {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .auth-card {
            background: #ffffff !important;
            border: 1px solid #e6dfd5 !important;
            box-shadow: 0 20px 40px rgba(30, 63, 32, 0.05) !important;
        }
        body[data-theme="nordic"] .form-input {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .form-input:focus {
            border-color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .btn-submit {
            background: #1e3f20 !important;
            color: #faf7f2 !important;
            box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
            font-weight: 800;
        }
        body[data-theme="nordic"] .btn-submit:hover {
            background: #152d16 !important;
            box-shadow: 0 6px 20px rgba(30, 63, 32, 0.4) !important;
        }
        body[data-theme="nordic"] .radio-option {
            background: #f5f2eb !important;
            border: 1px solid #e6dfd5 !important;
            color: #2b2b2b !important;
        }
        body[data-theme="nordic"] .radio-option input[type="radio"] {
            accent-color: #1e3f20 !important;
        }

        /* 🟣 Theme 5: Synthwave (Retrofuturism / Pink-Purple Neon) */
        body[data-theme="synthwave"] {
            --brand-primary: #ff007f;
            --brand-bg: #120e2e;
            --brand-card: #1c1543;
            --brand-text: #ffffff;
            --brand-subtext: #8e89c5;
            --brand-border: rgba(255, 0, 127, 0.15);
            --brand-border-hover: rgba(0, 240, 255, 0.4);
            --glass-bg: rgba(18, 14, 46, 0.85);
            background: #120e2e !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="synthwave"] .glow-1 {
            background: radial-gradient(circle, rgba(255, 0, 127, 0.15) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="synthwave"] .glow-2 {
            background: radial-gradient(circle, rgba(0, 240, 255, 0.12) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="synthwave"] .logo-mark {
            background: linear-gradient(135deg, #ff007f, #00f0ff) !important;
            box-shadow: 0 0 20px rgba(255, 0, 127, 0.5) !important;
        }
        body[data-theme="synthwave"] .logo-sub {
            color: #ff007f !important;
        }
        body[data-theme="synthwave"] nav {
            background: #120e2e !important;
            border-bottom: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .logo {
            color: #ffffff !important;
            text-shadow: 0 0 8px rgba(0, 240, 255, 0.6) !important;
        }
        body[data-theme="synthwave"] .auth-card {
            background: #1c1543 !important;
            border: 1px solid rgba(255, 0, 127, 0.15) !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.6) !important;
        }
        body[data-theme="synthwave"] .form-input {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
            color: #ffffff !important;
        }
        body[data-theme="synthwave"] .form-input:focus {
            border-color: #ff007f !important;
        }
        body[data-theme="synthwave"] .btn-submit {
            background: #ff007f !important;
            color: #ffffff !important;
            box-shadow: 0 4px 12px rgba(255, 0, 127, 0.4) !important;
            font-weight: 800;
        }
        body[data-theme="synthwave"] .btn-submit:hover {
            background: #e60072 !important;
            box-shadow: 0 6px 20px rgba(255, 0, 127, 0.6) !important;
        }
        body[data-theme="synthwave"] .radio-option {
            background: rgba(18, 14, 46, 0.55) !important;
            border: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .radio-option input[type="radio"] {
            accent-color: #ff007f !important;
        }

        /* 🏁 Theme 6: Carbon (High-Performance Stealth / Motorsport Yellow) */
        body[data-theme="carbon"] {
            --brand-primary: #facc15;
            --brand-bg: #070708;
            --brand-card: #101012;
            --brand-text: #ffffff;
            --brand-subtext: #8b8b92;
            --brand-border: #222226;
            --brand-border-hover: rgba(250, 204, 21, 0.3);
            --glass-bg: rgba(7, 7, 8, 0.95);
            background: #070708 !important;
            font-family: 'JetBrains Mono', monospace !important;
        }
        body[data-theme="carbon"] .glow-1 {
            background: radial-gradient(circle, rgba(250, 204, 21, 0.05) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="carbon"] .logo-mark {
            background: #facc15 !important;
            border-radius: 4px !important;
            box-shadow: 0 0 20px rgba(250, 204, 21, 0.5) !important;
        }
        body[data-theme="carbon"] .logo-sub {
            color: #facc15 !important;
        }
        body[data-theme="carbon"] nav {
            background: #070708 !important;
            border-bottom: 2px solid #222226 !important;
        }
        body[data-theme="carbon"] .logo {
            color: #ffffff !important;
        }
        body[data-theme="carbon"] .auth-card {
            background: #101012 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.8) !important;
        }
        body[data-theme="carbon"] .form-input {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            color: #ffffff !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .form-input:focus {
            border-color: #facc15 !important;
        }
        body[data-theme="carbon"] .btn-submit {
            background: #facc15 !important;
            color: #000000 !important;
            box-shadow: 0 4px 12px rgba(250, 204, 21, 0.2) !important;
            border-radius: 4px !important;
            font-weight: 900;
        }
        body[data-theme="carbon"] .btn-submit:hover {
            background: #eab308 !important;
            box-shadow: 0 6px 20px rgba(250, 204, 21, 0.4) !important;
        }
        body[data-theme="carbon"] .radio-option {
            background: #151518 !important;
            border: 2px solid #222226 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .radio-option input[type="radio"] {
            accent-color: #facc15 !important;
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


        /* Generic Holiday Styles (Modern themes only, not Retro) */
        body[data-holiday]:not([data-theme="retro"]) .logo-mark {
            background: var(--brand-primary) !important;
            box-shadow: 0 0 20px var(--brand-primary) !important;
        }
        body[data-holiday]:not([data-theme="retro"]) .logo-sub {
            color: var(--brand-primary) !important;
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: var(--brand-primary) !important;
        }
        body[data-holiday]:not([data-theme="retro"]) .btn-submit,
        body[data-holiday]:not([data-theme="retro"]) button[type="button"] {
            background: var(--brand-primary) !important;
            color: #000000 !important;
            border-color: var(--brand-primary) !important;
            box-shadow: 0 4px 15px var(--brand-primary) !important;
        }
        body[data-holiday]:not([data-theme="retro"]) .btn-submit:hover,
        body[data-holiday]:not([data-theme="retro"]) button[type="button"]:hover {
            opacity: 0.9 !important;
            box-shadow: 0 6px 20px var(--brand-primary) !important;
        }

        /* Generic Holiday Styles for Retro theme specifically */
        body[data-holiday][data-theme="retro"] .logo-mark {
            background: var(--brand-primary) !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-holiday][data-theme="retro"] .logo-sub {
            color: var(--brand-primary) !important;
            border-left-color: #000000 !important;
        }
        body[data-holiday][data-theme="retro"] .btn-submit {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        body[data-holiday][data-theme="retro"] .btn-submit:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
            background: var(--brand-primary) !important;
        }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>
@include('partials.theme-sync-body')
<div class="ambient-glows">
    <div class="glow-1"></div>
    <div class="glow-2"></div>
</div>

@include('storefront.partials.header')

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            @if($brand)
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 4px 12px; border-radius: 20px; margin-bottom: 1rem;">
                    <div style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></div>
                    <span style="font-size: 8px; font-weight: 800; color: #10b981; letter-spacing: 0.1em; text-transform: uppercase;">{{ $brand->name }} Compliance Domain</span>
                </div>
            @endif

            @if($inviteIntent ?? null)
                <div style="background: rgba(245, 48, 3, 0.05); border: 1px solid rgba(245, 48, 3, 0.2); padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem; text-align: left;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                        <i class="ph-bold ph-planet" style="color: var(--brand-primary); font-size: 1.25rem;"></i>
                        <span style="font-size: 11px; font-weight: 800; color: var(--brand-primary); letter-spacing: 0.05em; text-transform: uppercase;">Приглашение в команду</span>
                    </div>
                    <p style="font-size: 13.5px; color: #fff; margin-bottom: 0.25rem;">
                        Вас пригласили присоединиться к команде <strong>{{ $inviteIntent['partner_name'] }}</strong>
                    </p>
                    <p style="font-size: 11px; color: var(--brand-subtext);">
                        Роль в системе: <span style="color: #fff; font-weight: 600;">{{ match($inviteIntent['role']) { 'admin' => 'Администратор', 'manager' => 'Менеджер', 'viewer' => 'Наблюдатель', default => 'Наблюдатель' } }}</span>
                    </p>
                </div>
            @endif

            @if(!Auth::check() || !Auth::user()->passkeys()->exists())
                <h1>Создание профиля</h1>
                <p id="perimeter-desc">
                    Это выделенный маршрут для подключения юрлица: сначала Simple L1 профиль с Passkey и `sl1_` адресом, затем реквизиты организации.
                </p>
            @else
                <h1>Регистрация бизнеса</h1>
                <p id="perimeter-desc">
                    {{ ($inviteIntent ?? null) ? 'Зарегистрируйте свой суверенный ключ доступа для входа в панель консорциума.' : 'Определите вашу юрисдикцию для входа в легальный периметр.' }}
                </p>
            @endif
        </div>

        @if($errors->any())
            <div style="color: #ef4444; font-size: 13px; margin-bottom: 1.5rem; text-align: center; background: rgba(239, 68, 68, 0.08); padding: 0.5rem; border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.15);">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ $registrationSubmitRoute ?? route('partner.register.submit') }}" method="POST" id="registration-form">
            @csrf
            <input type="hidden" name="registration_target" value="{{ $registrationTarget ?? 'legal_entity' }}">
            <input type="hidden" name="registration_mode" id="registration-mode" value="business">
            <input type="hidden" name="dadata_verified" id="dadata-verified" value="0">
            <input type="hidden" name="dadata_party_type" id="dadata-party-type" value="">
            @if($brand)
                <input type="hidden" name="brand_id" value="{{ $brand->id }}">
            @endif
            @if($inviteToken ?? null)
                <input type="hidden" name="invite" value="{{ $inviteToken }}">
            @endif
            
            @if(!Auth::check() || !Auth::user()->passkeys()->exists())
                <!-- PHASE 1: SOVEREIGN IDENTITY (EMAIL ONLY) -->
                <div class="form-group">
                    <label class="form-label">Рабочий Email</label>
                    <input type="email" name="email" class="form-input" placeholder="ivan@company.com" required value="{{ old('email') }}">
                </div>

                <button type="submit" id="submit-btn" class="btn-submit" style="margin-top: 1.5rem;">
                    Создать профиль
                </button>
            @else
                <!-- PHASE 2: BUSINESS REGISTRATION -->
                <!-- 🌍 Jurisdiction Selection -->
                <div class="form-group" style="{{ ($inviteIntent ?? null) ? 'display: none;' : '' }}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.5rem;">
                        <label class="form-label" style="margin-bottom: 0;">Юрисдикция / Jurisdiction</label>
                        @if(isset($detectedCountryName) && $detectedCountry !== 'RU')
                            <span style="font-size: 10px; color: var(--brand-primary); font-weight: 600;">📍 Вы в: {{ $detectedCountryName }}</span>
                        @endif
                    </div>
                    <select name="jurisdiction" id="jurisdiction" class="form-input" style="height: 48px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%238e8e93%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto;">
                        @php $dc = $detectedCountry ?? 'RU'; @endphp
                        @if(!$supportedJurisdictions || in_array('RU', $supportedJurisdictions))
                            <option value="RU" {{ $dc === 'RU' ? 'selected' : '' }}>🇷🇺 Россия (ИНН)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('KZ', $supportedJurisdictions))
                            <option value="KZ" {{ $dc === 'KZ' ? 'selected' : '' }}>🇰🇿 Казахстан (БИН)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('BY', $supportedJurisdictions))
                            <option value="BY" {{ $dc === 'BY' ? 'selected' : '' }}>🇧🇾 Беларусь (УНП)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('UZ', $supportedJurisdictions))
                            <option value="UZ" {{ $dc === 'UZ' ? 'selected' : '' }}>🇺🇿 Узбекистан (ИНН)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('AM', $supportedJurisdictions))
                            <option value="AM" {{ $dc === 'AM' ? 'selected' : '' }}>🇦🇲 Армения (ИНН/ՀՎՀՀ)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('KG', $supportedJurisdictions))
                            <option value="KG" {{ $dc === 'KG' ? 'selected' : '' }}>🇰🇬 Кыргызстан (ИНН/ИН)</option>
                        @endif
                        @if(!$supportedJurisdictions || in_array('TM', $supportedJurisdictions))
                            <option value="TM" {{ $dc === 'TM' ? 'selected' : '' }}>🇹🇲 Туркменистан (ИНН/TIN)</option>
                        @endif
                    </select>
                    @if($brand)
                        <p class="mt-1" style="font-size: 11px; color: var(--brand-subtext); margin-top: 6px;">Показаны регионы, поддерживаемые брендом {{ $brand->name }}</p>
                        <div id="compliance-info" style="margin-top: 8px; font-size: 11px; color: var(--brand-subtext); padding: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid var(--brand-border); display: none;">
                            <!-- Dynamic compliance details -->
                        </div>
                    @endif
                </div>

                <!-- PHASE 1: INN SEARCH -->
                <div id="phase-search" style="{{ ($inviteIntent ?? null) ? 'display: none;' : '' }}">
                    <div class="form-group">
                        <label id="inn-label" class="form-label">ИНН организации</label>
                        <div style="position: relative;">
                            <input type="text" name="inn" id="inn-field" class="form-input" placeholder="7700123456" required value="{{ old('inn') }}" autocomplete="off" inputmode="numeric" pattern="[0-9]*" maxlength="12" style="padding-right: 50px;">
                            <button type="button" onclick="searchINN()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: var(--brand-primary); color: #fff; border: none; width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                                <i class="ph-bold ph-magnifying-glass" style="font-size: 1.1rem;"></i>
                            </button>
                        </div>
                        <p id="inn-hint" style="font-size: 11px; color: var(--brand-subtext); margin-top: 6px;">10 цифр для юрлица, 12 цифр для ИП/физлица</p>
                    </div>

                    <div class="form-group" id="name-container" style="display: none; transition: all 0.3s ease; opacity: 0; margin-top: 1.5rem;">
                        <div style="background: rgba(16, 185, 129, 0.03); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: 16px; padding: 1.5rem; text-align: center;">
                            <label class="form-label" style="color: #10b981; margin-bottom: 0.5rem; display: block;">Найдена организация:</label>
                            <input type="text" name="legal_name" id="name-field" class="form-input" readonly style="background: transparent; border: none; color: #fff; font-weight: 800; text-align: center; font-size: 1.2rem; padding: 0;">
                            
                            <div style="font-size: 10px; color: #10b981; margin-top: 1rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 1.5rem; letter-spacing: 0.05em;">
                                <i class="ph-bold ph-seal-check"></i> VERIFIED BY DADATA
                            </div>

                            <button type="button" id="confirm-org-btn" class="btn-submit" style="background: #10b981; color: #fff; margin-top: 0;" disabled>
                                Да, это моя организация ✅
                            </button>
                        </div>
                    </div>

                    <div id="individual-only-panel" style="display: none; margin-top: 1.5rem; padding: 1.35rem; border: 1px solid var(--brand-border); border-radius: 16px; background: rgba(255,255,255,0.025);">
                        <div style="font-size: 11px; color: var(--brand-primary); font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 0.75rem;">Физлицо без статуса ИП</div>
                        <p style="font-size: 13px; color: var(--brand-text); line-height: 1.55; margin: 0 0 0.9rem;">
                            DaData не нашла по этому ИНН действующее ИП или юридическое лицо. Сейчас доступна регистрация как физическое лицо: личный кабинет, покупки, ключи, Passkey-профиль и история заказов.
                        </p>
                        <div style="font-size: 12px; color: var(--brand-subtext); line-height: 1.55; margin-bottom: 1rem;">
                            Чтобы продавать цифровые товары, подключать API, витрины, Ozon/WB/Яндекс Маркет, получать оптовые цены и выплаты на расчетный счет, нужно открыть ИП или юрлицо. После регистрации ИП вернитесь сюда и повторите проверку ИНН.
                        </div>
                        <button type="submit" id="continue-as-individual-btn" class="btn-submit" style="margin-top: 0;">
                            Продолжить как физлицо
                        </button>
                    </div>

                    <div id="npd-panel" style="display: none; margin-top: 1.5rem; padding: 1.35rem; border: 1px solid rgba(16,185,129,0.26); border-radius: 16px; background: rgba(16,185,129,0.035);">
                        <div style="font-size: 11px; color: #10b981; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase; margin-bottom: 0.75rem;">Статус самозанятого подтвержден ФНС</div>
                        <p style="font-size: 13px; color: var(--brand-text); line-height: 1.55; margin: 0 0 0.9rem;">
                            По этому ИНН найден плательщик налога на профессиональный доход. Можно подключить NPD-профиль: принимать выплаты как самозанятый, продавать разрешенные цифровые услуги/товары в рамках лимитов НПД и вести расчеты без расчетного счета ИП.
                        </p>
                        <div style="font-size: 12px; color: var(--brand-subtext); line-height: 1.55; margin-bottom: 1rem;">
                            Для полноценного B2B-периметра с маркетплейсами, API-витринами, оптовыми закупками, командой, складом кодов и выплатами на расчетный счет лучше открыть ИП. После регистрации ИП повторите проверку ИНН, и профиль можно будет расширить.
                        </div>
                        <button type="submit" id="continue-as-npd-btn" class="btn-submit" style="margin-top: 0; background: #10b981; border-color: #10b981;">
                            Продолжить как самозанятый
                        </button>
                    </div>
                </div>

                <!-- PHASE 2: DETAILS (Hidden initially) -->
                <div id="phase-details" style="{{ ($inviteIntent ?? null) ? 'display: block;' : 'display: none;' }}; animation: slideDown 0.5s ease forwards;">
                    <div class="form-group" style="margin-top: 1.5rem; opacity: 0.7;">
                        <label class="form-label">Рабочий Email</label>
                        <input type="email" name="email" class="form-input" readonly value="{{ auth()->user()->email }}" style="background: rgba(255,255,255,0.03);">
                    </div>

                    @if(!($inviteIntent ?? null))
                        <!-- 💰 Tax System -->
                        <div id="tax-section" style="margin-top: 1.5rem;">
                            <label class="form-label">Система налогообложения</label>
                            <select name="tax_system" id="tax_system" class="form-input" style="height: 48px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%238e8e93%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto;">
                                <option value="OSN">ОСНО (Общая система)</option>
                                <option value="USN">УСН (Упрощенная система)</option>
                                <option value="AUSN">АУСН (Автоматизированная)</option>
                                <option value="USN_INCOME">УСН Доходы</option>
                                <option value="NPD">НПД (Самозанятый)</option>
                            </select>
                        </div>

                        <!-- Fallback/IP Fields -->
                        <div id="fallback-fields" style="display: none; margin-top: 1.5rem; border-top: 1px solid var(--brand-border); padding-top: 1.5rem;">
                            <p id="fallback-message" style="font-size: 12px; color: var(--brand-primary); margin-bottom: 1.25rem;"></p>
                            <div id="manual-name-group" class="form-group">
                                <label class="form-label">Полное название организации</label>
                                <input type="text" name="legal_name" id="manual_legal_name" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ОГРН</label>
                                <input type="text" name="ogrn" id="manual_ogrn" class="form-input">
                            </div>
                            <div class="form-group">
                                <label id="address-label" class="form-label">Юридический адрес</label>
                                <textarea name="address" id="manual_address" class="form-input" style="height: 70px; resize: none;"></textarea>
                            </div>
                        </div>

                        <!-- 👤 Signer Authority -->
                        <div class="form-section" style="margin-top: 1.5rem; padding: 1.5rem; background: rgba(255,255,255,0.01); border-radius: 16px; border: 1px solid var(--brand-border);">
                            <h3 style="font-size: 11px; margin-bottom: 1.25rem; color: var(--brand-primary); display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">
                                <i class="ph-bold ph-identification-card"></i> Полномочия подписанта
                            </h3>

                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="signer_role" value="ceo" checked onclick="togglePoA(false)">
                                    <div>
                                        <div style="font-weight: 700; font-size: 13.5px;">Я — Руководитель компании</div>
                                        <div style="font-size: 11px; color: var(--brand-subtext); margin-top: 2px;">Действую на основании Устава (первое лицо)</div>
                                    </div>
                                </label>

                                <label class="radio-option">
                                    <input type="radio" name="signer_role" value="representative" onclick="togglePoA(true)">
                                    <div>
                                        <div style="font-weight: 700; font-size: 13.5px;">Действую по доверенности</div>
                                        <div style="font-size: 11px; color: var(--brand-subtext); margin-top: 2px;">Уполномоченный представитель организации</div>
                                    </div>
                                </label>
                            </div>

                            <div id="poa-fields" style="display: none; margin-top: 1.5rem; border-top: 1px solid var(--brand-border); padding-top: 1.5rem;">
                                <div class="form-group">
                                    <label class="form-label">ФИО представителя</label>
                                    <input type="text" name="signer_name" id="signer_name" class="form-input" placeholder="Иванов Иван Иванович">
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Номер доверенности</label>
                                        <input type="text" name="poa_number" id="poa_number" class="form-input" placeholder="№ 123/2026">
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Дата выдачи</label>
                                        <input type="text" name="poa_date" id="poa_date" class="form-input" placeholder="{{ date('d/m/Y') }}">
                                    </div>
                                </div>
                                <div class="form-group" style="margin-top: 1.25rem;">
                                    <label class="form-label">Контактный телефон</label>
                                    <input type="tel" name="signer_phone" id="signer_phone" class="form-input" placeholder="+7 (999) 000-00-00">
                                </div>
                                <div class="form-group" style="margin-top: 1.25rem; margin-bottom: 0;">
                                    <label class="form-label" style="color: var(--brand-primary);">Скан-копия доверенности (PDF/JPG)</label>
                                    <input type="file" name="poa_file" class="form-input" style="padding: 10px; font-size: 12px; border: 1px dashed var(--brand-primary); background: transparent;">
                                </div>
                            </div>
                        </div>
                    @endif

                    <div id="background-data"></div>

                    <button type="submit" id="submit-btn" class="btn-submit">
                        Продолжить вход в периметр 🛡️
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>


<script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
<script>
    const { startRegistration, startAuthentication } = SimpleWebAuthnBrowser;
    const registrationForm = document.getElementById('registration-form');
    const submitBtn = document.getElementById('submit-btn');

    // Server-side flags
    const isUpgrade = @json(Auth::check() && Auth::user()->passkeys()->exists());
    const signingOptionsRaw = @json($signingOptions);

    registrationForm.addEventListener('submit', async (e) => {
        // 🛑 Stop multiple submissions
        if (registrationForm.dataset.submitting === 'true') return;

        if (isUpgrade) {
            // Logged-in user: just submit the form natively to process company data
            registrationForm.dataset.submitting = 'true';
            submitBtn.disabled = true;
            submitBtn.innerText = 'Сохранение данных организации... ⏳';
            return;
        }

        e.preventDefault();

        const emailInput = registrationForm.querySelector('input[name="email"]');
        const email = emailInput ? emailInput.value.trim() : '';

        if (!email) {
            alert('Пожалуйста, введите рабочий Email');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = 'Активация личности... 🛡️';

        try {

            // 🔑 Phase 1 (guest): Register new passkey
            console.log('Starting Sovereign Identity creation for:', email);
            const optionsRes = await fetch(@json($registrationOptionsRoute ?? route('partner.register.options')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    email: email,
                    registration_target: @json($registrationTarget ?? 'legal_entity')
                })
            });

            if (!optionsRes.ok) {
                const errorData = await optionsRes.json();
                throw new Error(errorData.error || 'Server error');
            }

            const data = await optionsRes.json();
            const options = data.options;
            const newCsrf = data.new_csrf;

            console.log('Options received:', options);

            // Trigger Biometric Registration
            const attestationResponse = await startRegistration({ optionsJSON: options });
            console.log('Attestation received:', attestationResponse);

            // Update CSRF Token to avoid 419
            if (newCsrf) {
                const csrfInput = registrationForm.querySelector('input[name="_token"]');
                if (csrfInput) csrfInput.value = newCsrf;
            }

            const attestationInput = document.createElement('input');
            attestationInput.type = 'hidden';
            attestationInput.name = 'passkey_attestation';
            attestationInput.value = JSON.stringify(attestationResponse);
            registrationForm.appendChild(attestationInput);

            registrationForm.dataset.submitting = 'true';
            registrationForm.submit();

        } catch (err) {
            console.error('Identity Error:', err);
            alert('Ошибка активации личности: ' + err.message);
            submitBtn.disabled = false;
            submitBtn.innerText = isUpgrade ? 'Продолжить вход в периметр 🛡️' : 'Создать суверенную личность 🛡️';
        }
    });

    // --- Business Registration Phase JS (only runs when elements exist) ---
    const innInput = document.getElementById('inn-field');
    const innLabel = document.getElementById('inn-label');
    const jurisdictionSelect = document.getElementById('jurisdiction');
    const fallbackFields = document.getElementById('fallback-fields');
    const bgData = document.getElementById('background-data');
    const nameField = document.getElementById('name-field');
    const nameContainer = document.getElementById('name-container');
    const phaseSearch = document.getElementById('phase-search');
    const phaseDetails = document.getElementById('phase-details');
    const confirmBtn = document.getElementById('confirm-org-btn');
    const innHint = document.getElementById('inn-hint');
    const dadataVerifiedInput = document.getElementById('dadata-verified');
    const dadataPartyTypeInput = document.getElementById('dadata-party-type');
    const registrationModeInput = document.getElementById('registration-mode');
    const individualOnlyPanel = document.getElementById('individual-only-panel');
    const continueAsIndividualBtn = document.getElementById('continue-as-individual-btn');
    const npdPanel = document.getElementById('npd-panel');
    const continueAsNpdBtn = document.getElementById('continue-as-npd-btn');
    let innSearchTimer = null;

    const complianceConfig = @json($complianceConfig);
    const complianceInfo = document.getElementById('compliance-info');
    const taxIdRules = {
        RU: { label: 'ИНН организации / физлица', placeholder: '7700123456 или 123456789012', lengths: [10, 12], max: 12, hint: '10 цифр для юрлица, 12 цифр для ИП/физлица' },
        KZ: { label: 'БИН / ИИН', placeholder: '123456789012', lengths: [12], max: 12, hint: '12 цифр' },
        BY: { label: 'УНП', placeholder: '123456789', lengths: [9], max: 9, hint: '9 цифр' },
        UZ: { label: 'ИНН / ПИНФЛ', placeholder: '123456789', lengths: [9, 14], max: 14, hint: '9 цифр для юрлица, 14 для физлица' },
        AM: { label: 'ИНН / ՀՎՀՀ', placeholder: '12345678', lengths: [8], max: 8, hint: '8 цифр' },
        KG: { label: 'ИНН / ИН', placeholder: '12345678901234', lengths: [14], max: 14, hint: '14 цифр' },
        TM: { label: 'ИНН / TIN', placeholder: '12345678', lengths: [8], max: 8, hint: '8 цифр' },
    };

    const getTaxIdRule = () => taxIdRules[jurisdictionSelect?.value || 'RU'] || taxIdRules.RU;
    const normalizeTaxId = () => {
        if (!innInput) return '';
        const rule = getTaxIdRule();
        const normalized = innInput.value.replace(/\D/g, '').slice(0, rule.max);
        if (innInput.value !== normalized) innInput.value = normalized;
        innInput.maxLength = rule.max;
        innInput.setAttribute('maxlength', String(rule.max));
        return normalized;
    };
    const isCompleteTaxId = (value) => getTaxIdRule().lengths.includes(value.length);
    const requiresDaDataVerification = () => (jurisdictionSelect?.value || 'RU') === 'RU';
    const resetDaDataVerification = () => {
        if (dadataVerifiedInput) dadataVerifiedInput.value = '0';
        if (dadataPartyTypeInput) dadataPartyTypeInput.value = '';
        if (registrationModeInput) registrationModeInput.value = 'business';
        if (individualOnlyPanel) individualOnlyPanel.style.display = 'none';
        if (npdPanel) npdPanel.style.display = 'none';
        if (bgData) bgData.innerHTML = '';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerText = 'Сначала подтвердите ИНН через DaData';
            confirmBtn.style.opacity = '0.55';
            confirmBtn.style.cursor = 'not-allowed';
        }
    };
    const showNpdPanel = () => {
        if (registrationModeInput) registrationModeInput.value = 'self_employed';
        if (npdPanel) npdPanel.style.display = 'block';
        if (individualOnlyPanel) individualOnlyPanel.style.display = 'none';
        if (dadataVerifiedInput) dadataVerifiedInput.value = '1';
        if (dadataPartyTypeInput) dadataPartyTypeInput.value = 'NPD';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerText = 'Используйте регистрацию самозанятого';
            confirmBtn.style.opacity = '0.55';
            confirmBtn.style.cursor = 'not-allowed';
        }
    };
    const markDaDataVerified = (partyType) => {
        if (dadataVerifiedInput) dadataVerifiedInput.value = '1';
        if (dadataPartyTypeInput) dadataPartyTypeInput.value = partyType || '';
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.innerText = 'Да, это моя организация ✅';
            confirmBtn.style.opacity = '1';
            confirmBtn.style.cursor = 'pointer';
        }
    };
    const showIndividualOnlyPanel = () => {
        if (registrationModeInput) registrationModeInput.value = 'profile';
        if (individualOnlyPanel) individualOnlyPanel.style.display = 'block';
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.innerText = 'Бизнес-профиль недоступен для физлица';
            confirmBtn.style.opacity = '0.55';
            confirmBtn.style.cursor = 'not-allowed';
        }
    };

    if (jurisdictionSelect) {
        const updateLabels = () => {
            const jurisdiction = jurisdictionSelect.value;
            const rule = getTaxIdRule();
            if (innLabel) innLabel.innerText = rule.label;
            if (innInput) {
                innInput.placeholder = rule.placeholder;
                innInput.title = rule.hint;
                normalizeTaxId();
            }
            if (innHint) innHint.innerText = rule.hint;
            resetDaDataVerification();

            if (complianceConfig && complianceConfig[jurisdiction] && complianceInfo) {
                const config = complianceConfig[jurisdiction];
                complianceInfo.style.display = 'block';
                if (config.blocked) {
                    complianceInfo.style.background = 'rgba(239, 68, 68, 0.03)';
                    complianceInfo.style.borderColor = 'rgba(239, 68, 68, 0.15)';
                    complianceInfo.innerHTML = `<div style="color:#ef4444;font-weight:800;font-size:11px;margin-bottom:4px;">⛔ OUT OF PERIMETER</div><div style="font-size:10px;">${config.reason || 'Not supported in this domain.'}</div>`;
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                } else {
                    complianceInfo.style.background = 'rgba(255,255,255,0.01)';
                    complianceInfo.style.borderColor = 'var(--brand-border)';
                    complianceInfo.innerHTML = `<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>Risk Level:</span><span style="color:${config.risk==='high'?'#ef4444':'#ffaa00'};font-weight:700;">${config.risk?config.risk.toUpperCase():'MEDIUM'}</span></div><div style="display:flex;justify-content:space-between;"><span>KYC Provider:</span><span style="color:#fff;font-weight:600;">${config.kyc_provider||'Standard'}</span></div>`;
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                }
            } else {
                if (complianceInfo) complianceInfo.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
        };

        jurisdictionSelect.addEventListener('change', updateLabels);
        updateLabels();
    }

    if (innInput) {
        const handleInput = () => {
            const inn = normalizeTaxId();
            clearTimeout(innSearchTimer);
            resetDaDataVerification();
            if (isCompleteTaxId(inn)) {
                innSearchTimer = setTimeout(searchINN, 300);
            }
        };
        innInput.addEventListener('input', handleInput);
        innInput.addEventListener('paste', () => setTimeout(handleInput, 100));
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (requiresDaDataVerification() && dadataVerifiedInput?.value !== '1') {
                alert('Сначала подтвердите ИНН через DaData. Нужна найденная компания или ИП.');
                return;
            }
            if (phaseSearch) {
                phaseSearch.style.opacity = '0.3';
                phaseSearch.style.pointerEvents = 'none';
            }
            if (phaseDetails) phaseDetails.style.display = 'block';
        });
    }

    if (continueAsIndividualBtn) {
        continueAsIndividualBtn.addEventListener('click', () => {
            if (registrationModeInput) registrationModeInput.value = 'profile';
        });
    }

    if (continueAsNpdBtn) {
        continueAsNpdBtn.addEventListener('click', () => {
            if (registrationModeInput) registrationModeInput.value = 'self_employed';
            if (dadataVerifiedInput) dadataVerifiedInput.value = '1';
            if (dadataPartyTypeInput) dadataPartyTypeInput.value = 'NPD';
        });
    }

    function togglePoA(show) {
        const poaFields = document.getElementById('poa-fields');
        if (!poaFields) return;
        poaFields.style.display = show ? 'block' : 'none';
        if (show) poaFields.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function searchINN() {
        if (!innInput || !nameContainer || !nameField) return;
        const inn = normalizeTaxId();
        if (!inn) return;
        if (!isCompleteTaxId(inn)) {
            resetDaDataVerification();
            nameContainer.style.display = 'block';
            nameContainer.style.opacity = '1';
            nameField.value = getTaxIdRule().hint;
            return;
        }
        if (!requiresDaDataVerification()) {
            markDaDataVerified('FOREIGN');
            return;
        }

        nameContainer.style.display = 'block';
        nameContainer.style.opacity = '0.5';
        nameField.value = 'Загрузка...';
        resetDaDataVerification();

        try {
            const res = await fetch('/api/b2b/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ inn: inn })
            });

            if (!res.ok) throw new Error('API Error: ' + res.status);

            const data = await res.json();
            if (bgData) bgData.innerHTML = '';

            if (data.suggestions && data.suggestions.length > 0) {
                const org = data.suggestions[0];
                const partyType = org.raw_type || (org.is_ip ? 'INDIVIDUAL' : 'LEGAL');
                if (String(org.inn || '') !== inn || !['LEGAL', 'INDIVIDUAL'].includes(partyType)) {
                    nameField.value = 'ИНН не подтвержден DaData';
                    nameContainer.style.opacity = '1';
                    resetDaDataVerification();
                    return;
                }

                nameContainer.style.opacity = '1';
                nameField.value = org.name;
                markDaDataVerified(partyType);

                addHidden('legal_name', org.name);
                addHidden('ogrn', org.ogrn);
                addHidden('kpp', org.kpp || '');
                addHidden('address', org.address || '');
                addHidden('director_name', typeof org.management === 'string' ? org.management : (org.management?.name || ''));

                const taxEl = document.getElementById('tax_system');
                if (taxEl) taxEl.value = org.tax_system || 'OSN';

                if (org.is_ip && fallbackFields) {
                    fallbackFields.style.display = 'block';
                    const mg = document.getElementById('manual-name-group');
                    if (mg) mg.style.display = 'none';
                    const fm = document.getElementById('fallback-message');
                    if (fm) fm.textContent = 'Для ИП и самозанятых необходимо подтвердить адрес регистрации:';
                    const al = document.getElementById('address-label');
                    if (al) al.textContent = 'Адрес регистрации';
                    const ma = document.getElementById('manual_address');
                    if (ma) ma.value = org.address || '';
                    const mo = document.getElementById('manual_ogrn');
                    if (mo) mo.value = org.ogrn;
                } else if (fallbackFields) {
                    fallbackFields.style.display = 'none';
                }
            } else if (data.fallback) {
                nameField.value = 'ИНН не найден в DaData';
                nameContainer.style.opacity = '1';
                resetDaDataVerification();
                if (data.npd && data.npd.status === true) {
                    nameField.value = 'Самозанятый подтвержден ФНС';
                    showNpdPanel();
                    return;
                }
                if (requiresDaDataVerification() && inn.length === 12) {
                    showIndividualOnlyPanel();
                }
            }
        } catch (e) {
            console.error('Search failed:', e);
            resetDaDataVerification();
            if (nameField) nameField.value = 'Ошибка проверки DaData';
            if (nameContainer) nameContainer.style.opacity = '1';
        }
    }

    function addHidden(name, value) {
        if (!bgData) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        bgData.appendChild(input);
    }

</script>

</body>
</html>


