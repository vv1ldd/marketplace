<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meanly - маркетплейс цифровых активов</title>
    <meta name="description" content="Meanly marketplace: цифровые подарочные карты, игровые ключи и подписки с быстрым checkout и выдачей кодов.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ route('home') }}">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.catalog.index') }}">
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => route('home').'#organization',
                'name' => 'Meanly',
                'url' => route('home'),
            ],
            [
                '@type' => 'WebSite',
                '@id' => route('home').'#website',
                'name' => 'Meanly',
                'url' => route('home'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('home').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    @isset($catalogJsonLd)
        <script type="application/ld+json">{!! json_encode($catalogJsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;700;800;900&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #eef0fc;
            --panel: #ffffff;
            --ink: #050505;
            --muted: #4b5563;
            --brand: #7c3aed;
            --brand-soft: #efe6ff;
            --line: #050505;
            --shadow: 7px 7px 0 #050505;
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; overflow-x: hidden; }
        body {
            margin: 0;
            position: relative;
            background:
                linear-gradient(90deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                var(--bg);
            background-size: 28px 28px;
            color: var(--ink);
            font-family: "Outfit", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 580px;
            background: radial-gradient(circle at 50% -120px, rgba(124, 58, 237, 0.16) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1180px, calc(100vw - 32px)); margin: 0 auto; }
        .nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--panel);
            border-bottom: 4px solid var(--line);
            box-shadow: 0 4px 0 rgba(5, 5, 5, .12);
        }
        .nav-inner {
            min-height: 74px;
            display: grid;
            grid-template-columns: minmax(160px, 1fr) auto minmax(180px, 1fr);
            align-items: center;
            gap: 24px;
        }
        .logo { display: inline-flex; align-items: center; gap: 9px; font-weight: 950; letter-spacing: -.05em; }
        .logo-mark { width: 12px; height: 12px; background: var(--brand); border: 2px solid var(--line); box-shadow: 2px 2px 0 var(--line); }
        .nav-links { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 13px; font-weight: 900; }
        .nav-links a,
        .nav-action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 12px;
            border: 2px solid transparent;
            border-radius: 3px;
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }
        .nav-links a:hover,
        .nav-action-link:hover {
            background: var(--brand-soft);
            border-color: var(--line);
            transform: translate(1px, 1px);
        }
        .nav-actions { display: flex; justify-content: flex-end; align-items: center; gap: 14px; font-size: 13px; font-weight: 900; }
        .nav-action-link {
            background: var(--panel);
            color: var(--ink);
            border-color: var(--line);
            box-shadow: 3px 3px 0 var(--line);
        }
        .nav-action-link:hover { box-shadow: 2px 2px 0 var(--line); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--line);
            border-radius: 4px;
            min-height: 46px;
            padding: 0 22px;
            font-weight: 950;
            background: var(--panel);
            box-shadow: 4px 4px 0 var(--line);
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .btn:hover {
            transform: translate(-3px, -3px);
            box-shadow: 7px 7px 0 var(--line);
        }
        .btn:active {
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 var(--line);
        }
        .btn-primary { background: var(--brand); color: #fff; }
        .hero { padding: 78px 0 68px; text-align: center; position: relative; z-index: 1; }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 9px 18px;
            border: 3px solid var(--line);
            background: var(--brand-soft);
            color: var(--brand);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            box-shadow: 4px 4px 0 var(--line);
            margin-bottom: 28px;
        }
        h1 {
            margin: 0 auto;
            max-width: 920px;
            font-size: clamp(48px, 7vw, 92px);
            line-height: .92;
            letter-spacing: -.08em;
            font-weight: 950;
        }
        .lead {
            margin: 26px auto 0;
            max-width: 720px;
            color: var(--muted);
            font-size: clamp(18px, 2.2vw, 24px);
            line-height: 1.45;
            font-weight: 700;
        }
        .hero-actions { margin-top: 36px; display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
        .intent-box {
            margin: 34px auto 0;
            max-width: 860px;
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            padding: 24px;
            box-shadow: 8px 8px 0 var(--line);
            text-align: left;
            position: relative;
            z-index: 2;
        }
        .intent-box label {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .intent-row { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 12px; }
        .intent-row input {
            min-height: 58px;
            border: 3px solid var(--line);
            border-radius: 4px;
            padding: 0 18px;
            font: inherit;
            font-size: 18px;
            font-weight: 900;
            color: var(--ink);
            background: var(--bg);
            transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }
        .intent-row input:focus {
            background: #ffffff;
            border-color: var(--brand);
            outline: none;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15);
        }
        .chips { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .chip {
            border: 2px solid var(--line);
            background: var(--panel);
            color: var(--ink);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 12px;
            font-weight: 950;
            box-shadow: 3px 3px 0 var(--line);
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.15s ease;
        }
        .chip:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 var(--line);
            background: var(--brand-soft);
        }
        .ai-panel {
            margin: 6px 0 34px;
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: #fff7d6;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        .ai-panel h2 { margin: 0 0 8px; font-size: clamp(24px, 3vw, 38px); letter-spacing: -.05em; }
        .ai-panel p { margin: 0; color: var(--muted); font-weight: 800; }
        .reason-list { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
        .reason { border: 2px solid var(--line); background: var(--brand-soft); padding: 5px 8px; font-size: 11px; font-weight: 950; }
        .offer-badges { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
        .offer-badge { border: 2px solid var(--line); background: #d8ff6f; padding: 5px 8px; font-size: 11px; font-weight: 950; }
        .offer-score { margin-top: 8px; font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 11px; color: var(--muted); font-weight: 900; }
        .seller-line { margin-top: 8px; font-size: 12px; color: var(--muted); font-weight: 950; text-transform: uppercase; letter-spacing: .04em; }
        .shelf { margin: 38px 0 54px; position: relative; z-index: 1; }
        .shelf-title { display: flex; justify-content: space-between; align-items: end; gap: 16px; margin-bottom: 18px; }
        .shelf-title h2 { margin: 0; font-size: clamp(26px, 3vw, 42px); letter-spacing: -.05em; line-height: 1; }
        .shelf-title span { color: var(--muted); font-weight: 900; }
        .rail { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        .mini-card {
            border: 3px solid var(--line);
            background: var(--panel);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: 5px 5px 0 var(--line);
            min-height: 190px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .mini-card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 10px 10px 0 var(--line);
        }
        .mini-card h3 { margin: 0; font-size: 18px; line-height: 1.08; letter-spacing: -.04em; }
        .mini-card .price { font-size: 24px; margin: 10px 0; }
        .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
        .category-card {
            border: 3px solid var(--line);
            background: var(--brand-soft);
            color: var(--ink);
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: 5px 5px 0 var(--line);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.2s ease;
        }
        .category-card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 10px 10px 0 var(--line);
            background: var(--panel);
        }
        .category-card strong { display: block; font-size: 20px; letter-spacing: -.04em; }
        .favorite-btn {
            border: 2px solid var(--line);
            background: #fff;
            color: var(--ink);
            border-radius: 999px;
            font-weight: 950;
            padding: 7px 10px;
            cursor: pointer;
            box-shadow: 3px 3px 0 var(--line);
        }
        .favorite-btn.active { background: #ffd7ef; }
        .pagination-neo {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            margin: -42px 0 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            font-weight: 950;
        }
        .pagination-neo a,
        .pagination-neo span {
            border: 3px solid var(--line);
            background: var(--panel);
            color: var(--ink);
            min-width: 44px;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 12px;
            box-shadow: 4px 4px 0 var(--line);
        }
        .pagination-neo .active {
            background: var(--brand);
            color: #fff;
        }
        .pagination-neo .disabled {
            opacity: .45;
            box-shadow: none;
        }

        /* Search Options & Cards */
        .hero-search-panel {
            max-width: 880px;
            margin: 34px auto 0;
            text-align: left;
            position: relative;
            z-index: 2;
        }
        .intent-card {
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            padding: 24px;
            box-shadow: 8px 8px 0 var(--line);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .hero-search-note {
            margin: 14px 0 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.45;
        }
        .suggest-panel {
            display: none;
            margin-top: 14px;
            border: 3px solid var(--line);
            background: var(--panel);
            box-shadow: 5px 5px 0 var(--line);
            border-radius: 6px;
            overflow: hidden;
        }
        .suggest-panel.is-open { display: block; }
        .suggest-item {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 2px solid var(--line);
            background: var(--panel);
        }
        .suggest-item:last-child { border-bottom: 0; }
        .suggest-item:hover { background: var(--brand-soft); }
        .suggest-title {
            display: block;
            font-weight: 950;
            letter-spacing: -.02em;
        }
        .suggest-meta {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 850;
        }
        .suggest-price {
            align-self: center;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
            white-space: nowrap;
            color: var(--brand);
        }
        .intent-card label {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        /* Brands Grid & Cards */
        .brands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
        }
        .brand-card {
            border: 3px solid var(--line);
            background: var(--panel); /* dynamic panel theme bg */
            border-radius: var(--radius);
            padding: 18px;
            box-shadow: 5px 5px 0 var(--line);
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), background 0.2s ease;
            text-decoration: none;
            color: var(--ink);
        }
        .brand-card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 10px 10px 0 var(--line);
            background: var(--brand-soft);
        }
        .brand-card strong {
            display: block;
            font-size: 20px;
            letter-spacing: -.04em;
        }
        .brand-card span {
            font-size: 13px;
            color: var(--muted);
            font-weight: 800;
            display: block;
            margin-top: 6px;
        }
        .brand-card .badge-active {
            display: inline-block;
            background: var(--brand);
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 900;
            text-transform: uppercase;
        }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 24px;
            margin: 28px 0 22px;
            border-bottom: 4px solid var(--line);
            padding-bottom: 18px;
        }
        .section-head h2 { margin: 0; font-size: clamp(28px, 4vw, 44px); line-height: 1; letter-spacing: -.05em; }
        .section-head p { margin: 8px 0 0; color: var(--muted); font-weight: 800; }
        .search {
            display: flex;
            gap: 10px;
            align-items: center;
            min-width: min(430px, 100%);
        }
        .search input {
            width: 100%;
            min-height: 50px;
            border: 3px solid var(--line);
            border-radius: 3px;
            background: var(--panel);
            color: var(--ink);
            padding: 0 14px;
            font: inherit;
            font-weight: 800;
            box-shadow: 4px 4px 0 var(--line);
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 22px;
            padding-bottom: 70px;
        }
        .card {
            background: var(--panel);
            border: 3px solid var(--line);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform .16s ease, box-shadow .16s ease;
        }
        .card:hover { transform: translate(3px, 3px); box-shadow: 3px 3px 0 var(--line); }
        .card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: var(--brand-soft); border-bottom: 3px solid var(--line); display: block; }
        .card-body { padding: 18px; }
        .product-title { min-height: 54px; font-size: 18px; font-weight: 950; line-height: 1.12; letter-spacing: -.03em; }
        .meta { margin-top: 10px; color: var(--muted); font-size: 13px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .price { margin: 18px 0; font-size: 30px; line-height: 1; font-weight: 950; letter-spacing: -.06em; }
        .empty {
            margin-bottom: 70px;
            border: 3px dashed var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            padding: 28px;
            color: var(--muted);
            font-size: 18px;
            font-weight: 900;
            box-shadow: var(--shadow);
        }
        .proofs {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 18px;
            margin: 12px 0 42px;
        }
        .proof {
            border: 3px solid var(--line);
            background: var(--panel);
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: 5px 5px 0 var(--line);
        }
        .proof strong { display: block; font-size: 20px; line-height: 1.1; margin-bottom: 8px; }
        .proof span { color: var(--muted); font-weight: 800; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin: -16px 0 42px; }
        .stat-card { border: 3px solid var(--line); background: var(--panel); border-radius: var(--radius); padding: 16px; box-shadow: 4px 4px 0 var(--line); }
        .stat-card strong { display: block; font-size: 28px; line-height: 1; letter-spacing: -.05em; }
        .stat-card span { display: block; margin-top: 7px; color: var(--muted); font-size: 12px; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .status-pill { display: inline-flex; margin-top: 10px; border: 2px solid var(--line); background: #fff7d6; padding: 6px 8px; font-size: 11px; font-weight: 950; text-transform: uppercase; letter-spacing: .04em; }
        .status-pill.network { background: var(--brand-soft); color: var(--brand); }
        .offer-summary { margin-top: 12px; color: var(--muted); font-size: 13px; font-weight: 850; line-height: 1.35; }
        .card-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 16px; }
        .text-link { color: var(--brand); font-weight: 950; }
        .marketplace-footer {
            width: min(1180px, calc(100vw - 32px));
            margin: 18px auto 40px;
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.2fr) repeat(3, minmax(0, 1fr));
            gap: 24px;
            padding: 26px;
        }
        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-weight: 950;
            letter-spacing: -.05em;
            margin-bottom: 14px;
        }
        .footer-brand-block p,
        .footer-proof-block p {
            margin: 0;
            color: var(--muted);
            font-weight: 800;
            line-height: 1.45;
        }
        .footer-links-block {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .footer-title {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .footer-links-block a {
            font-weight: 900;
            color: var(--ink);
        }
        .footer-links-block a:hover { color: var(--brand); }
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 26px;
            border-top: 3px solid var(--line);
            background: var(--brand-soft);
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        @media (max-width: 820px) {
            .nav-inner { grid-template-columns: 1fr; padding: 16px 0; }
            .nav-links, .nav-actions { justify-content: flex-start; flex-wrap: wrap; }
            .intent-row { grid-template-columns: 1fr; }
            .section-head { align-items: stretch; flex-direction: column; }
            .search { min-width: 0; }
            .proofs { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; }
        }

        /* AI Chat Assistant Drawer */
        .ai-chat-drawer {
            position: fixed;
            top: 0;
            right: -420px;
            width: 400px;
            height: 100vh;
            background: #ffffff;
            border-left: 5px solid #050505;
            box-shadow: -10px 0 0 rgba(5,5,5,0.08);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .ai-chat-drawer.open {
            right: 0;
        }
        .ai-chat-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.4);
            z-index: 999;
            display: none;
            backdrop-filter: blur(2px);
        }
        .ai-chat-header {
            background: var(--brand);
            color: #ffffff;
            border-bottom: 4px solid #050505;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ai-chat-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 950;
            letter-spacing: -0.03em;
        }
        .ai-chat-close {
            background: #ffffff;
            color: #050505;
            border: 3px solid #050505;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 2px 2px 0 #050505;
            transition: all 0.1s ease;
        }
        .ai-chat-close:hover {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 #050505;
        }
        .ai-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f8fafc;
        }
        .ai-chat-bubble {
            max-width: 85%;
            padding: 12px 16px;
            border: 3px solid #050505;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
            font-weight: 750;
            box-shadow: 4px 4px 0 #050505;
        }
        .ai-chat-bubble.assistant {
            background: #ffffff;
            align-self: flex-start;
        }
        .ai-chat-bubble.user {
            background: var(--brand-soft);
            align-self: flex-end;
        }
        .ai-chat-bubble.error {
            background: #fee2e2;
            border-color: #ef4444;
            align-self: flex-start;
            box-shadow: 4px 4px 0 #ef4444;
        }
        .chat-product-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 3px solid #050505;
            background: #d8ff6f;
            color: #050505 !important;
            border-radius: 4px;
            padding: 8px 12px;
            font-weight: 950;
            margin-top: 8px;
            box-shadow: 3px 3px 0 #050505;
            transition: all 0.1s ease;
            text-decoration: none;
        }
        .chat-product-link:hover {
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 #050505;
            background: #c3e664;
        }
        .ai-chat-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }
        .ai-chat-chip {
            background: #ffffff;
            border: 2px solid #050505;
            border-radius: 12px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 900;
            cursor: pointer;
            box-shadow: 2px 2px 0 #050505;
            transition: all 0.1s ease;
        }
        .ai-chat-chip:hover {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0 #050505;
            background: var(--brand-soft);
        }
        .ai-chat-footer {
            border-top: 4px solid #050505;
            padding: 16px;
            background: #ffffff;
        }
        .ai-chat-input-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }
        .ai-chat-input-wrapper input {
            border: 3px solid #050505;
            border-radius: 6px;
            padding: 12px;
            font-size: 14px;
            font-weight: 800;
            outline: none;
            background: var(--bg);
        }
        .ai-chat-input-wrapper button {
            border: 3px solid #050505;
            background: var(--brand);
            color: #ffffff;
            border-radius: 6px;
            width: 46px;
            height: 46px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 3px 3px 0 #050505;
            transition: all 0.1s ease;
        }
        .ai-chat-input-wrapper button:hover {
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 #050505;
        }
        .ai-chat-typing {
            align-self: flex-start;
            display: flex;
            gap: 4px;
            padding: 8px 12px;
            border: 2px solid #050505;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 2px 2px 0 #050505;
        }
        .ai-chat-typing span {
            width: 6px;
            height: 6px;
            background: #050505;
            border-radius: 50%;
            animation: bounceChatDot 1.4s infinite ease-in-out both;
        }
        .ai-chat-typing span:nth-child(1) { animation-delay: -0.32s; }
        .ai-chat-typing span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounceChatDot {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1.0); }
        }

        @media (max-width: 480px) {
            .ai-chat-drawer {
                width: 100%;
                right: -100%;
                border-left: none;
            }
        }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <section class="hero">
            <div class="eyebrow">Digital gift cards and subscriptions</div>
            <h1>Meanly помогает быстро найти цифровой товар.</h1>
            <p class="lead">Мгновенная покупка подарочных карт, игровых кодов и подписок. Моментальная доставка активационных кодов на вашу электронную почту сразу после оплаты.</p>
            <div class="hero-search-panel">
                <div class="intent-card">
                    <label for="intent">Поиск по каталогу</label>
                    <form method="GET" action="{{ route('meanly.storefront.index') }}">
                        <div class="intent-row">
                            <input id="intent" name="q" type="search" value="{{ $query }}" placeholder="Steam Turkey, PSN 20 EUR, Spotify US..." autocomplete="off" data-live-search-input data-live-search-url="{{ route('meanly.storefront.suggest') }}">
                            <button class="btn btn-primary" type="submit">Найти</button>
                        </div>
                        <p class="hero-search-note">
                            Ищем по совпадениям и содержанию карточки: название, бренд, категория, регион, номинал, валюта, продавец и активный оффер.
                            <span data-live-search-status aria-live="polite"></span>
                        </p>
                        <div class="suggest-panel" data-live-suggestions aria-label="Быстрые подсказки поиска"></div>
                        <div class="chips">
                            @foreach(data_get($homepage, 'quick_chips', []) as $chip)
                                <a class="chip" href="{{ route('meanly.storefront.index', ['q' => $chip['query']]) }}#storefront">{{ $chip['label'] }}</a>
                            @endforeach
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div data-live-search-results>
            @include('storefront.partials.search-results', ['query' => $query, 'products' => $products])
        </div>

        <section class="proofs" id="infrastructure">
            <div class="proof"><strong>🚀 Мгновенная доставка</strong><span>получите ваш лицензионный код на email в течение 1 минуты после оплаты</span></div>
            <div class="proof"><strong>🛡️ 100% Гарантия</strong><span>все ключи и карты закупаются напрямую у надежных авторизованных партнеров</span></div>
            <div class="proof"><strong>💬 Заботливый ИИ и саппорт</strong><span>Meanly AI и служба поддержки помогут с подбором и активацией</span></div>
        </section>

        @if(auth()->check() && (auth()->user()->isB2BPartner() || auth()->user()->isSystemUser()))
        <section class="stats-grid" aria-label="Marketplace catalog stats">
            <div class="stat-card"><strong>{{ number_format((int) data_get($homepage, 'stats.total_canonical_products', 0), 0, '.', ' ') }}</strong><span>товаров в каталоге</span></div>
            <div class="stat-card"><strong>{{ number_format((int) data_get($homepage, 'stats.provider_backed_products', 0), 0, '.', ' ') }}</strong><span>доступно для подключения</span></div>
            <div class="stat-card"><strong>{{ number_format((int) data_get($homepage, 'stats.seller_offer_products', 0), 0, '.', ' ') }}</strong><span>с предложениями</span></div>
            <div class="stat-card"><strong>{{ number_format((int) data_get($homepage, 'stats.public_storefront_products', 0), 0, '.', ' ') }}</strong><span>на витрине</span></div>
            <div class="stat-card"><strong>{{ number_format((int) data_get($homepage, 'stats.review_excluded_products', 0), 0, '.', ' ') }}</strong><span>скоро появятся</span></div>
        </section>
        @endif

        <section class="shelf">
            <div class="shelf-title">
                <div>
                    <h2>Популярные группы</h2>
                    <span>Сначала выберите продукт, затем регион и номинал</span>
                </div>
                <a class="text-link" href="{{ route('meanly.catalog.index') }}">Весь каталог</a>
            </div>
            <div class="category-grid">
                @forelse(data_get($homepage, 'product_groups', []) as $groupCard)
                    @php($variantGroup = (array) data_get($groupCard, 'variant_group', []))
                    <a class="category-card" href="{{ $groupCard['url'] }}">
                        <strong>{{ $groupCard['name'] }}</strong>
                        <span>
                            {{ (int) ($variantGroup['variant_count'] ?? 1) }} вариантов
                            @if(($variantGroup['region_count'] ?? 0) > 0) · {{ (int) $variantGroup['region_count'] }} регионов @endif
                            @if(($variantGroup['nominal_count'] ?? 0) > 0) · {{ (int) $variantGroup['nominal_count'] }} номиналов @endif
                        </span>
                    </a>
                @empty
                    <div class="empty">Группы появятся после наполнения каталога.</div>
                @endforelse
            </div>
        </section>

        <section class="shelf">
            <div class="shelf-title">
                <div>
                    <h2>Лучшие офферы сейчас</h2>
                    <span>Товары, которые уже можно открыть у продавца</span>
                </div>
                <a class="text-link" href="{{ route('meanly.catalog.index') }}">Все категории</a>
            </div>
            <div class="rail">
                @forelse(data_get($homepage, 'featured_products', []) as $product)
                    <article class="mini-card">
                        <div>
                            <a href="{{ $product['url'] }}"><h3>{{ $product['name'] }}</h3></a>
                            <div class="meta">{{ $product['category_label'] }}</div>
                            @if(!empty($product['face_value']) && (float)$product['face_value'] > 0)
                                <div style="margin-top: 6px; margin-bottom: 6px;">
                                    <span class="tag" style="background: #e7fff2; border: 1.5px solid var(--line); font-size: 10px; padding: 3px 6px; box-shadow: 1.5px 1.5px 0 var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">💰 пополнение: {{ $product['face_value'] }} {{ $product['face_value_currency'] }}</span>
                                </div>
                            @endif
                            <span class="status-pill">Доступно</span>
                            @if(data_get($product, 'selected_offer'))
                                <div class="offer-summary">
                                    {{ data_get($product, 'selected_offer.seller.name') }} · {{ number_format((float) data_get($product, 'selected_offer.price.amount'), 2, '.', ' ') }} ₽ · {{ data_get($product, 'selected_offer.availability') }}
                                </div>
                            @endif
                        </div>
                        <div class="card-actions">
                            <a class="btn btn-primary" href="{{ $product['url'] }}">Открыть</a>
                        </div>
                    </article>
                @empty
                    <div class="empty">Предложения продавцов появятся после подключения товаров к витрине.</div>
                @endforelse
            </div>
        </section>

        <section class="shelf">
            <div class="shelf-title">
                <div>
                    <h2>Скоро в продаже</h2>
                    @if(auth()->check() && (auth()->user()->isB2BPartner() || auth()->user()->isSystemUser()))
                        <span>Товары уже доступны для подключения продавцом, но покупка еще не открыта</span>
                    @else
                        <span>Товары, которые появятся в наличии в ближайшее время</span>
                    @endif
                </div>
                @if(auth()->check() && (auth()->user()->isB2BPartner() || auth()->user()->isSystemUser()))
                    <a class="text-link" href="{{ route('business.register') }}">Подключить как продавец</a>
                @endif
            </div>
            <div class="rail">
                @forelse(data_get($homepage, 'provider_network_products', []) as $product)
                    <article class="mini-card">
                        <div>
                            <a href="{{ $product['url'] }}"><h3>{{ $product['name'] }}</h3></a>
                            <div class="meta">{{ $product['category_label'] }}</div>
                            @if(!empty($product['face_value']) && (float)$product['face_value'] > 0)
                                <div style="margin-top: 6px; margin-bottom: 6px;">
                                    <span class="tag" style="background: #e7fff2; border: 1.5px solid var(--line); font-size: 10px; padding: 3px 6px; box-shadow: 1.5px 1.5px 0 var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">💰 пополнение: {{ $product['face_value'] }} {{ $product['face_value_currency'] }}</span>
                                </div>
                            @endif
                            <span class="status-pill network">Скоро доступно</span>
                            @if(auth()->check() && (auth()->user()->isB2BPartner() || auth()->user()->isSystemUser()))
                                <div class="offer-summary">Товар есть в партнерской сети поставки. Продавец может подключить его к витрине.</div>
                            @else
                                <div class="offer-summary">Товар временно отсутствует на складе. Ожидайте поступления в продажу.</div>
                            @endif
                        </div>
                        <div class="card-actions">
                            <a class="btn btn-primary" href="{{ $product['url'] }}">Открыть</a>
                            @if(auth()->check() && (auth()->user()->isB2BPartner() || auth()->user()->isSystemUser()))
                                <a class="text-link" href="{{ route('business.register') }}">Подключить</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty">Пока нет товаров, ожидающих подключения продавцом.</div>
                @endforelse
            </div>
        </section>

        <section class="shelf">
            <div class="shelf-title">
                <div>
                    <h2>Популярные бренды</h2>
                    <span>Покупайте цифровые товары и пополнения по брендам</span>
                </div>
            </div>
            <div class="brands-grid">
                @forelse(data_get($homepage, 'brands', []) as $brand)
                    <a class="brand-card" href="{{ $brand['url'] }}#storefront">
                        <strong>{{ $brand['name'] }}</strong>
                        <span>{{ $brand['count'] }} товаров @if($brand['seller_offer_count'] > 0) · <span class="badge-active">{{ $brand['seller_offer_count'] }} в наличии</span>@endif</span>
                    </a>
                @empty
                    <div class="empty">Бренды появятся после наполнения каталога.</div>
                @endforelse
            </div>
        </section>

        <section class="shelf">
            <div class="shelf-title">
                <div>
                    <h2>Категории каталога</h2>
                    <span>Игры, подарочные карты, подписки и другие цифровые товары</span>
                </div>
            </div>
            <div class="category-grid">
                @forelse(data_get($homepage, 'categories', []) as $category)
                    <a class="category-card" href="{{ $category['url'] }}">
                        <strong>{{ $category['name'] }}</strong>
                        <span>{{ $category['count'] }} товаров · {{ $category['seller_offer_count'] }} с предложениями</span>
                    </a>
                @empty
                    <div class="empty">Категории появятся после наполнения каталога.</div>
                @endforelse
            </div>
        </section>

    </main>
    @include('storefront.partials.footer')

    <!-- AI Chat Assistant Backdrop Overlay -->
    <div id="aiChatOverlay" class="ai-chat-overlay"></div>

    <!-- AI Chat Assistant Drawer -->
    <div id="aiChatDrawer" class="ai-chat-drawer">
        <div class="ai-chat-header">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 1.4rem;">🪄</span>
                <div>
                    <h3 style="margin: 0; line-height: 1;">Meanly AI</h3>
                    <span style="font-size: 10px; font-weight: 800; text-transform: uppercase; opacity: 0.85; letter-spacing: 0.05em;">Локальный ИИ-Ассистент</span>
                </div>
            </div>
            <button id="aiChatClose" class="ai-chat-close" title="Закрыть">&times;</button>
        </div>
        <div id="aiChatMessages" class="ai-chat-messages">
            <div class="ai-chat-bubble assistant">
                Привет! Я Meanly AI, твой персональный ИИ-помощник. 🎮
                <br><br>
                Помогу выбрать идеальную карту пополнения, подписку или игровой ключ! Напиши, что именно ты ищешь или выбери один из быстрых вариантов ниже.
            </div>
        </div>
        <div class="ai-chat-footer">
            <div class="ai-chat-chips">
                <button type="button" class="ai-chat-chip" onclick="sendChipQuery('Покажи Steam Турция')">🎮 Steam TR</button>
                <button type="button" class="ai-chat-chip" onclick="sendChipQuery('PlayStation Network USA')">💳 PSN US</button>
                <button type="button" class="ai-chat-chip" onclick="sendChipQuery('Подписка Spotify')">🎵 Spotify</button>
                <button type="button" class="ai-chat-chip" onclick="sendChipQuery('Xbox gift card')">💚 Xbox</button>
            </div>
            <form id="aiChatForm" onsubmit="handleChatSubmit(event)">
                <div class="ai-chat-input-wrapper">
                    <input type="text" id="aiChatInput" placeholder="Спросите Meanly AI..." autocomplete="off">
                    <button type="submit" aria-label="Отправить">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="transform: rotate(45deg);"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dynamic AI Chat Controller Script -->
    <script>
        (() => {
            const input = document.querySelector('[data-live-search-input]');
            const suggestions = document.querySelector('[data-live-suggestions]');
            const status = document.querySelector('[data-live-search-status]');

            if (!input || !suggestions) return;

            const endpoint = input.dataset.liveSearchUrl;
            let timer = null;
            let controller = null;
            let lastQuery = input.value.trim();

            const setStatus = (text) => {
                if (status) status.textContent = text ? ` ${text}` : '';
            };

            const escape = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

            const closeSuggestions = () => {
                suggestions.classList.remove('is-open');
                suggestions.innerHTML = '';
            };

            const renderSuggestions = (items) => {
                if (!items.length) {
                    closeSuggestions();
                    return;
                }

                suggestions.innerHTML = items.map((item) => {
                    const meta = [item.category, item.match_label].filter(Boolean).join(' · ');
                    const price = item.price?.formatted || (item.availability === 'soon' ? 'Скоро' : '');

                    return `<a class="suggest-item" href="${escape(item.url)}">
                        <span>
                            <span class="suggest-title">${escape(item.name)}</span>
                            <span class="suggest-meta">${escape(meta)}</span>
                        </span>
                        <span class="suggest-price">${escape(price)}</span>
                    </a>`;
                }).join('');
                suggestions.classList.add('is-open');
            };

            const runSuggest = async () => {
                const query = input.value.trim();

                if (query === lastQuery) return;
                lastQuery = query;

                if (controller) controller.abort();

                if (query.length === 0) {
                    closeSuggestions();
                    setStatus('');
                    return;
                }

                if (query.length < 2) {
                    closeSuggestions();
                    setStatus('Введите минимум 2 символа.');
                    return;
                }

                controller = new AbortController();
                setStatus('Подбираем...');

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('q', query);

                    const response = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        signal: controller.signal,
                    });

                    if (!response.ok) throw new Error('Search failed');

                    const payload = await response.json();
                    const items = Array.isArray(payload.results) ? payload.results : [];
                    renderSuggestions(items);
                    setStatus(items.length ? `Подсказок: ${items.length}. Enter для полного поиска.` : 'Ничего не найдено.');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        closeSuggestions();
                        setStatus('Не удалось обновить результаты.');
                    }
                }
            };

            input.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(runSuggest, 220);
            });

            document.addEventListener('click', (event) => {
                if (!suggestions.contains(event.target) && event.target !== input) {
                    closeSuggestions();
                }
            });
        })();

        const chatTrigger = document.getElementById('aiChatTrigger');
        const chatClose = document.getElementById('aiChatClose');
        const chatDrawer = document.getElementById('aiChatDrawer');
        const chatOverlay = document.getElementById('aiChatOverlay');
        const chatMessages = document.getElementById('aiChatMessages');
        const chatForm = document.getElementById('aiChatForm');
        const chatInput = document.getElementById('aiChatInput');

        let chatHistory = [];
        let isWaitingForAi = false;

        // The floating trigger was removed from the storefront; keep the drawer reusable.
        if (chatTrigger) {
            chatTrigger.addEventListener('click', () => {
                chatDrawer.classList.add('open');
                chatOverlay.style.display = 'block';
                chatInput.focus();
            });
        }

        const closeDrawer = () => {
            chatDrawer.classList.remove('open');
            chatOverlay.style.display = 'none';
        };

        chatClose?.addEventListener('click', closeDrawer);
        chatOverlay?.addEventListener('click', closeDrawer);

        // Escape html helper
        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Custom markdown parser
        function parseMarkdown(text) {
            let parsed = escapeHtml(text);
            
            // Format bold text
            parsed = parsed.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            
            // Parse markdown links: [label](url) -> interactive Neobrutalism card pill
            parsed = parsed.replace(/\[([^\]]+)\]\(([^)]+)\)/g, function(match, label, url) {
                return `<a href="${url}" class="chat-product-link">
                    <span>${label}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                </a>`;
            });

            // Convert newlines to breaks
            return parsed.replace(/\n/g, '<br>');
        }

        // Scroll to bottom of chat
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Append message bubble helper
        function appendBubble(role, content, isHtml = false) {
            const bubble = document.createElement('div');
            bubble.classList.add('ai-chat-bubble', role);
            if (isHtml) {
                bubble.innerHTML = content;
            } else {
                bubble.innerHTML = parseMarkdown(content);
            }
            chatMessages.appendChild(bubble);
            scrollToBottom();
            return bubble;
        }

        // Handle typing indicator
        let typingIndicator = null;
        function showTypingIndicator() {
            if (typingIndicator) return;
            typingIndicator = document.createElement('div');
            typingIndicator.classList.add('ai-chat-typing');
            typingIndicator.innerHTML = '<span></span><span></span><span></span>';
            chatMessages.appendChild(typingIndicator);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            if (typingIndicator) {
                typingIndicator.remove();
                typingIndicator = null;
            }
        }

        // Trigger message post
        async function submitUserMessage(messageText) {
            if (isWaitingForAi || !messageText.trim()) return;

            // 1. Render user message
            appendBubble('user', messageText);
            chatInput.value = '';

            // 2. Set loading states
            isWaitingForAi = true;
            showTypingIndicator();

            try {
                const response = await fetch('{{ route("storefront.chat") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        message: messageText,
                        history: chatHistory
                    })
                });

                const data = await response.json();
                removeTypingIndicator();
                isWaitingForAi = false;

                if (data.success && data.response) {
                    // Render assistant response
                    appendBubble('assistant', data.response);

                    // Add to conversational history memory
                    chatHistory.push({ role: 'user', content: messageText });
                    chatHistory.push({ role: 'assistant', content: data.response });

                    // Keep history memory compact (last 10 messages)
                    if (chatHistory.length > 10) {
                        chatHistory = chatHistory.slice(-10);
                    }
                } else {
                    appendBubble('error', data.error || 'Извините, возникла непредвиденная ошибка при запросе к ИИ.');
                }

            } catch (error) {
                removeTypingIndicator();
                isWaitingForAi = false;
                appendBubble('error', 'Ошибка сети. Убедитесь, что локальный веб-сервер и Ollama запущены корректно.');
            }
        }

        // Handles regular input submission
        function handleChatSubmit(e) {
            e.preventDefault();
            const text = chatInput.value;
            submitUserMessage(text);
        }

        // Handles quick chips clicks
        function sendChipQuery(chipText) {
            if (isWaitingForAi) return;
            submitUserMessage(chipText);
        }

    </script>
</body>
</html>
