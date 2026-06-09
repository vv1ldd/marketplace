<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['title'] }} | Meanly</title>
    <meta name="description" content="{{ $page['description'] }}">
    <link rel="canonical" href="{{ url()->current() }}">
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
            --shadow: 8px 8px 0 #050505;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background:
                linear-gradient(90deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                radial-gradient(circle at 50% -120px, rgba(124, 58, 237, .16) 0%, transparent 38rem),
                var(--bg);
            background-size: 28px 28px, 28px 28px, auto, auto;
            color: var(--ink);
            font-family: "Outfit", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .legal-shell { width: min(1120px, calc(100vw - 32px)); margin: 0 auto; padding: 112px 0 72px; }
        .legal-hero,
        .legal-card {
            background: var(--panel);
            border: 4px solid var(--line);
            border-radius: 14px;
            box-shadow: var(--shadow);
        }
        .legal-hero { padding: clamp(24px, 5vw, 44px); }
        .legal-eyebrow {
            display: inline-flex;
            padding: 6px 10px;
            border: 2px solid var(--line);
            background: var(--brand-soft);
            color: var(--brand);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            box-shadow: 2px 2px 0 var(--line);
        }
        h1 {
            margin: 16px 0 14px;
            font-size: clamp(38px, 7vw, 78px);
            line-height: .9;
            letter-spacing: -.07em;
            font-weight: 950;
        }
        .legal-lead {
            max-width: 840px;
            margin: 0;
            color: var(--muted);
            font-size: 18px;
            font-weight: 750;
            line-height: 1.55;
        }
        .legal-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }
        .legal-nav a {
            border: 2px solid var(--line);
            border-radius: 999px;
            background: #fff;
            box-shadow: 2px 2px 0 var(--line);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            padding: 8px 10px;
            text-transform: uppercase;
        }
        .legal-nav a.active { background: #d8ff6f; }
        .legal-grid {
            display: grid;
            gap: 18px;
            grid-template-columns: minmax(0, 1.25fr) minmax(280px, .75fr);
            margin-top: 22px;
        }
        .legal-stack { display: grid; gap: 18px; }
        .legal-card { padding: 22px; }
        .legal-card h2 {
            margin: 0 0 12px;
            font-size: 24px;
            font-weight: 950;
            letter-spacing: -.04em;
        }
        .legal-card ul { margin: 0; padding-left: 20px; }
        .legal-card li { margin: 8px 0; color: var(--muted); font-size: 16px; font-weight: 700; line-height: 1.5; }
        .legal-table { display: grid; gap: 10px; }
        .legal-row {
            display: grid;
            gap: 6px;
            grid-template-columns: 150px minmax(0, 1fr);
            border-bottom: 1px solid rgba(5, 5, 5, .12);
            padding-bottom: 10px;
        }
        .legal-row span:first-child {
            color: var(--muted);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .legal-row span:last-child { font-weight: 850; }
        .legal-note {
            border: 3px solid var(--line);
            background: #fff7d6;
            box-shadow: 4px 4px 0 var(--line);
            font-weight: 850;
            line-height: 1.5;
            margin-top: 16px;
            padding: 14px;
        }
        .marketplace-footer {
            background: #f8fafc;
            border: 2px solid var(--line);
            border-radius: 24px;
            box-shadow: 3px 3px 0 var(--line);
            margin: 0 auto 40px;
            overflow: hidden;
            width: min(1180px, calc(100vw - 32px));
        }
        .footer-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: minmax(260px, 1.2fr) repeat(4, minmax(0, 1fr));
            padding: 16px 18px;
        }
        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-weight: 950;
            letter-spacing: -.05em;
            margin-bottom: 8px;
        }
        .logo-mark {
            width: 15px;
            height: 15px;
            background: var(--brand);
            border: 2px solid var(--line);
            box-shadow: 2px 2px 0 var(--line);
            display: inline-block;
            transform: rotate(-8deg);
        }
        .footer-brand-block p,
        .footer-bottom {
            color: var(--muted);
            font-weight: 800;
            line-height: 1.35;
        }
        .footer-links-block {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }
        .footer-title {
            color: var(--muted);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .footer-links-block a {
            color: var(--ink);
            font-size: 13px;
            font-weight: 900;
        }
        .footer-bottom {
            border-top: 2px solid var(--line);
            display: flex;
            gap: 16px;
            justify-content: space-between;
            font-size: 10px;
            letter-spacing: .04em;
            padding: 9px 18px;
            text-transform: uppercase;
        }
        @media (max-width: 820px) {
            .legal-grid { grid-template-columns: 1fr; }
            .legal-row { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; }
        }
    </style>
</head>
<body class="meanly-buyer-page">
@include('storefront.partials.header')
<main class="legal-shell">
    <section class="legal-hero">
        <span class="legal-eyebrow">{{ $page['eyebrow'] }}</span>
        <h1>{{ $page['title'] }}</h1>
        <p class="legal-lead">{{ $page['description'] }}</p>
        <nav class="legal-nav" aria-label="Юридические документы">
            @foreach ($pages as $key => $item)
                <a class="{{ $pageKey === $key ? 'active' : '' }}" href="{{ url('/'.$key) }}">{{ $item['title'] }}</a>
            @endforeach
        </nav>
    </section>

    <div class="legal-grid">
        <div class="legal-stack">
            @foreach ($page['sections'] as $section)
                <article class="legal-card">
                    <h2>{{ $section['title'] }}</h2>
                    <ul>
                        @foreach ($section['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>

        <aside class="legal-card">
            <h2>Реквизиты и оплата</h2>
            <div class="legal-table">
                <div class="legal-row"><span>Бренд</span><span>{{ data_get($company, 'brand') }}</span></div>
                <div class="legal-row"><span>Юр. лицо</span><span>{{ data_get($company, 'legal_name') }}</span></div>
                <div class="legal-row"><span>Страна</span><span>{{ data_get($company, 'registered_country') }}</span></div>
                <div class="legal-row"><span>Юр. адрес</span><span>{{ data_get($company, 'legal_address') }}</span></div>
                <div class="legal-row"><span>Телефон</span><span>{{ data_get($company, 'phone') }}</span></div>
                <div class="legal-row"><span>Email</span><span>{{ data_get($company, 'email') }}</span></div>
                <div class="legal-row"><span>ИНН</span><span>{{ data_get($company, 'inn') }}</span></div>
                <div class="legal-row"><span>ОГРН</span><span>{{ data_get($company, 'ogrn') }}</span></div>
                <div class="legal-row"><span>Эквайринг</span><span>{{ data_get($bank, 'name') }}</span></div>
                <div class="legal-row"><span>HTTPS</span><span>{{ data_get($bank, 'ssl_level') }}</span></div>
                <div class="legal-row"><span>Карты</span><span>{{ implode(', ', (array) data_get($bank, 'payment_systems', [])) }}</span></div>
            </div>
            <p class="legal-note">Перед заявкой в банк замените env-переменные ACQUIRING_COMPANY_* и ACQUIRING_BANK_NAME на согласованные юридические данные.</p>
        </aside>
    </div>
</main>
@include('storefront.partials.footer')
</body>
</html>
