<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meanly — цифровые товары и подарочные карты</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- Icons -->
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

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--brand-bg);
            color: var(--brand-text);
            line-height: 1.5;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* 🪐 Futuristic Background Glows */
        .ambient-glows {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-1 {
            position: absolute; top: -10%; left: 20%; width: 60vw; height: 60vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.08) 0%, rgba(0,0,0,0) 70%);
            filter: blur(80px);
        }
        .glow-2 {
            position: absolute; top: 30%; right: -10%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(0, 102, 255, 0.06) 0%, rgba(0,0,0,0) 70%);
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
        
        .nav-links { display: flex; gap: 2rem; align-items: center; }
        .nav-links a { color: var(--brand-subtext); text-decoration: none; font-size: 13px; font-weight: 500; transition: color .2s; }
        .nav-links a:hover { color: var(--brand-text); }
        .nav-links a.nav-link-b2b { color: var(--brand-primary) !important; font-weight: 600; }
        .nav-links a.nav-link-b2b:hover { filter: brightness(1.2); }
        
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-nav-login { 
            color: var(--brand-text); 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 600; 
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: background 0.2s;
        }
        .btn-nav-login:hover {
            background: rgba(255,255,255,0.05);
        }
        .btn-nav-cta {
            background: var(--brand-primary); 
            color: #fff !important; 
            padding: 0.5rem 1.25rem;
            border-radius: 8px; 
            font-weight: 700; 
            font-size: 13px;
            text-decoration: none; 
            box-shadow: 0 4px 20px rgba(245, 48, 3, 0.3);
            transition: all 0.2s;
        }
        .btn-nav-cta:hover { 
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(245, 48, 3, 0.5);
        }

        /* ── HERO ── */
        .hero {
            position: relative;
            padding: 10rem 1.5rem 4rem;
            display: flex; flex-direction: column; align-items: center;
            text-align: center;
            max-width: 1200px; margin: 0 auto;
            z-index: 10;
        }

        .hero-badge {
            background: rgba(245, 48, 3, 0.1);
            color: var(--brand-primary);
            border: 1px solid rgba(245, 48, 3, 0.2);
            padding: 0.4rem 1rem;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .hero h1 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1.1;
            max-width: 900px;
            margin-bottom: 1.5rem;
            background: linear-gradient(180deg, #ffffff 0%, #a0a0a0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero p.subtitle {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--brand-subtext);
            max-width: 600px;
            margin-bottom: 3rem;
            line-height: 1.6;
        }
        
        .hero-actions { display: flex; gap: 1rem; }

        /* ── RETAIL HUB ── */
        .store-section {
            position: relative;
            padding: 4rem 1.5rem 8rem;
            max-width: 1200px;
            margin: 0 auto;
            z-index: 10;
        }

        .store-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 3rem;
            border-bottom: 1px solid var(--brand-border);
            padding-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .store-title h2 {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .store-title p {
            color: var(--brand-subtext);
            font-size: 14px;
            margin-top: 0.25rem;
        }

        /* Filters */
        .filter-group {
            display: flex;
            gap: 0.5rem;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--brand-border);
            padding: 0.3rem;
            border-radius: 100px;
        }
        .filter-btn {
            background: transparent;
            border: none;
            color: var(--brand-subtext);
            padding: 0.5rem 1.2rem;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .filter-btn.active, .filter-btn:hover {
            background: rgba(255,255,255,0.08);
            color: var(--brand-text);
        }

        /* Product Grid */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 2rem;
        }

        .product-card {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 16px;
            padding: 1.8rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 4px;
            background: var(--platform-gradient, linear-gradient(90deg, #f53003, #ff7b00));
            opacity: 0.8;
        }

        .product-card:hover {
            transform: translateY(-4px);
            border-color: var(--brand-border-hover);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 30px var(--platform-glow, rgba(245, 48, 3, 0.05));
        }

        .platform-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--platform-color, var(--brand-primary));
            margin-bottom: 1.5rem;
        }

        .product-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .product-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.8rem;
        }
        .region-tag {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-size: 11px;
            color: var(--brand-subtext);
            background: rgba(255,255,255,0.03);
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            border: 1px solid var(--brand-border);
        }

        .price-section {
            border-top: 1px solid var(--brand-border);
            padding-top: 1.5rem;
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .price-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--brand-subtext);
        }
        .price-value {
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--brand-text);
        }

        .btn-buy {
            all: unset;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            height: 44px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--brand-border);
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 1.2rem;
            transition: all 0.2s;
        }
        .btn-buy:hover {
            background: var(--brand-text);
            color: #000;
            border-color: var(--brand-text);
            box-shadow: 0 4px 15px rgba(255,255,255,0.1);
        }

        /* ── INTERACTIVE CHECKOUT MODAL ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(8px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .checkout-modal {
            background: #090909;
            border: 1px solid var(--brand-border);
            width: 100%;
            max-width: 440px;
            border-radius: 20px;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 40px 100px rgba(0,0,0,0.8);
            transform: scale(0.95);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
        }
        .modal-overlay.active .checkout-modal {
            transform: scale(1);
        }

        .close-btn {
            position: absolute;
            top: 1.5rem; right: 1.5rem;
            background: transparent;
            border: none;
            color: var(--brand-subtext);
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .close-btn:hover { color: var(--brand-text); }

        .checkout-icon {
            font-size: 2.5rem;
            color: var(--brand-primary);
            margin-bottom: 1.5rem;
        }
        .checkout-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 0.8rem;
        }
        .checkout-desc {
            font-size: 14px;
            color: var(--brand-subtext);
            line-height: 1.6;
            margin-bottom: 2.5rem;
        }

        /* ── FOOTER ── */
        footer {
            padding: 6rem 0;
            border-top: 1px solid var(--brand-border);
            color: var(--brand-subtext);
            font-size: 13px;
            position: relative;
            z-index: 10;
            width: 100%;
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-links { display: flex; gap: 2rem; }
        .footer-links a { color: var(--brand-subtext); text-decoration: none; transition: color 0.2s; }
        .footer-links a:hover { color: var(--brand-text); }

        @media (max-width: 768px) {
            .nav-links { display: none; }
            .hero { padding-top: 8rem; }
            .footer-container { flex-direction: column; gap: 2rem; text-align: center; }
            .footer-links { justify-content: center; }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* 🎨 ============================================
           PREMIUM 3-SKIN THEME SWITCHER SYSTEM
           ============================================ */

        /* --- 🎨 Theme Switcher Nav Pill --- */
        .skin-switcher-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--brand-border);
            border-radius: 100px;
            padding: 4px;
            gap: 4px;
            box-shadow: inset 1px 1px 4px rgba(0,0,0,0.5);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            margin-right: 1.5rem;
        }
        .skin-btn {
            background: transparent;
            border: none;
            color: var(--brand-subtext);
            font-size: 0.65rem;
            font-weight: 800;
            padding: 6px 12px;
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .skin-btn:hover {
            color: var(--brand-text);
            background: rgba(255,255,255,0.02);
        }

        /* Highlight active theme buttons dynamically */
        body[data-theme="partner"] #skin-btn-partner {
            background: var(--brand-primary) !important;
            color: #000000 !important;
            box-shadow: 0 2px 10px rgba(255, 159, 10, 0.3) !important;
            font-weight: 900;
        }
        body[data-theme="consortium"] #skin-btn-consortium {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(245, 48, 3, 0.4) !important;
            font-weight: 900;
        }
        body[data-theme="retro"] #skin-btn-retro {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 900;
            border: 2px solid #000000 !important;
        }

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
        body[data-theme="partner"] .logo-mark {
            background: #ff9f0a !important;
            box-shadow: 0 0 15px rgba(255, 159, 10, 0.5) !important;
        }
        body[data-theme="partner"] .hero-badge {
            background: rgba(255, 159, 10, 0.1) !important;
            color: #ff9f0a !important;
            border-color: rgba(255, 159, 10, 0.2) !important;
        }
        body[data-theme="partner"] .btn-nav-cta {
            background: #ff9f0a !important;
            color: #000 !important;
            box-shadow: 0 4px 20px rgba(255, 159, 10, 0.3) !important;
        }
        body[data-theme="partner"] .btn-nav-cta:hover {
            box-shadow: 0 6px 25px rgba(255, 159, 10, 0.5) !important;
        }
        body[data-theme="partner"] .product-card::before {
            background: linear-gradient(90deg, #ff9f0a, #e65100) !important;
        }

        /* 🚩 Theme 2: Consortium Flagship (Flat Dark Neobrutalism) - DEFAULT */
        body[data-theme="consortium"] {
            --brand-primary: #f53003;
            --brand-bg: #030303;
            --brand-card: #090909;
            --brand-text: #ffffff;
            --brand-subtext: #8e8e93;
            --brand-border: rgba(255, 255, 255, 0.05);
            --brand-border-hover: rgba(245, 48, 3, 0.25);
            --glass-bg: rgba(3, 3, 3, 0.85);
            background: #030303 !important;
            font-family: 'JetBrains Mono', monospace !important;
        }
        body[data-theme="consortium"] .glow-1 {
            background: radial-gradient(circle, rgba(245, 48, 3, 0.08) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="consortium"] h1, 
        body[data-theme="consortium"] h2, 
        body[data-theme="consortium"] h3, 
        body[data-theme="consortium"] .logo, 
        body[data-theme="consortium"] .btn-nav-cta,
        body[data-theme="consortium"] .filter-btn,
        body[data-theme="consortium"] .btn-buy,
        body[data-theme="consortium"] .price-value {
            font-family: 'JetBrains Mono', monospace !important;
            letter-spacing: -0.01em !important;
        }
        body[data-theme="consortium"] .product-card::before {
            background: linear-gradient(90deg, #f53003, #ff7b00) !important;
        }

        /* 🍃 Theme 4: Nordic (Warm Eco / Scandinavian Minimalist - Ideal for Females, Calm/Nature Lovers) */
        body[data-theme="nordic"] {
            --brand-primary: #1e3f20; /* Sage / Deep Forest Green */
            --brand-bg: #faf7f2;      /* Warm Oat background */
            --brand-card: #ffffff;
            --brand-text: #2b2b2b;    /* Organic charcoal text */
            --brand-subtext: #6e706a;  /* Muted clay text */
            --brand-border: #e6dfd5;
            --brand-border-hover: rgba(30, 63, 32, 0.2);
            --glass-bg: rgba(250, 247, 242, 0.95);
            background: #faf7f2 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="nordic"] .glow-1,
        body[data-theme="nordic"] .glow-2 {
            display: none !important;
        }
        body[data-theme="nordic"] nav {
            background: #faf7f2 !important;
            border-bottom: 1px solid #e6dfd5 !important;
        }
        body[data-theme="nordic"] .logo {
            color: #1e3f20 !important;
        }
        body[data-theme="nordic"] .logo-mark {
            background: #1e3f20 !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 10px rgba(30, 63, 32, 0.15) !important;
        }
        body[data-theme="nordic"] .hero-badge {
            background: rgba(30, 63, 32, 0.05) !important;
            color: #1e3f20 !important;
            border: 1px solid rgba(30, 63, 32, 0.1) !important;
            border-radius: 100px !important;
        }
        body[data-theme="nordic"] .btn-nav-cta {
            background: #1e3f20 !important;
            color: #faf7f2 !important;
            border-radius: 8px !important;
            box-shadow: 0 4px 12px rgba(30, 63, 32, 0.2) !important;
        }
        body[data-theme="nordic"] .btn-nav-cta:hover {
            background: #152d16 !important;
        }
        body[data-theme="nordic"] .product-card::before {
            background: linear-gradient(90deg, #1e3f20, #426b45) !important;
        }

        /* 🟣 Theme 5: Synthwave (Retrofuturism / Pink-Purple Neon - Ideal for Young Gamers, Vibrant Personalities) */
        body[data-theme="synthwave"] {
            --brand-primary: #ff007f; /* Hot Pink */
            --brand-bg: #120e2e;      /* Deep Midnight Saturated Blue */
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
        body[data-theme="synthwave"] nav {
            background: #120e2e !important;
            border-bottom: 1px solid rgba(255, 0, 127, 0.2) !important;
        }
        body[data-theme="synthwave"] .logo {
            color: #ffffff !important;
            text-shadow: 0 0 8px rgba(0, 240, 255, 0.6) !important;
        }
        body[data-theme="synthwave"] .logo-mark {
            background: linear-gradient(135deg, #ff007f, #00f0ff) !important;
            border-radius: 8px !important;
            box-shadow: 0 0 15px rgba(255, 0, 127, 0.6) !important;
        }
        body[data-theme="synthwave"] .hero-badge {
            background: rgba(0, 240, 255, 0.1) !important;
            color: #00f0ff !important;
            border: 1px solid rgba(0, 240, 255, 0.3) !important;
            border-radius: 100px !important;
        }
        body[data-theme="synthwave"] .btn-nav-cta {
            background: #ff007f !important;
            color: #ffffff !important;
            border-radius: 4px !important;
            box-shadow: 0 0 15px rgba(255, 0, 127, 0.4) !important;
        }
        body[data-theme="synthwave"] .btn-nav-cta:hover {
            background: #e60072 !important;
            box-shadow: 0 0 25px rgba(255, 0, 127, 0.7) !important;
        }
        body[data-theme="synthwave"] .product-card::before {
            background: linear-gradient(90deg, #ff007f, #00f0ff) !important;
        }

        /* 🏁 Theme 6: Carbon (High-Performance Stealth / Motorsports Yellow - Ideal for Males, Tech Geeks) */
        body[data-theme="carbon"] {
            --brand-primary: #facc15; /* Motorsport Yellow */
            --brand-bg: #070708;      /* Stealth Matte Black */
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
        body[data-theme="carbon"] nav {
            background: #070708 !important;
            border-bottom: 2px solid #222226 !important;
        }
        body[data-theme="carbon"] h1, 
        body[data-theme="carbon"] h2, 
        body[data-theme="carbon"] h3, 
        body[data-theme="carbon"] .logo, 
        body[data-theme="carbon"] .btn-nav-cta,
        body[data-theme="carbon"] .filter-btn,
        body[data-theme="carbon"] .btn-buy,
        body[data-theme="carbon"] .price-value {
            font-family: 'JetBrains Mono', monospace !important;
            letter-spacing: -0.02em !important;
        }
        body[data-theme="carbon"] .logo-mark {
            background: #facc15 !important;
            border-radius: 4px !important;
            box-shadow: 0 4px 10px rgba(250, 204, 21, 0.2) !important;
        }
        body[data-theme="carbon"] .hero-badge {
            background: rgba(250, 204, 21, 0.08) !important;
            color: #facc15 !important;
            border: 1px solid rgba(250, 204, 21, 0.2) !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .btn-nav-cta {
            background: #facc15 !important;
            color: #000000 !important;
            font-weight: 900 !important;
            border-radius: 4px !important;
        }
        body[data-theme="carbon"] .btn-nav-cta:hover {
            background: #eab308 !important;
        }
        body[data-theme="carbon"] .product-card::before {
            background: linear-gradient(90deg, #facc15, #f59e0b) !important;
        }

        /* ⚡ Theme 3: Consortium Retro (Light Neo-Brutalism - Stark & Bold) */
        body[data-theme="retro"] {
            --brand-primary: #7c3aed;
            --brand-bg: #eef0fc;
            --brand-card: #ffffff;
            --brand-text: #000000;
            --brand-subtext: #4e4e5e;
            --brand-border: #000000;
            --brand-border-hover: #000000;
            --glass-bg: rgba(238, 240, 252, 0.95);
            background: #eef0fc !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="retro"] .glow-1,
        body[data-theme="retro"] .glow-2 {
            display: none !important;
        }
        body[data-theme="retro"] nav {
            background: #ffffff !important;
            border-bottom: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .logo {
            color: #000000 !important;
        }
        body[data-theme="retro"] .logo-mark {
            background: var(--brand-primary) !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: none !important;
        }
        body[data-theme="retro"] .skin-switcher-pill {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: none !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .skin-btn {
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .nav-links a {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .nav-links a.nav-link-b2b {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .nav-links a:hover {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .nav-links a.nav-link-b2b:hover {
            color: #000000 !important;
        }
        body[data-theme="retro"] .btn-nav-login {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .btn-nav-cta {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
            text-shadow: none !important;
        }
        body[data-theme="retro"] .btn-nav-cta:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .hero h1 {
            background: none !important;
            -webkit-text-fill-color: initial !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .hero-badge {
            background: color-mix(in srgb, var(--brand-primary) 10%, transparent) !important;
            color: var(--brand-primary) !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .store-section {
            background: transparent !important;
        }
        body[data-theme="retro"] .store-header {
            border-bottom: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .store-title h2,
        body[data-theme="retro"] .store-title p {
            color: #000000 !important;
        }
        body[data-theme="retro"] .filter-group {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            padding: 0.2rem !important;
        }
        body[data-theme="retro"] .filter-btn {
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .filter-btn.active,
        body[data-theme="retro"] .filter-btn:hover {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
        }
        body[data-theme="retro"] #storeSearch {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] #storeSearch::placeholder {
            color: #888888 !important;
        }
        body[data-theme="retro"] .product-card {
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            background: #ffffff !important;
            border-radius: 0px !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .product-card::before {
            display: none !important;
        }
        body[data-theme="retro"] .product-card:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 8px 8px 0px #000000 !important;
        }
        body[data-theme="retro"] .platform-badge {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .product-title {
            color: #000000 !important;
        }
        body[data-theme="retro"] .region-tag {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .price-section {
            border-top: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .price-label {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .price-value {
            color: #000000 !important;
        }
        body[data-theme="retro"] .btn-buy {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 3px 3px 0px #000000 !important;
        }
        body[data-theme="retro"] .btn-buy:hover {
            background: var(--brand-primary) !important;
            color: #ffffff !important;
        }
        body[data-theme="retro"] #features {
            background: #ffffff !important;
            border-top: 2px solid #000000 !important;
            border-bottom: 2px solid #000000 !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] #features h2,
        body[data-theme="retro"] #features h3,
        body[data-theme="retro"] #features p {
            color: #000000 !important;
        }
        body[data-theme="retro"] footer {
            background: #ffffff !important;
            border-top: 2px solid #000000 !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .footer-links a {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .footer-links a:hover {
            color: var(--brand-primary) !important;
        }
        body[data-theme="retro"] .checkout-modal {
            background: #ffffff !important;
            color: #000000 !important;
            border: 3px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 10px 10px 0px #000000 !important;
        }
        body[data-theme="retro"] .checkout-desc {
            color: #4e4e5e !important;
        }

        /* 🦁 Easter Egg: Son's Birthday (May 19) - Albiceleste */
        body[data-holiday="sons-birthday"] {
            --brand-primary: #74acdf !important; /* Argentine Sky Blue */
            --brand-border-hover: rgba(116, 172, 223, 0.45) !important;
        }
        body[data-holiday="sons-birthday"] .logo-mark {
            background: linear-gradient(135deg, #74acdf 0%, #ffffff 100%) !important; /* Albiceleste gradient! */
            box-shadow: 0 0 15px rgba(116, 172, 223, 0.55) !important;
        }
        body[data-holiday="sons-birthday"] .product-card::before {
            background: linear-gradient(90deg, #74acdf, #ffffff, #74acdf) !important; /* Albiceleste! */
        }
        body[data-holiday="sons-birthday"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="sons-birthday"]:not([data-theme="retro"]) .btn-nav-cta {
            background: #74acdf !important; /* Albiceleste Sky Blue! */
            color: #ffffff !important;
            border-color: #ffffff !important;
            box-shadow: 0 4px 15px rgba(116, 172, 223, 0.45) !important;
        }

        /* 🌸 Easter Egg: Orchid Day (May 12) - Beautiful Orchid Purple & Violet Theme */
        body[data-holiday="orchid-day"] {
            --brand-primary: #d946ef !important; /* Orchid Magenta */
            --brand-border-hover: rgba(217, 70, 239, 0.45) !important;
        }
        body[data-holiday="orchid-day"] .logo-mark {
            background: linear-gradient(135deg, #d946ef 0%, #c084fc 100%) !important;
            box-shadow: 0 0 15px rgba(217, 70, 239, 0.5) !important;
        }
        body[data-holiday="orchid-day"] .product-card::before {
            background: linear-gradient(90deg, #d946ef, #c084fc, #e879f9) !important;
        }
        body[data-holiday="orchid-day"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="orchid-day"]:not([data-theme="retro"]) .btn-nav-cta {
            background: linear-gradient(135deg, #d946ef 0%, #86198f 100%) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(217, 70, 239, 0.35) !important;
        }

        /* 🩺 Easter Egg: Doctor's Day / Stethoscope Day (April 21) - Healing Mint & Cyan Theme */
        body[data-holiday="doctor-day"] {
            --brand-primary: #06b6d4 !important; /* Healing Cyan */
            --brand-border-hover: rgba(6, 182, 212, 0.45) !important;
        }
        body[data-holiday="doctor-day"] .logo-mark {
            background: linear-gradient(135deg, #0d9488 0%, #06b6d4 100%) !important;
            box-shadow: 0 0 15px rgba(13, 148, 136, 0.5) !important;
        }
        body[data-holiday="doctor-day"] .product-card::before {
            background: linear-gradient(90deg, #0d9488, #06b6d4, #10b981, #2dd4bf) !important;
        }
        body[data-holiday="doctor-day"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="doctor-day"]:not([data-theme="retro"]) .btn-nav-cta {
            background: linear-gradient(135deg, #0d9488 0%, #115e59 100%) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(13, 148, 136, 0.35) !important;
        }

        /* 📚 Easter Egg: Library of Babel Day (Jorge Luis Borges' Birthday - August 24) - Antique Amber & Parchment Theme */
        body[data-holiday="babel-library"] {
            --brand-primary: #d97706 !important; /* Antique Amber */
            --brand-border-hover: rgba(217, 119, 6, 0.45) !important;
        }
        body[data-holiday="babel-library"] .logo-mark {
            background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%) !important;
            box-shadow: 0 0 15px rgba(180, 83, 9, 0.5) !important;
        }
        body[data-holiday="babel-library"] .product-card::before {
            background: linear-gradient(90deg, #b45309, #d97706, #f59e0b, #78350f) !important;
        }
        body[data-holiday="babel-library"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="babel-library"]:not([data-theme="retro"]) .btn-nav-cta {
            background: linear-gradient(135deg, #b45309 0%, #78350f 100%) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(180, 83, 9, 0.35) !important;
        }

        /* 💕 Easter Egg: Valentine's Day (Feb 14) - Premium Sweet Pink & Crimson Red Theme */
        body[data-holiday="valentine"] {
            --brand-primary: #e11d48 !important; /* Crimson Rose */
            --brand-border-hover: rgba(225, 29, 72, 0.45) !important;
        }
        body[data-holiday="valentine"] .logo-mark {
            background: linear-gradient(135deg, #ff4d6d 0%, #ff758f 100%) !important;
            box-shadow: 0 0 15px rgba(255, 77, 109, 0.5) !important;
        }
        body[data-holiday="valentine"] .product-card::before {
            background: linear-gradient(90deg, #ff4d6d, #ff758f, #ffccd5, #ff85a1) !important;
        }
        body[data-holiday="valentine"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="valentine"]:not([data-theme="retro"]) .btn-nav-cta {
            background: linear-gradient(135deg, #ff4d6d 0%, #c9184a 100%) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(255, 77, 109, 0.4) !important;
        }

        /* 🌹 Easter Egg: The Little Prince (Oct 17) - Single Rose under Glass Dome & Stars */
        body[data-holiday="little-prince"] {
            --brand-primary: #e11d48 !important; /* Rose Red */
            --brand-bg: #0b0f19 !important; /* Twilight indigo space dark */
            --brand-card: rgba(17, 24, 39, 0.65) !important;
            --brand-border: rgba(255, 255, 255, 0.08) !important;
            --brand-border-hover: rgba(225, 29, 72, 0.35) !important;
            background: #0b0f19 !important;
        }
        body[data-holiday="little-prince"] .logo-mark {
            background: linear-gradient(135deg, #e11d48 0%, #fbbf24 100%) !important;
            box-shadow: 0 0 15px rgba(225, 29, 72, 0.55) !important;
        }
        body[data-holiday="little-prince"] .product-card::before {
            background: linear-gradient(90deg, #f59e0b, #be123c, #e11d48) !important; /* Golden stars & deep rose red! */
        }
        body[data-holiday="little-prince"]:not([data-theme="retro"]) .btn-buy, 
        body[data-holiday="little-prince"]:not([data-theme="retro"]) .btn-nav-cta {
            background: linear-gradient(135deg, #be123c 0%, #e11d48 100%) !important; /* Rose Red buttons */
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(225, 29, 72, 0.35) !important;
        }

        /* 🌹 Glass Dome Floating Widget */
        .little-prince-dome {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 130px;
            height: 180px;
            z-index: 10000;
            cursor: pointer;
            pointer-events: auto;
            animation: domeFloat 4.5s ease-in-out infinite;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            filter: drop-shadow(0 10px 25px rgba(225, 29, 72, 0.18));
            display: none;
        }
        body[data-holiday="little-prince"] .little-prince-dome {
            display: block !important;
        }
        .little-prince-dome:hover {
            transform: scale(1.12) translateY(-5px);
            filter: drop-shadow(0 15px 35px rgba(225, 29, 72, 0.38));
        }
        .dome-svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 0 12px rgba(225, 29, 72, 0.2));
        }
        .dome-glow {
            position: absolute;
            inset: 15px;
            background: radial-gradient(circle, rgba(225, 29, 72, 0.2) 0%, rgba(225,29,72,0) 70%);
            pointer-events: none;
            z-index: -1;
            mix-blend-mode: screen;
            animation: rosePulse 3.5s ease-in-out infinite;
        }
        .dome-tooltip {
            position: absolute;
            bottom: 105%;
            right: 0;
            width: 240px;
            background: rgba(11, 15, 25, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            color: #ffffff;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            text-align: center;
            line-height: 1.4;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }
        .little-prince-dome:hover .dome-tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dome-sparkle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #ffd700;
            border-radius: 50%;
            box-shadow: 0 0 8px #ffd700;
            pointer-events: none;
            animation: domeSparkle 3s ease-in-out infinite;
        }
        @keyframes domeFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        @keyframes rosePulse {
            0%, 100% { opacity: 0.6; transform: scale(0.92); }
            50% { opacity: 1; transform: scale(1.12); }
        }
        @keyframes domeSparkle {
            0%, 100% { transform: scale(0) translateY(0); opacity: 0; }
            50% { transform: scale(1) translateY(-18px); opacity: 1; }
        }
    </style>
@include('partials.theme-sync-body')
<body data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>

<!-- 🌹 Easter Egg Widget: Rose under Glass Dome (The Little Prince) -->
<div class="little-prince-dome" id="littlePrinceDome">
    <div class="dome-tooltip">«Ты навсегда в ответе за тех, кого приручил. Твоя Роза.» 🌹</div>
    <svg viewBox="0 0 100 140" class="dome-svg">
        <ellipse cx="50" cy="120" rx="35" ry="10" fill="#4a2c11" stroke="#2c1a0a" stroke-width="1.5"/>
        <ellipse cx="50" cy="118" rx="32" ry="8" fill="#6d421e"/>
        <path d="M 42 116 Q 46 113 49 116 Q 45 119 42 116" fill="#e11d48" opacity="0.9"/>
        <path d="M 50 118 Q 48 95 50 75" fill="none" stroke="#166534" stroke-width="2.5" stroke-linecap="round"/>
        <path d="M 49 100 Q 40 98 44 92 Q 49 96 49 100" fill="#15803d"/>
        <path d="M 50 88 Q 58 87 55 81 Q 50 84 50 88" fill="#15803d"/>
        <ellipse cx="50" cy="70" rx="7" ry="10" fill="#be123c"/>
        <path d="M 44 73 C 40 65, 46 58, 50 63 C 54 58, 60 65, 56 73 C 50 78, 50 78, 44 73 Z" fill="#e11d48"/>
        <path d="M 47 72 C 45 68, 48 64, 50 66 C 52 64, 55 68, 53 72 Z" fill="#f43f5e"/>
        <path d="M 22 118 L 22 60 A 28 28 0 0 1 78 60 L 78 118 Z" fill="rgba(255, 255, 255, 0.08)" stroke="rgba(255, 255, 255, 0.35)" stroke-width="1.5" stroke-linejoin="round"/>
        <path d="M 28 110 L 28 60 A 22 22 0 0 1 50 38" fill="none" stroke="rgba(255, 255, 255, 0.25)" stroke-width="1.5" stroke-linecap="round"/>
        <circle cx="50" cy="30" r="4.5" fill="rgba(255, 255, 255, 0.4)" stroke="rgba(255, 255, 255, 0.6)" stroke-width="1"/>
    </svg>
    <div class="dome-glow"></div>
    <div class="dome-sparkle" style="top: 40%; left: 30%; animation-delay: 0s;"></div>
    <div class="dome-sparkle" style="top: 60%; left: 70%; animation-delay: 1.2s;"></div>
    <div class="dome-sparkle" style="top: 80%; left: 45%; animation-delay: 2.4s;"></div>
</div>

<div class="ambient-glows">
    <div class="glow-1"></div>
    <div class="glow-2"></div>
</div>
 
@include('storefront.partials.header')
 
<section class="hero">
    <div class="hero-badge">
        <i class="ph-fill ph-shield-checkered"></i> Sovereign Cryptographic Network
    </div>
    <h1>Магазин Цифровых Активов нового поколения</h1>
    <p class="subtitle">Покупайте лицензионные карты пополнения баланса, игровые ключи и подписки напрямую по честным региональным ценам.</p>
    
    <div class="hero-actions">
        <a href="#retail" class="btn-nav-cta" style="font-size: 15px; padding: 0.8rem 2rem;">Перейти к витрине ↓</a>
    </div>
</section>
 
<section class="store-section" id="retail">
    <div class="store-header">
        <div class="store-title">
            <h2>Витрина товаров</h2>
            <p>Выберите платформу и желаемый регион активации</p>
        </div>

        <div class="filter-group">
            <button class="filter-btn active" onclick="filterPlatform('all')">Все</button>
            @php
                $userBrands = [];
                if (Auth::check()) {
                    // Try to get unique platforms user has purchased
                    try {
                        $items = \App\Models\Order\OrderItems::whereHas('order', function($q) {
                            $q->where('user_id', Auth::id());
                        })->with('game')->get();
                        
                        foreach ($items as $item) {
                            $vendor = strtolower($item->game?->vendor ?? '');
                            $name = strtolower($item->game?->name ?? $item->sku ?? '');
                            $full = $vendor . ' ' . $name;
                            
                            if (str_contains($full, 'steam')) $userBrands['steam'] = true;
                            if (str_contains($full, 'playstation') || str_contains($full, 'psn')) $userBrands['playstation'] = true;
                            if (str_contains($full, 'xbox') || str_contains($full, 'microsoft')) $userBrands['xbox'] = true;
                            if (str_contains($full, 'spotify')) $userBrands['spotify'] = true;
                        }
                    } catch (\Exception $e) {}
                }
            @endphp

            @foreach(['steam' => 'Steam', 'playstation' => 'PlayStation', 'xbox' => 'Xbox', 'spotify' => 'Spotify'] as $key => $name)
                <button class="filter-btn" onclick="filterPlatform('{{ $key }}')">
                    @if(isset($userBrands[$key])) 
                        <i class="ph-fill ph-star" style="color: var(--brand-primary); margin-right: 4px;"></i> 
                    @endif 
                    {{ $name }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- 🔍 Real-time Search Input -->
    <div style="margin-bottom: 3rem; width: 100%; position: relative;">
        <input type="text" id="storeSearch" placeholder="Поиск среди 12 000+ товаров по названию, SKU или платформе..." oninput="debounceSearch(this.value)" style="width: 100%; height: 52px; background: var(--theme-surface-muted, rgba(255,255,255,0.02)); border: 1px solid var(--brand-border); border-radius: 12px; padding: 0 1.5rem 0 3.5rem; color: var(--brand-text); font-size: 15px; outline: none; transition: all 0.2s; font-family: 'Outfit', sans-serif;">
        <i class="ph-bold ph-magnifying-glass" style="position: absolute; left: 1.3rem; top: 1.1rem; color: var(--brand-subtext); font-size: 20px;"></i>
    </div>

    <!-- Dynamic Grid -->
    <div class="product-grid">
        <!-- Products will load dynamically via AJAX -->
    </div>

    <!-- Paginator: Load More -->
    <div style="text-align: center; margin-top: 4rem;">
        <button id="loadMoreBtn" onclick="loadNextPage()" class="btn-nav-cta" style="font-size: 14px; padding: 0.8rem 2.5rem; display: none; align-items: center; gap: 0.5rem; border: none; cursor: pointer; margin: 0 auto;">
            <i class="ph-bold ph-arrow-down"></i> Загрузить еще
        </button>
    </div>
</section>

<section id="features" style="background: rgba(255,255,255,0.01); border-top: 1px solid var(--brand-border); border-bottom: 1px solid var(--brand-border); padding: 6rem 1.5rem;">
    <div style="max-width: 1200px; margin: 0 auto;">
        <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 4rem; letter-spacing: -0.03em; text-align: center;">Надежная покупка и быстрая выдача</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 4rem;">
            <div>
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 1rem; color: var(--brand-text); text-transform: uppercase; letter-spacing: 0.05em;"><i class="ph-bold ph-cube" style="color: var(--brand-primary); margin-right: 0.5rem;"></i> История заказа</h3>
                <p style="font-size: 14px; color: var(--brand-subtext); line-height: 1.6;">Каждая покупка и выдача кода сохраняется в понятной истории. Если понадобится помощь, поддержка быстро найдет нужный заказ.</p>
            </div>
            <div>
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 1rem; color: var(--brand-text); text-transform: uppercase; letter-spacing: 0.05em;"><i class="ph-bold ph-lightning" style="color: var(--brand-primary); margin-right: 0.5rem;"></i> Мгновенная доставка</h3>
                <p style="font-size: 14px; color: var(--brand-subtext); line-height: 1.6;">После оплаты цифровой ваучер мгновенно доставляется в ваш личный сейф. Никакого ожидания и ручных проверок.</p>
            </div>
            <div>
                <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 1rem; color: var(--brand-text); text-transform: uppercase; letter-spacing: 0.05em;"><i class="ph-bold ph-fingerprint" style="color: var(--brand-primary); margin-right: 0.5rem;"></i> Беспарольная аутентификация</h3>
                <p style="font-size: 14px; color: var(--brand-subtext); line-height: 1.6;">Забудьте про взломы аккаунтов. Вход в личный кабинет защищен технологией Passkey (Touch ID / Face ID) на аппаратном уровне вашего устройства.</p>
            </div>
        </div>
    </div>
</section>
 
<footer>
    <div class="footer-container">
        <div>&copy; {{ date('Y') }} Meanly Systems. Цифровые покупки в личном сейфе.</div>
        <div class="footer-links">
            <a href="#">Условия использования</a>
            <a href="#">Конфиденциальность</a>
            <a href="#">Документация API</a>
        </div>
    </div>
</footer>

<!-- ── CHECKOUT MODAL ── -->
<div class="modal-overlay" id="checkoutModal" onclick="closeCheckout()">
    <div class="checkout-modal" onclick="event.stopPropagation()">
        <button class="close-btn" onclick="closeCheckout()"><i class="ph-bold ph-x"></i></button>
        <div class="checkout-icon">
            <i class="ph-bold ph-fingerprint"></i>
        </div>
        <h3 class="checkout-title">Войдите для покупки</h3>
        <p class="checkout-desc" id="checkoutDesc">Чтобы совершить покупку, войдите в личный аккаунт. С Passkey это занимает пару секунд.</p>
        
        <a href="/login" class="btn-nav-cta" style="display: flex; align-items: center; justify-content: center; gap: 0.6rem; font-size: 14px; padding: 0.8rem 2rem;">
            Войти и оплатить ➔
        </a>
    </div>
</div>

<script>
    let currentPage = 1;
    let currentPlatform = 'all';
    let searchQuery = '';
    let hasMore = false;
    let loading = false;

    // Debounce search input to avoid hitting database on every keystroke
    let searchTimeout = null;
    function debounceSearch(val) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            searchQuery = val;
            currentPage = 1;
            loadProducts(true);
        }, 300);
    }

    function filterPlatform(platform) {
        // Toggle active button class
        const buttons = document.querySelectorAll('.filter-btn');
        buttons.forEach(btn => {
            const btnText = btn.textContent.toLowerCase();
            if (btnText === platform || (platform === 'all' && btnText === 'все')) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        currentPlatform = platform;
        currentPage = 1;
        loadProducts(true);
    }

    function getPlatformMeta(brandName, productName) {
        const name = ((brandName || '') + ' ' + (productName || '')).toUpperCase();
        if (name.includes('STEAM')) {
            return {
                gradient: 'linear-gradient(90deg, #1b2838, #66c0f4)',
                glow: 'rgba(102, 192, 244, 0.05)',
                color: '#66c0f4',
                icon: 'ph-steam-logo',
                badgeName: 'Steam'
            };
        } else if (name.includes('SPOTIFY')) {
            return {
                gradient: 'linear-gradient(90deg, #1db954, #1ed760)',
                glow: 'rgba(30, 215, 96, 0.05)',
                color: '#1db954',
                icon: 'ph-spotify-logo',
                badgeName: 'Spotify'
            };
        } else if (name.includes('PLAYSTATION') || name.includes('PSN') || name.includes('PS PLUS')) {
            return {
                gradient: 'linear-gradient(90deg, #003087, #0072ce)',
                glow: 'rgba(0, 114, 206, 0.05)',
                color: '#0072ce',
                icon: 'ph-game-controller',
                badgeName: 'PlayStation'
            };
        } else if (name.includes('XBOX') || name.includes('MICROSOFT')) {
            return {
                gradient: 'linear-gradient(90deg, #107c10, #109d10)',
                glow: 'rgba(16, 157, 16, 0.05)',
                color: '#109d10',
                icon: 'ph-xbox-logo',
                badgeName: 'Xbox'
            };
        }
        return {
            gradient: 'linear-gradient(90deg, #f53003, #ff7b00)',
            glow: 'rgba(245, 48, 3, 0.05)',
            color: '#f53003',
            icon: 'ph-cube',
            badgeName: 'Digital Key'
        };
    }

    async function loadProducts(clearGrid = false) {
        if (loading) return;
        loading = true;
        
        const grid = document.querySelector('.product-grid');
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        
        if (clearGrid) {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: var(--brand-subtext);"><i class="ph-bold ph-spinner-gap" style="font-size: 2rem; animation: spin 1s linear infinite; display: inline-block;"></i><p style="margin-top: 1rem; font-size: 14px;">Поиск товаров...</p></div>';
            if (loadMoreBtn) loadMoreBtn.style.display = 'none';
        }
        
        try {
            const response = await fetch(`/products-search?query=${encodeURIComponent(searchQuery)}&platform=${encodeURIComponent(currentPlatform)}&page=${currentPage}`);
            const data = await response.json();
            
            if (clearGrid) {
                grid.innerHTML = '';
            }
            
            if (data.products.length === 0 && clearGrid) {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: var(--brand-subtext);"><i class="ph-bold ph-ghost" style="font-size: 2rem; display: inline-block;"></i><p style="margin-top: 1rem; font-size: 14px;">Товары не найдены</p></div>';
                loading = false;
                return;
            }
            
            data.products.forEach(product => {
                const meta = getPlatformMeta(product.vendor, product.name);
                
                // Format price: divide by 100
                const rubPrice = Math.round(product.price_rub / 100);
                const formattedPrice = new Intl.NumberFormat('ru-RU').format(rubPrice) + ' ₽';
                
                const card = document.createElement('a');
                card.href = `/products/${product.slug}`;
                card.className = 'product-card';
                card.setAttribute('data-platform', meta.badgeName.toLowerCase());
                card.style.setProperty('--platform-gradient', meta.gradient);
                card.style.setProperty('--platform-glow', meta.glow);
                card.style.setProperty('--platform-color', meta.color);
                card.style.textDecoration = 'none';
                
                card.innerHTML = `
                    <div>
                        <div class="platform-badge"><i class="ph-bold ${meta.icon}"></i> ${meta.badgeName}</div>
                        <h3 class="product-title">${product.name}</h3>
                        <div class="product-meta">
                            <span class="region-tag">${product.vendor ?? 'Digital'}</span>
                            <span class="region-tag">${product.category ?? 'Ключ'}</span>
                        </div>
                    </div>
                    <div>
                        <div class="price-section">
                            <span class="price-label">Цена в рублях</span>
                            <span class="price-value">${formattedPrice}</span>
                        </div>
                        <button class="btn-buy" style="pointer-events: none;">
                            <i class="ph-bold ph-shopping-cart-simple"></i> Подробнее
                        </button>
                    </div>
                `;
                grid.appendChild(card);
            });
            
            hasMore = data.has_more;
            if (loadMoreBtn) {
                loadMoreBtn.style.display = hasMore ? 'inline-flex' : 'none';
            }
            
        } catch (err) {
            console.error('Error loading products:', err);
            if (clearGrid) {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: var(--brand-primary);"><i class="ph-bold ph-warning" style="font-size: 2rem; display: inline-block;"></i><p style="margin-top: 1rem; font-size: 14px;">Ошибка загрузки витрины</p></div>';
            }
        } finally {
            loading = false;
        }
    }

    function loadNextPage() {
        if (!hasMore || loading) return;
        currentPage++;
        loadProducts(false);
    }

    // Initial Load
    document.addEventListener('DOMContentLoaded', () => {
        loadProducts(true);
    });

    function openCheckout(productName) {
        @auth
            window.location.href = '/vault';
        @else
            document.getElementById('checkoutDesc').innerText = `Для мгновенного оформления заказа на "${productName}" авторизуйтесь в суверенном личном кабинете через Passkey.`;
            document.getElementById('checkoutModal').classList.add('active');
        @endauth
    }

    function closeCheckout() {
        document.getElementById('checkoutModal').classList.remove('active');
    }

    // 🎨 Premium Theme/Skin Switcher
    function setTheme(theme) {
        if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
            theme = window.MeanlyTheme.apply(theme);
        }
        document.body.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
        var cookieDomain = @json(config('session.domain') ?? null);
        var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
        document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax${domainSuffix}`;
        
        // Update active class on switcher buttons
        document.querySelectorAll('.skin-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.getElementById(`skin-btn-${theme}`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }
    }

    // 🧠 Cognitive Demographic & Heuristic Default Theme Predictor
    function getCognitiveDemographicDefaultTheme() {
        try {
            // 1. Detect Locale/Region
            const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
            const isCIS = /Moscow|Europe\/Moscow|Samara|Yekaterinburg|Novosibirsk|Asia\/Almaty|Asia\/Tashkent|Asia\/Baku|Europe\/Minsk|ru|ru-RU/i.test(timeZone + navigator.language);
            
            // 2. Detect Device Capabilities (Proxy for Generation / Age / Hacker profile)
            const hasTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
            const isHighDPI = window.devicePixelRatio && window.devicePixelRatio > 1.5;
            
            // Check for WebGPU (highly indicative of Gen Z bleeding-edge gamer/creator rigs)
            const supportsWebGPU = !!navigator.gpu;
            
            // Check for older/desktop developer setups (Retro lovers)
            const isLinuxOrOldOS = /Linux|Ubuntu|Debian|Windows NT 6.1|Windows NT 5.1/i.test(navigator.userAgent);
            const lacksModernGpu = !supportsWebGPU && !window.WebGL2RenderingContext;

            // 3. System Theme preference
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const currentHour = new Date().getHours();

            console.log(`[Cognitive Engine] TZ: ${timeZone}, Touch: ${hasTouch}, WebGPU: ${supportsWebGPU}, PrefersDark: ${prefersDark}, Hour: ${currentHour}`);

            // 4. Expanded Demographic Heuristics Decision Tree
            
            // A. Light System / Organic / Calm preferences (Nordic)
            if (!prefersDark || /Stockholm|Oslo|Copenhagen|Helsinki|Europe\/London|Europe\/Paris/i.test(timeZone)) {
                console.log("[Cognitive Choice] Matched calming light/organic profile -> NORDIC theme 🍃");
                return 'nordic';
            }
            
            // B. Retro technical profile / Gen X / old-school geeks (Retro)
            if (isLinuxOrOldOS || lacksModernGpu) {
                console.log("[Cognitive Choice] Matched old-school technical profile -> RETRO theme ⚡");
                return 'retro';
            }
            
            // C. Gamers, creative night owls, neon-futurism (Synthwave)
            if (supportsWebGPU && (currentHour >= 18 || currentHour <= 4)) {
                console.log("[Cognitive Choice] Matched late-night creative gamer -> SYNTHWAVE theme 🟣");
                return 'synthwave';
            }
            
            // D. High-performance desktop geeks / pure performance (Carbon)
            if (prefersDark && !hasTouch && isHighDPI) {
                console.log("[Cognitive Choice] Matched high-performance minimalist developer -> CARBON theme 🏁");
                return 'carbon';
            }
            
            // E. Mobile-first digital creators (Partner)
            if (hasTouch && isHighDPI) {
                console.log("[Cognitive Choice] Matched mobile digital creator -> PARTNER theme 🌟");
                return 'partner';
            }
            
            // F. Premium B2B Executive (Consortium)
            console.log("[Cognitive Choice] Matched flagship executive profile -> CONSORTIUM theme 🚩");
            return 'consortium';
        } catch (e) {
            console.warn("[Cognitive Engine] Failed to compute heuristics, falling back to Consortium flagship.", e);
            return 'consortium';
        }
    }

    // 📆 Holiday Detection Logic
    function getActiveHoliday() {
        return document.body.getAttribute('data-holiday') || null;
    }

    // 🎭 Sovereign Atmospheric Holiday & Context Effects Engine
    function initAtmosphericHolidayFX(holidayOverride) {
        const holiday = holidayOverride || getActiveHoliday();
        if (!holiday) return;

        // Set body attribute for CSS overrides
        document.body.setAttribute('data-holiday', holiday);
        
        if (holiday === 'sons-birthday') {
            console.log("%c🦁 [Sovereign Heir Engine] 19 MAY: Happy Birthday to the Champion! С Днём Рождения, Сына! Расти сильным, смелым и свободным! 👑🏆⚡", "color: #ffd700; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'little-prince') {
            console.log("%c🌹 [Little Prince Engine] 17 OCTOBER: \"Ты навсегда в ответе за тех, кого приручил.\" Твоя единственная Роза. 💫🌠", "color: #e11d48; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'orchid-day') {
            console.log("%c🌸 [Orchid Engine] 12 MAY: В воздухе парит изысканность... С Днём Орхидей! 🌺💫", "color: #d946ef; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'doctor-day') {
            console.log("%c🩺 [Doctor Engine] 21 APRIL: Слышим каждое биение сердца... С Днём Врача! 💚⚕️", "color: #0d9488; font-weight: bold; font-size: 14px;");
        } else if (holiday === 'babel-library') {
            console.log("%c📚 [Library of Babel] 24 AUGUST: \"La Biblioteca es ilimitada y periódica...\" / \"The Library is limitless and periodic...\" 🌌🚪", "color: #b45309; font-weight: bold; font-size: 14px;");
        } else {
            console.log(`[Holiday Engine] Active Festive Period: ${holiday.toUpperCase()} 🎁`);
        }

        // Create canvas element
        const canvas = document.createElement('canvas');
        canvas.id = 'holiday-canvas-fx';
        Object.assign(canvas.style, {
            position: 'fixed',
            inset: '0',
            pointerEvents: 'none',
            zIndex: '1',
            opacity: '0.65'
        });
        document.body.appendChild(canvas);

        const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;

        window.addEventListener('resize', () => {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        });

        const particles = [];
        const maxParticles = 60;

        class Particle {
            constructor() {
                this.reset();
            }

            reset() {
                this.x = Math.random() * width;
                const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day' || holiday === 'babel-library');
                this.y = isFloatingUp ? height + 25 : -25;
                this.type = Math.floor(Math.random() * 12); // Stable random type assigned on reset
                // Stable Babel character — assigned once on reset, never changes mid-flight
                const _babelAlphabet = "abcdefghijklmnopqrstuvwxyz,.";
                this.babelChar = _babelAlphabet[Math.floor(Math.random() * _babelAlphabet.length)];
                
                if (isFloatingUp) {
                    this.size = Math.random() * 12 + 10; // Majestic 10px to 22px size!
                    this.speedX = Math.random() * 0.2 - 0.1;
                    this.speedY = -(Math.random() * 0.45 + 0.25); // Gentle slow float upwards!
                    this.alpha = Math.random() * 0.3 + 0.7; // Bright and crisp visibility
                    this.angle = Math.random() * Math.PI * 2;
                    this.spin = Math.random() * 0.012 - 0.006; // Calm majestic rotation
                } else {
                    this.size = Math.random() * 4 + 2;
                    this.speedX = holiday === 'womens-day' ? Math.random() * 1.5 - 0.2 : Math.random() * 1 - 0.5;
                    this.speedY = Math.random() * 1 + 0.8;
                    this.alpha = Math.random() * 0.6 + 0.4;
                    this.angle = Math.random() * Math.PI * 2;
                    this.spin = Math.random() * 0.04 - 0.02;
                }
            }

            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                this.angle += this.spin;

                const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day');

                if (isFloatingUp) {
                    // Beautiful sinusoidal sway (fluttering float)
                    this.x += Math.sin(this.y / 35) * 0.35;
                }

                if (isFloatingUp) {
                    if (this.y < -25 || this.x < -25 || this.x > width + 25) this.reset();
                } else {
                    if (this.y > height + 25 || this.x < -25 || this.x > width + 25) this.reset();
                }
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.translate(this.x, this.y);
                ctx.rotate(this.angle);

                if (holiday === 'christmas') {
                    // Draw snowflake
                    ctx.fillStyle = '#ffffff';
                    ctx.beginPath();
                    ctx.arc(0, 0, this.size, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'valentine') {
                    // Draw premium colorful hearts
                    const heartColors = ['#ff4d6d', '#ff758f', '#ff85a1', '#c9184a', '#ffccd5'];
                    const colorIndex = Math.floor(Math.abs(this.x + this.y)) % heartColors.length;
                    ctx.fillStyle = heartColors[colorIndex];
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.bezierCurveTo(-this.size, -this.size, -this.size * 2, this.size / 3, 0, this.size * 1.5);
                    ctx.bezierCurveTo(this.size * 2, this.size / 3, this.size, -this.size, 0, 0);
                    ctx.fill();
                } else if (holiday === 'womens-day') {
                    // Draw flower petal
                    ctx.fillStyle = '#ffb7c5'; // Soft sakura pink
                    ctx.beginPath();
                    ctx.ellipse(0, 0, this.size * 1.5, this.size, Math.PI / 4, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'halloween') {
                    // Draw embers
                    ctx.fillStyle = '#ff6600';
                    ctx.beginPath();
                    ctx.arc(0, 0, this.size * 1.2, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'black-friday') {
                    // Draw neon glitch segment
                    ctx.strokeStyle = '#39ff14'; // Cyber green
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(0, 0);
                    ctx.lineTo(0, this.size * 5);
                    ctx.stroke();
                } else if (holiday === 'sons-birthday') {
                    // Render Swiss flags, Argentine flags (with Sol de Mayo), standalone Suns, cute Hippo, and Golden stars with Alejandro 👑!
                    const particleType = this.type % 5;
                    const scale = this.size * 1.35;

                    if (particleType === 0) {
                        // 1. Swiss Flag (Швейцарский флаг)
                        ctx.fillStyle = '#da291c';
                        ctx.fillRect(-scale, -scale, scale * 2, scale * 2);
                        ctx.fillStyle = '#ffffff';
                        const barW = scale * 0.4;
                        const barH = scale * 1.3;
                        ctx.fillRect(-barW / 2, -barH / 2, barW, barH);
                        ctx.fillRect(-barH / 2, -barW / 2, barH, barW);
                    } else if (particleType === 1) {
                        // 2. Argentine Flag (Аргентинский флаг с прорисованным Солнцем!)
                        const w = scale * 1.8;
                        const h = scale * 1.2;
                        ctx.fillStyle = '#74acdf';
                        ctx.fillRect(-w / 2, -h / 2, w, h / 3);
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(-w / 2, -h / 2 + h / 3, w, h / 3);
                        ctx.fillStyle = '#74acdf';
                        ctx.fillRect(-w / 2, -h / 2 + (h / 3) * 2, w, h / 3);
                        
                        // Sun center
                        ctx.fillStyle = '#f6b40e';
                        ctx.beginPath();
                        ctx.arc(0, 0, h * 0.12, 0, Math.PI * 2);
                        ctx.fill();
                        
                        // Miniature Sun rays
                        ctx.strokeStyle = '#f6b40e';
                        ctx.lineWidth = h * 0.04;
                        for (let r = 0; r < 8; r++) {
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            const rx = Math.cos(r * Math.PI / 4) * h * 0.22;
                            const ry = Math.sin(r * Math.PI / 4) * h * 0.22;
                            ctx.lineTo(rx, ry);
                            ctx.stroke();
                        }
                    } else if (particleType === 2) {
                        // 3. Standalone Sol de Mayo (Солнце Аргентины)
                        const rSun = scale * 0.45;
                        ctx.fillStyle = '#f6b40e';
                        ctx.beginPath();
                        ctx.arc(0, 0, rSun, 0, Math.PI * 2);
                        ctx.fill();

                        ctx.strokeStyle = '#f6b40e';
                        ctx.lineWidth = scale * 0.12;
                        for (let r = 0; r < 12; r++) {
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            const rx = Math.cos(r * Math.PI / 6) * scale * 1.1;
                            const ry = Math.sin(r * Math.PI / 6) * scale * 1.1;
                            ctx.lineTo(rx, ry);
                            ctx.stroke();
                        }
                    } else if (particleType === 3) {
                        // 4. Cute Vector Hippo (Бегемотик)
                        // Head
                        ctx.fillStyle = '#a5b4fc'; // Cute indigo/lilac color
                        ctx.beginPath();
                        ctx.arc(0, -scale * 0.1, scale * 0.5, 0, Math.PI * 2);
                        ctx.fill();

                        // Snout (large lower oval)
                        ctx.fillStyle = '#818cf8';
                        ctx.beginPath();
                        ctx.ellipse(0, scale * 0.2, scale * 0.6, scale * 0.35, 0, 0, Math.PI * 2);
                        ctx.fill();

                        // Nostrils
                        ctx.fillStyle = '#4f46e5';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();

                        // Eyes
                        ctx.fillStyle = '#1e1b4b'; // Dark blue eyes
                        ctx.beginPath();
                        ctx.arc(-scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                        ctx.fill();

                        // Eye highlights
                        ctx.fillStyle = '#ffffff';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.2, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.16, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                        ctx.fill();

                        // Ears
                        ctx.fillStyle = '#a5b4fc';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();

                        // Pink inner ear
                        ctx.fillStyle = '#fda4af';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                        ctx.fill();
                    } else {
                        // 5. Golden Champion Star
                        ctx.fillStyle = '#ffd700';
                        ctx.beginPath();
                        ctx.moveTo(0, -scale * 1.3);
                        ctx.lineTo(scale * 0.35, -scale * 0.35);
                        ctx.lineTo(scale * 1.3, 0);
                        ctx.lineTo(scale * 0.35, scale * 0.35);
                        ctx.lineTo(0, scale * 1.3);
                        ctx.lineTo(-scale * 0.35, scale * 0.35);
                        ctx.lineTo(-scale * 1.3, 0);
                        ctx.lineTo(-scale * 0.35, -scale * 0.35);
                        ctx.closePath();
                        ctx.fill();

                        // Golden Crown at the top of the star
                        ctx.fillStyle = '#f59e0b'; // Amber Gold
                        ctx.beginPath();
                        ctx.moveTo(-scale * 0.3, -scale * 1.4);
                        ctx.lineTo(-scale * 0.2, -scale * 1.7);
                        ctx.lineTo(0, -scale * 1.5);
                        ctx.lineTo(scale * 0.2, -scale * 1.7);
                        ctx.lineTo(scale * 0.3, -scale * 1.4);
                        ctx.closePath();
                        ctx.fill();

                        ctx.shadowBlur = 0; // reset shadow
                    }
                } else if (holiday === 'little-prince') {
                    // Draw sparkling golden stars of Asteroid B-612!
                    ctx.fillStyle = '#ffd700';
                    ctx.beginPath();
                    ctx.moveTo(0, -this.size * 1.25);
                    ctx.lineTo(this.size * 0.3, -this.size * 0.3);
                    ctx.lineTo(this.size * 1.25, 0);
                    ctx.lineTo(this.size * 0.3, this.size * 0.3);
                    ctx.lineTo(0, this.size * 1.25);
                    ctx.lineTo(-this.size * 0.3, this.size * 0.3);
                    ctx.lineTo(-this.size * 1.25, 0);
                    ctx.lineTo(-this.size * 0.3, -this.size * 0.3);
                    ctx.closePath();
                    ctx.fill();
                } else if (holiday === 'orchid-day') {
                    // Draw a majestic vector orchid flower!
                    const scale = this.size * 1.4;
                    
                    // Sepals
                    ctx.fillStyle = '#f5d0fe'; // Lavender
                    ctx.beginPath();
                    ctx.ellipse(0, -scale * 0.8, scale * 0.45, scale * 0.8, 0, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(-scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, Math.PI / 3, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, -Math.PI / 3, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Large lateral petals
                    ctx.fillStyle = '#e879f9'; // Vibrant orchid pink
                    ctx.beginPath();
                    ctx.ellipse(-scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, -Math.PI / 8, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.beginPath();
                    ctx.ellipse(scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, Math.PI / 8, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Deep magenta center lip (Labellum)
                    ctx.fillStyle = '#df0893';
                    ctx.beginPath();
                    ctx.ellipse(0, scale * 0.25, scale * 0.45, scale * 0.5, 0, 0, Math.PI * 2);
                    ctx.fill();
                    
                    // Yellow stamen core
                    ctx.fillStyle = '#eab308';
                    ctx.beginPath();
                    ctx.arc(0, -scale * 0.1, scale * 0.18, 0, Math.PI * 2);
                    ctx.fill();
                } else if (holiday === 'doctor-day') {
                    // Draw a beautiful stethoscope vector!
                    const scale = this.size * 1.3;
                    const particleType = this.type % 3;

                    if (particleType === 0) {
                        // 1. Classic Medical Stethoscope
                        // Outer chestpiece rim
                        ctx.strokeStyle = '#cbd5e1'; // Silver/grey
                        ctx.lineWidth = scale * 0.15;
                        ctx.beginPath();
                        ctx.arc(0, scale * 0.6, scale * 0.45, 0, Math.PI * 2);
                        ctx.stroke();

                        // Inner chestpiece diaphragm
                        ctx.fillStyle = '#06b6d4'; // Cyan glowing core
                        ctx.beginPath();
                        ctx.arc(0, scale * 0.6, scale * 0.3, 0, Math.PI * 2);
                        ctx.fill();

                        // Rubber tubes (curved)
                        ctx.strokeStyle = '#0d9488'; // Teal tube
                        ctx.lineWidth = scale * 0.16;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';

                        // Main tube connecting chestpiece to the headset Y
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.15);
                        ctx.bezierCurveTo(-scale * 0.4, -scale * 0.1, -scale * 0.4, -scale * 0.6, 0, -scale * 0.7);
                        ctx.stroke();

                        // Y-binaural metallic branches
                        ctx.strokeStyle = '#cbd5e1'; // Metallic binaural
                        ctx.lineWidth = scale * 0.1;
                        ctx.beginPath();
                        ctx.arc(-scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                        ctx.stroke();
                        ctx.beginPath();
                        ctx.arc(scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                        ctx.stroke();

                        // Black plastic Eartips at the top
                        ctx.fillStyle = '#1e293b';
                        ctx.beginPath();
                        ctx.arc(-scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.beginPath();
                        ctx.arc(scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                        ctx.fill();
                    } else if (particleType === 1) {
                        // 2. Glowing Medical Red/Teal Cross
                        ctx.fillStyle = Math.abs(this.x) % 2 === 0 ? '#10b981' : '#0d9488'; // Emerald / Teal
                        const w = scale * 0.4;
                        const h = scale * 1.3;
                        ctx.fillRect(-w / 2, -h / 2, w, h);
                        ctx.fillRect(-h / 2, -w / 2, h, w);
                    } else {
                        // 3. EKG Pulse Line Segment (Зеленая линия ЭКГ)
                        ctx.strokeStyle = '#2dd4bf'; // Glowing turquoise
                        ctx.lineWidth = scale * 0.18;
                        ctx.lineCap = 'round';
                        ctx.beginPath();
                        ctx.moveTo(-scale, 0);
                        ctx.lineTo(-scale * 0.4, 0);
                        ctx.lineTo(-scale * 0.2, -scale * 0.8);
                        ctx.lineTo(scale * 0.1, scale * 0.8);
                        ctx.lineTo(scale * 0.3, -scale * 0.2);
                        ctx.lineTo(scale * 0.5, 0);
                        ctx.lineTo(scale, 0);
                        ctx.stroke();
                    }
                } else if (holiday === 'babel-library') {
                    // Draw Borges' Library of Babel vectors!
                    const scale = this.size * 1.3;
                    const particleType = this.type % 4;

                    if (particleType === 0) {
                        // 1. Hexagonal Gallery (Borges' Hexagon)
                        ctx.strokeStyle = '#d97706'; // Antique Amber
                        ctx.lineWidth = scale * 0.12;
                        ctx.beginPath();
                        for (let h = 0; h < 6; h++) {
                            const hx = Math.cos(h * Math.PI / 3) * scale;
                            const hy = Math.sin(h * Math.PI / 3) * scale;
                            if (h === 0) ctx.moveTo(hx, hy);
                            else ctx.lineTo(hx, hy);
                        }
                        ctx.closePath();
                        ctx.stroke();
                    } else if (particleType === 1) {
                        // 2. Mystical Open Book (Книга Вавилонской Библиотеки)
                        ctx.fillStyle = '#fef3c7'; // Old parchment pages
                        ctx.strokeStyle = '#78350f'; // Leather brown spine/cover
                        ctx.lineWidth = scale * 0.08;

                        // Left page
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.4);
                        ctx.bezierCurveTo(-scale * 0.4, scale * 0.2, -scale * 0.6, scale * 0.4, -scale * 0.8, scale * 0.2);
                        ctx.lineTo(-scale * 0.8, -scale * 0.4);
                        ctx.bezierCurveTo(-scale * 0.6, -scale * 0.2, -scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                        ctx.closePath();
                        ctx.fill();
                        ctx.stroke();

                        // Right page
                        ctx.beginPath();
                        ctx.moveTo(0, scale * 0.4);
                        ctx.bezierCurveTo(scale * 0.4, scale * 0.2, scale * 0.6, scale * 0.4, scale * 0.8, scale * 0.2);
                        ctx.lineTo(scale * 0.8, -scale * 0.4);
                        ctx.bezierCurveTo(scale * 0.6, -scale * 0.2, scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                        ctx.closePath();
                        ctx.fill();
                        ctx.stroke();

                        // Spine line
                        ctx.beginPath();
                        ctx.moveTo(0, -scale * 0.2);
                        ctx.lineTo(0, scale * 0.4);
                        ctx.stroke();
                    } else if (particleType === 2) {
                        // 3. Floating Random Character / Letter of Babel (Случайный символ бесконечного алфавита)
                        const char = this.babelChar || 'a';
                        ctx.fillStyle = '#f59e0b'; // Glowing gold
                        ctx.font = `italic bold ${Math.max(12, scale * 0.85)}px serif`;
                        ctx.textAlign = 'center';
                        ctx.fillText(char, 0, scale * 0.3);
                    } else {
                        // 4. Rolled Parchment Scroll (Свиток)
                        ctx.fillStyle = '#fef3c7'; // Parchment roll
                        ctx.strokeStyle = '#d97706';
                        ctx.lineWidth = scale * 0.06;
                        ctx.beginPath();
                        ctx.ellipse(0, 0, scale * 0.7, scale * 0.25, Math.PI / 6, 0, Math.PI * 2);
                        ctx.fill();
                        ctx.stroke();
                    }
                }
                
                ctx.restore();
            }
        }

        for (let i = 0; i < maxParticles; i++) {
            particles.push(new Particle());
            // Pre-warm particles across screen height
            particles[i].y = Math.random() * height;
        }

        function animate() {
            ctx.clearRect(0, 0, width, height);
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }
            requestAnimationFrame(animate);
        }

        animate();
    }

    // Auto initialize theme & holiday effects
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('theme')) {
        localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
    }
    const savedTheme = localStorage.getItem('theme') || getCognitiveDemographicDefaultTheme();
    setTheme(savedTheme);
    
    // Instant fallback/sync load
    initAtmosphericHolidayFX();

    // Async active holiday sync with backend Google-Doodle-style API
    async function syncActiveHolidayWithApi() {
        try {
            const holidayParam = urlParams.get('holiday');
            const dateParam = urlParams.get('date');
            
            let apiUrl = '/api/holidays/active';
            const params = [];
            if (holidayParam) params.push(`holiday=${holidayParam}`);
            if (dateParam) params.push(`date=${dateParam}`);
            if (params.length > 0) apiUrl += `?${params.join('&')}`;

            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error("API failed");
            const data = await response.json();
            
            const apiHoliday = data.active_holiday;
            const currentHoliday = document.body.getAttribute('data-holiday');
            
            if (apiHoliday) {
                if (currentHoliday !== apiHoliday.id) {
                    console.log(`[Festive API] Dynamic Sync: Switching active holiday to ${apiHoliday.name} (${apiHoliday.id})! 🎭`);
                    document.body.setAttribute('data-holiday', apiHoliday.id);
                    
                    const existingCanvas = document.getElementById('holiday-canvas-fx');
                    if (existingCanvas) existingCanvas.remove();
                    
                    initAtmosphericHolidayFX(apiHoliday.id);
                }
            } else {
                if (currentHoliday) {
                    document.body.removeAttribute('data-holiday');
                    const existingCanvas = document.getElementById('holiday-canvas-fx');
                    if (existingCanvas) existingCanvas.remove();
                }
            }
        } catch (e) {
            console.warn("[Festive API] Failed to fetch active holiday, keeping local client fallback.", e);
        }
    }
    
    // Defer API sync to ensure high performance
    if (window.requestIdleCallback) {
        window.requestIdleCallback(() => syncActiveHolidayWithApi());
    } else {
        setTimeout(syncActiveHolidayWithApi, 200);
    }
</script>

</body>
</html>
