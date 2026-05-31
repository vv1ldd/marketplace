<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- 🛡️ DYNAMIC SEO META TAGS -->
    <title>{{ $product->meta_title ?? $product->name }} — Купить в Meanly</title>
    <meta name="description" content="{{ $product->meta_description ?? 'Купить лицензионный цифровой товар по лучшей цене с мгновенной автоматической доставкой в личный сейф.' }}">
    @if(!empty($product->meta_keywords))
        <meta name="keywords" content="{{ $product->meta_keywords }}">
    @endif
    <link rel="alternate" type="application/json" href="{{ route('llms.products.show', $product->slug) }}">
    @isset($productJsonLd)
        <script type="application/ld+json">{!! json_encode($productJsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    @isset($productFacts)
        <script type="application/json" id="meanly-product-facts">{!! json_encode($productFacts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    
    <!-- OpenGraph (Facebook / Telegram Link Previews) -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $product->meta_title ?? $product->name }} — Meanly">
    <meta property="og:description" content="{{ $product->meta_description ?? 'Мгновенная доставка, безопасный клиринг и оплата в рублях.' }}">
    <meta property="og:image" content="{{ $product->getRedeemDisplayImageSrc() }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="Meanly Systems">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $product->meta_title ?? $product->name }}">
    <meta name="twitter:description" content="{{ $product->meta_description }}">
    <meta name="twitter:image" content="{{ $product->getRedeemDisplayImageSrc() }}">

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

        /* Ambient Glow */
        .ambient-glows {
            position: absolute;
            top: 0; left: 0; right: 0; height: 100vh;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-main {
            position: absolute; top: -20%; left: 30%; width: 50vw; height: 50vw;
            background: radial-gradient(circle, rgba(245, 48, 3, 0.06) 0%, rgba(0,0,0,0) 70%);
            filter: blur(80px);
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
        .nav-actions { display: flex; gap: 1rem; align-items: center; }
        .btn-nav-cta {
            background: var(--brand-primary); 
            color: #fff !important; 
            padding: 0.5rem 1.25rem;
            border-radius: 8px; 
            font-weight: 700; 
            font-size: 13px;
            text-decoration: none; 
            transition: all 0.2s;
        }
        .btn-nav-cta:hover { 
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(245, 48, 3, 0.5);
        }

        /* ── BREADCRUMBS ── */
        .breadcrumbs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            font-size: 12px;
            color: var(--brand-subtext);
            margin-bottom: 2rem;
        }
        .breadcrumbs a {
            color: var(--brand-subtext);
            text-decoration: none;
            transition: color 0.2s;
        }
        .breadcrumbs a:hover {
            color: var(--brand-text);
        }
        .breadcrumbs i { font-size: 10px; }

        .product-container {
            max-width: 1200px;
            padding: 8rem 1.5rem 4rem;
            margin: 0 auto;
            position: relative;
            z-index: 10;
        }

        .product-grid {
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 4rem;
            align-items: start;
        }

        /* Image Section */
        .image-pane {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 24px;
            aspect-ratio: 1/1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .image-pane img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .image-pane svg {
            width: 100%;
            height: 100%;
        }

        /* Info Section */
        .info-pane {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .product-category-tag {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--brand-primary);
        }

        .info-pane h1 {
            font-size: clamp(2rem, 4vw, 2.8rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            line-height: 1.2;
        }

        .meta-row {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }
        .meta-tag {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--brand-border);
            padding: 0.35rem 0.8rem;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            color: var(--brand-text);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Price Card */
        .price-box {
            background: var(--brand-card);
            border: 1px solid var(--brand-border);
            border-radius: 20px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .price-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .price-large {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .price-nominal {
            font-size: 13px;
            color: var(--brand-subtext);
            font-family: 'JetBrains Mono', monospace;
        }

        .btn-checkout {
            all: unset;
            background: var(--brand-primary);
            color: #fff;
            height: 54px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(245, 48, 3, 0.35);
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
        }
        .btn-checkout:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 25px rgba(245, 48, 3, 0.55);
        }

        /* Benefits Grid */
        .benefits-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        .benefit-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 13px;
            color: var(--brand-subtext);
        }
        .benefit-item i {
            color: #107c10;
            font-size: 16px;
        }

        /* Description pane */
        .details-section {
            border-top: 1px solid var(--brand-border);
            margin-top: 4rem;
            padding-top: 3rem;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 4rem;
        }
        .description-pane h2, .specs-pane h2 {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
        }
        .description-pane p {
            color: var(--brand-subtext);
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .specs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .specs-table tr {
            border-bottom: 1px solid var(--brand-border);
        }
        .specs-table td {
            padding: 0.75rem 0;
            font-size: 13px;
        }
        .specs-label {
            color: var(--brand-subtext);
            font-weight: 500;
        }
        .specs-val {
            text-align: right;
            font-weight: 700;
        }

        /* ── FOOTER ── */
        footer {
            padding: 6rem 0;
            margin-top: 6rem;
            border-top: 1px solid var(--brand-border);
            color: var(--brand-subtext);
            font-size: 13px;
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
        .footer-links a { color: var(--brand-subtext); text-decoration: none; }

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
        }
        .checkout-icon {
            font-size: 2.5rem;
            color: var(--brand-primary);
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .product-grid { grid-template-columns: 1fr; gap: 2rem; }
            .details-section { grid-template-columns: 1fr; gap: 2rem; }
            .footer-container { flex-direction: column; gap: 2rem; text-align: center; }
            .footer-links { justify-content: center; }
        }

        /* 🎚️ Custom Styled Range Slider */
        #nominalSlider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--brand-primary);
            cursor: pointer;
            box-shadow: 0 0 10px rgba(245, 48, 3, 0.6);
            transition: transform 0.15s, background-color 0.15s;
        }
        #nominalSlider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
            background: #ff5224;
        }
        #nominalSlider::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border: none;
            border-radius: 50%;
            background: var(--brand-primary);
            cursor: pointer;
            box-shadow: 0 0 10px rgba(245, 48, 3, 0.6);
            transition: transform 0.15s, background-color 0.15s;
        }
        #nominalSlider::-moz-range-thumb:hover {
            transform: scale(1.2);
            background: #ff5224;
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
            background: #74acdf !important;
            color: #000000 !important;
            box-shadow: 0 2px 10px rgba(116, 172, 223, 0.3) !important;
            font-weight: 900;
        }
        body[data-theme="consortium"] #skin-btn-consortium {
            background: #f53003 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 10px rgba(245, 48, 3, 0.4) !important;
            font-weight: 900;
        }
        body[data-theme="retro"] #skin-btn-retro {
            background: #7c3aed !important;
            color: #ffffff !important;
            box-shadow: 2px 2px 0px #000000 !important;
            font-weight: 900;
            border: 2px solid #000000 !important;
        }

        /* 🌟 Theme 1: Partner (Albiceleste Light Blue - Sons Birthday) */
        body[data-theme="partner"] {
            --brand-primary: #74acdf;
            --brand-bg: #060608;
            --brand-card: rgba(14, 14, 18, 0.65);
            --brand-text: #ffffff;
            --brand-subtext: #9a9ab0;
            --brand-border: rgba(255, 255, 255, 0.04);
            --brand-border-hover: rgba(116, 172, 223, 0.2);
            --glass-bg: rgba(11, 11, 14, 0.7);
            background: #060608 !important;
            font-family: 'Outfit', sans-serif !important;
        }
        body[data-theme="partner"] .glow-main {
            background: radial-gradient(circle, rgba(116, 172, 223, 0.09) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="partner"] .logo-mark {
            background: #74acdf !important;
            box-shadow: 0 0 15px rgba(116, 172, 223, 0.5) !important;
        }
        body[data-theme="partner"] .product-category-tag {
            color: #74acdf !important;
        }
        body[data-theme="partner"] .btn-checkout {
            background: #74acdf !important;
            color: #000 !important;
            box-shadow: 0 4px 20px rgba(116, 172, 223, 0.35) !important;
        }
        body[data-theme="partner"] .btn-checkout:hover {
            box-shadow: 0 6px 25px rgba(116, 172, 223, 0.55) !important;
        }
        body[data-theme="partner"] #nominalSlider::-webkit-slider-thumb {
            background: #74acdf !important;
            box-shadow: 0 0 10px rgba(116, 172, 223, 0.6) !important;
        }
        body[data-theme="partner"] #nominalSlider::-moz-range-thumb {
            background: #74acdf !important;
            box-shadow: 0 0 10px rgba(116, 172, 223, 0.6) !important;
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
        body[data-theme="consortium"] .glow-main {
            background: radial-gradient(circle, rgba(245, 48, 3, 0.08) 0%, rgba(0,0,0,0) 70%) !important;
        }
        body[data-theme="consortium"] h1, 
        body[data-theme="consortium"] h2, 
        body[data-theme="consortium"] h3, 
        body[data-theme="consortium"] .logo, 
        body[data-theme="consortium"] .btn-nav-cta,
        body[data-theme="consortium"] .btn-checkout,
        body[data-theme="consortium"] .price-large,
        body[data-theme="consortium"] .price-nominal {
            font-family: 'JetBrains Mono', monospace !important;
            letter-spacing: -0.01em !important;
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
        body[data-theme="retro"] .glow-main {
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
            background: #7c3aed !important;
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
        body[data-theme="retro"] .btn-nav-login {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .btn-nav-cta {
            background: #7c3aed !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        body[data-theme="retro"] .btn-nav-cta:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .breadcrumbs {
            color: #000000 !important;
        }
        body[data-theme="retro"] .breadcrumbs a {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .image-pane {
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
        }
        body[data-theme="retro"] .product-category-tag {
            color: #7c3aed !important;
        }
        body[data-theme="retro"] .info-pane h1 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .meta-tag {
            background: #ffffff !important;
            color: #000000 !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .price-box {
            border: 2px solid #000000 !important;
            box-shadow: 6px 6px 0px #000000 !important;
            border-radius: 0px !important;
            background: #ffffff !important;
            color: #000000 !important;
        }
        body[data-theme="retro"] .price-label {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .price-large {
            color: #000000 !important;
        }
        body[data-theme="retro"] .price-nominal {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] #nominalInputContainer {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] #nominalInput {
            color: #000000 !important;
        }
        body[data-theme="retro"] #nominalSlider {
            background: rgba(0,0,0,0.1) !important;
        }
        body[data-theme="retro"] #nominalSlider::-webkit-slider-thumb {
            background: #7c3aed !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] #nominalSlider::-moz-range-thumb {
            background: #7c3aed !important;
            border: 2px solid #000000 !important;
            box-shadow: 2px 2px 0px #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .btn-checkout {
            background: #7c3aed !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
        }
        body[data-theme="retro"] .btn-checkout:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .benefit-item {
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .benefit-item i {
            color: #7c3aed !important;
        }
        body[data-theme="retro"] .details-section {
            border-top: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .description-pane h2,
        body[data-theme="retro"] .specs-pane h2 {
            color: #000000 !important;
        }
        body[data-theme="retro"] .description-pane p {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .specs-table tr {
            border-bottom: 2px solid #000000 !important;
        }
        body[data-theme="retro"] .specs-label {
            color: #4e4e5e !important;
        }
        body[data-theme="retro"] .specs-val {
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
            color: #7c3aed !important;
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

        /* 🎉 Sons Birthday / Holiday (Albiceleste) Overrides */
        body[data-holiday="sons-birthday"] {
            --brand-primary: #74acdf;
            --brand-primary-rgb: 116, 172, 223;
        }
        body[data-holiday="sons-birthday"] .logo-mark {
            background: #74acdf !important;
            box-shadow: 0 0 15px rgba(116, 172, 223, 0.5) !important;
        }
        body[data-holiday="sons-birthday"] .btn-checkout,
        body[data-holiday="sons-birthday"] .btn-nav-cta {
            background: #74acdf !important;
            color: #fff !important;
            box-shadow: 0 4px 20px rgba(116, 172, 223, 0.3) !important;
        }
        body[data-holiday="sons-birthday"] .product-category-tag {
            color: #74acdf !important;
        }
        body[data-holiday="sons-birthday"] #nominalSlider::-webkit-slider-thumb {
            background: #74acdf !important;
            box-shadow: 0 0 10px rgba(116, 172, 223, 0.6) !important;
        }
    </style>
    
    <!-- 📊 STRUCTURED SCHEMA.ORG JSON-LD FOR GOOGLE SEARCH -->
    <script type="application/ld+json">
    {
      "@@context": "https://schema.org/",
      "@type": "Product",
      "name": "{{ $product->name }}",
      "image": "{{ $product->getRedeemDisplayImageSrc() }}",
      "description": "{{ $product->meta_description }}",
      "sku": "{{ $product->sku }}",
      "brand": {
        "@type": "Brand",
        "name": "{{ $product->vendor ?? 'Digital Platform' }}"
      },
      "offers": {
        "@type": "Offer",
        "url": "{{ url()->current() }}",
        "priceCurrency": "RUB",
        "price": "{{ $product->price_rub / 100 }}",
        "itemCondition": "https://schema.org/NewCondition",
        "availability": "https://schema.org/InStock"
      }
    }
    </script>
</head>
@php
    $minVal = (float) (data_get($product->data, 'min_price') ?? data_get($product->data, 'data.min_price') ?? 0);
    $maxVal = (float) (data_get($product->data, 'max_price') ?? data_get($product->data, 'data.max_price') ?? 0);
    $isOpenDenomination = ($minVal > 0 && $maxVal > $minVal);
    
    $rate = 1.0;
    if ($isOpenDenomination) {
        if ($product->purchase_price > 0) {
            $rate = ($product->price_rub / 100) / $product->purchase_price;
        } else {
            $rate = 3.2; 
        }
    }
@endphp

<body>
@include('partials.theme-sync-body')

<div class="ambient-glows">
    <div class="glow-main"></div>
</div>

@include('storefront.partials.header')

<main class="product-container">
    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <a href="/"><i class="ph-bold ph-house-line"></i> Главная</a>
        <i class="ph-bold ph-caret-right"></i>
        <span>Каталог</span>
        <i class="ph-bold ph-caret-right"></i>
        <span>{{ $product->category ?? 'Цифровые товары' }}</span>
    </div>

    <!-- Product Grid -->
    <div class="product-grid">
        <!-- Left: Image -->
        <div class="image-pane">
            <img src="{{ $product->getRedeemDisplayImageSrc() }}" alt="{{ $product->name }}">
        </div>

        <!-- Right: Information & Action -->
        <div class="info-pane">
            <div>
                <span class="product-category-tag">{{ $product->category ?? 'Цифровой ваучер' }}</span>
                <h1 style="margin-top: 0.5rem;">{{ $product->name }}</h1>
            </div>

            <!-- Meta attributes -->
            <div class="meta-row">
                @if($product->vendor)
                    <span class="meta-tag"><i class="ph-bold ph-tag"></i> {{ $product->vendor }}</span>
                @endif
                <span class="meta-tag"><i class="ph-bold ph-globe"></i> Региональный ключ</span>
                <span class="meta-tag"><i class="ph-bold ph-fingerprint"></i> Passkey-защита</span>
            </div>

            <!-- Price and checkout box -->
            <div class="price-box">
                @if($isOpenDenomination)
                    <!-- 🎚️ Open Denomination Range Slider -->
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="price-label" style="font-weight: 700;">Сумма пополнения ({{ $product->purchase_currency ?? 'TRY' }})</span>
                            <div id="nominalInputContainer" style="display: flex; align-items: center; gap: 0.5rem; background: rgba(255,255,255,0.02); border: 1px solid var(--brand-border); padding: 0.4rem 0.8rem; border-radius: 8px;">
                                <input type="number" id="nominalInput" min="{{ $minVal }}" max="{{ $maxVal }}" value="{{ $minVal }}" style="background: transparent; border: none; color: #fff; font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 700; width: 80px; text-align: right; outline: none;" oninput="updateNominal(this.value)">
                                <span style="font-size: 13px; color: var(--brand-subtext); font-weight: 700;">{{ $product->purchase_currency ?? 'TRY' }}</span>
                            </div>
                        </div>

                        <!-- Beautiful custom range slider -->
                        <div style="position: relative; padding: 0.5rem 0;">
                            <input type="range" id="nominalSlider" min="{{ $minVal }}" max="{{ $maxVal }}" value="{{ $minVal }}" step="1" style="width: 100%; -webkit-appearance: none; background: rgba(255,255,255,0.05); height: 6px; border-radius: 100px; outline: none; transition: background 0.3s;" oninput="updateNominal(this.value)">
                            <div style="display: flex; justify-content: space-between; margin-top: 0.8rem; font-size: 11px; color: var(--brand-subtext); font-family: 'JetBrains Mono', monospace;">
                                <span>{{ number_format($minVal, 0) }} {{ $product->purchase_currency ?? 'TRY' }}</span>
                                <span>{{ number_format($maxVal, 0) }} {{ $product->purchase_currency ?? 'TRY' }}</span>
                            </div>
                        </div>

                        <div class="price-details" style="border-top: 1px solid var(--brand-border); padding-top: 1.5rem;">
                            <div>
                                <span class="price-label">Итоговая стоимость в рублях</span>
                                <div class="price-large" id="calculatedPriceRub" style="margin-top: 0.25rem;">{{ number_format($minVal * $rate, 0, '.', ' ') }} ₽</div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- 🏷️ Standard Fixed Price Box -->
                    <div class="price-details">
                        <div>
                            <span class="price-label">Цена в рублях</span>
                            <div class="price-large" style="margin-top: 0.25rem;">{{ number_format($product->price_rub / 100, 0, '.', ' ') }} ₽</div>
                        </div>
                        @if($product->purchase_price > 0)
                            <div style="text-align: right;">
                                <span class="price-label">Номинал</span>
                                <div class="price-nominal" style="margin-top: 0.5rem;">{{ (float) $product->purchase_price }} {{ $product->purchase_currency }}</div>
                            </div>
                        @endif
                    </div>
                @endif

                <button class="btn-checkout" onclick="openCheckout('{{ $product->name }}')">
                    <i class="ph-bold ph-shopping-cart-simple"></i> Купить в розницу
                </button>

                <!-- Benefits -->
                <div class="benefits-grid">
                    <div class="benefit-item"><i class="ph-fill ph-check-circle"></i> Мгновенная доставка</div>
                    <div class="benefit-item"><i class="ph-fill ph-check-circle"></i> 100% валидный код</div>
                    <div class="benefit-item"><i class="ph-fill ph-check-circle"></i> Безопасный клиринг</div>
                    <div class="benefit-item"><i class="ph-fill ph-check-circle"></i> Без паспорта и документов</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Section -->
    <div class="details-section">
        <!-- Description -->
        <div class="description-pane">
            <h2>Описание товара</h2>
            @if($product->description)
                <p>{!! nl2br(e($product->description)) !!}</p>
            @else
                <p>Этот лицензионный код предназначен для быстрой активации на соответствующей платформе. После оплаты ваучер будет автоматически доставлен в ваш личный сейф в течение нескольких секунд.</p>
            @endif
            
            <h2 style="margin-top: 3rem;">Инструкция по активации</h2>
            <p>1. Авторизуйтесь на целевой платформе активации.<br>
            2. Перейдите в раздел активации подарочных кодов / redeem.<br>
            3. Вставьте купленный ключ из вашего личного сейфа Meanly.<br>
            4. Баланс будет мгновенно зачислен на ваш кошелек!</p>
        </div>

        <!-- Specifications -->
        <div class="specs-pane">
            <h2>Характеристики</h2>
            <table class="specs-table">
                @if($product->sku)
                    <tr>
                        <td class="specs-label">Артикул (SKU)</td>
                        <td class="specs-val" style="font-family: 'JetBrains Mono', monospace;">{{ $product->sku }}</td>
                    </tr>
                @endif
                @if($product->vendor)
                    <tr>
                        <td class="specs-label">Производитель</td>
                        <td class="specs-val">{{ $product->vendor }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="specs-label">Формат товара</td>
                    <td class="specs-val">Цифровой ключ (Ваучер)</td>
                </tr>
                <tr>
                    <td class="specs-label">Способ доставки</td>
                    <td class="specs-val" style="color: #107c10;">Мгновенно на Email / Сейф</td>
                </tr>
                <tr>
                    <td class="specs-label">Лимит активации</td>
                    <td class="specs-val">1 устройство / аккаунт</td>
                </tr>
            </table>
        </div>
    </div>
</main>

<!-- ── CHECKOUT MODAL ── -->
<div class="modal-overlay" id="checkoutModal" onclick="closeCheckout()">
    <div class="checkout-modal" onclick="event.stopPropagation()">
        <button class="close-btn" onclick="closeCheckout()"><i class="ph-bold ph-x"></i></button>
        <div class="checkout-icon">
            <i class="ph-bold ph-fingerprint"></i>
        </div>
        <h3 class="checkout-title">Войдите для покупки</h3>
        <p class="checkout-desc" id="checkoutDesc">Чтобы совершить покупку, войдите в личный аккаунт. С Passkey это занимает пару секунд.</p>
        
        <a href="/login" class="btn-nav-cta" style="display: flex; align-items: center; justify-content: center; gap: 0.6rem; font-size: 14px; padding: 0.8rem 2rem; width: 100%;">
            Войти и оплатить ➔
        </a>
    </div>
</div>

<footer>
    <div class="footer-container">
        <div>&copy; {{ date('Y') }} Meanly Systems. Цифровые покупки в личном сейфе.</div>
        <div class="footer-links">
            <a href="/">Назад на витрину</a>
            <a href="#">Условия использования</a>
            <a href="#">Конфиденциальность</a>
        </div>
    </div>
</footer>

<script>
    @if($isOpenDenomination)
    const rate = {{ $rate }};
    
    function updateNominal(val) {
        val = parseFloat(val);
        const minVal = {{ $minVal }};
        const maxVal = {{ $maxVal }};
        
        if (isNaN(val)) val = minVal;
        if (val < minVal) val = minVal;
        if (val > maxVal) val = maxVal;
        
        // Sync both inputs
        document.getElementById('nominalSlider').value = val;
        document.getElementById('nominalInput').value = val;
        
        // Calculate RUB
        const rubPrice = Math.round(val * rate);
        
        // Format with spaces
        const formattedPrice = new Intl.NumberFormat('ru-RU').format(rubPrice) + ' ₽';
        document.getElementById('calculatedPriceRub').innerText = formattedPrice;
    }
    @endif

    function openCheckout(productName) {
        @auth
            window.location.href = '/vault';
        @else
            let displayProductName = productName;
            @if($isOpenDenomination)
                const selectedAmount = document.getElementById('nominalInput').value;
                displayProductName = `${productName} (${selectedAmount} {{ $product->purchase_currency ?? 'TRY' }})`;
            @endif
            document.getElementById('checkoutDesc').innerText = `Для мгновенного оформления заказа на "${displayProductName}" авторизуйтесь в суверенном личном кабинете через Passkey.`;
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

    // Auto initialize theme
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('theme')) {
        localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
    }
    const savedTheme = localStorage.getItem('theme') || 'consortium';
    setTheme(savedTheme);

    // Basic Holiday Detection to inherit theme overrides
    function applyHoliday() {
        const urlParams = new URLSearchParams(window.location.search);
        
        // 1. Explicit query parameter override (highest priority)
        if (urlParams.has('holiday')) {
            const override = urlParams.get('holiday');
            if (override && override !== 'none') {
                localStorage.setItem('holiday-override', override.toLowerCase());
            } else {
                localStorage.removeItem('holiday-override');
            }
        }
        
        // 2. Natural calendar holiday of the current date (second priority)
        let holiday = null;
        const now = new Date();
        const month = now.getMonth();
        const date = now.getDate();
        
        if (month === 4 && date === 19) {
            holiday = 'sons-birthday';
        } else if (month === 4 && date === 12) {
            holiday = 'orchid-day';
        }
        
        // 3. Stored localStorage override from manually-triggered queries (third priority)
        if (!holiday) {
            holiday = localStorage.getItem('holiday-override');
        }
        
        if (holiday) {
            document.body.setAttribute('data-holiday', holiday.toLowerCase());
        }
    }
    applyHoliday();
</script>

</body>
</html>
