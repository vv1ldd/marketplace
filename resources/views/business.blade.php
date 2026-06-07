<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business — Meanly</title>
    <meta name="description" content="Meanly business services: B2B onboarding, digital voucher fulfillment, payments, reporting and marketplace channel adapters.">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.services.index') }}">
    @isset($serviceJsonLd)
        <script type="application/ld+json">{!! json_encode($serviceJsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    @isset($serviceFacts)
        <script type="application/json" id="meanly-service-facts">{!! json_encode($serviceFacts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600;700;800;900&family=JetBrains+Mono:wght@500;700;800&family=Outfit:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #050505;
            --bg-2: #080808;
            --card: #0b0b0b;
            --card-strong: rgba(11, 11, 11, 0.86);
            --text: #ffffff;
            --text-on-brand: #ffffff;
            --muted: #8e8e93;
            --lead: #b6b6bc;
            --border: rgba(255, 255, 255, 0.08);
            --border-strong: rgba(245, 48, 3, 0.24);
            --brand: #f53003;
            --brand-rgb: 245, 48, 3;
            --radius: 20px;
            --panel-radius: 24px;
            --shadow: 0 30px 90px rgba(0, 0, 0, 0.45);
            --font-main: "Instrument Sans", ui-sans-serif, system-ui, sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, monospace;
        }
        html[data-theme="partner"],
        body[data-theme="partner"] {
            --bg: #070707;
            --bg-2: #151008;
            --card: rgba(255, 255, 255, 0.045);
            --card-strong: rgba(255, 255, 255, 0.065);
            --text: #fffaf0;
            --text-on-brand: #111111;
            --muted: #9d8d72;
            --lead: #d8c7a8;
            --border: rgba(255, 255, 255, 0.10);
            --border-strong: rgba(255, 159, 10, 0.34);
            --brand: #ff9f0a;
            --brand-rgb: 255, 159, 10;
            --radius: 22px;
            --panel-radius: 30px;
            --shadow: 0 30px 90px rgba(255, 159, 10, 0.08), 0 26px 80px rgba(0, 0, 0, 0.48);
            --font-main: "Outfit", ui-sans-serif, system-ui, sans-serif;
        }
        html[data-theme="retro"],
        body[data-theme="retro"] {
            --bg: #eef0fc;
            --bg-2: #ffffff;
            --card: #ffffff;
            --card-strong: #ffffff;
            --text: #000000;
            --text-on-brand: #ffffff;
            --muted: #4b5563;
            --lead: #1f2937;
            --border: #000000;
            --border-strong: #000000;
            --brand: #7c3aed;
            --brand-rgb: 124, 58, 237;
            --radius: 8px;
            --panel-radius: 10px;
            --shadow: 8px 8px 0 #000000;
            --font-main: "Outfit", ui-sans-serif, system-ui, sans-serif;
        }
        html[data-theme="nordic"],
        body[data-theme="nordic"] {
            --bg: #07111f;
            --bg-2: #0e1b2e;
            --card: rgba(226, 240, 255, 0.045);
            --card-strong: rgba(226, 240, 255, 0.065);
            --text: #f8fbff;
            --muted: #8aa4bd;
            --lead: #c9d9e8;
            --border-strong: rgba(56, 189, 248, 0.32);
            --brand: #38bdf8;
            --brand-rgb: 56, 189, 248;
            --text-on-brand: #07111f;
        }
        html[data-theme="synthwave"],
        body[data-theme="synthwave"] {
            --bg: #11051e;
            --bg-2: #1f0b35;
            --card: rgba(236, 72, 153, 0.055);
            --card-strong: rgba(99, 102, 241, 0.08);
            --text: #fff7ff;
            --muted: #c084fc;
            --lead: #f0abfc;
            --border-strong: rgba(236, 72, 153, 0.36);
            --brand: #ec4899;
            --brand-rgb: 236, 72, 153;
        }
        html[data-theme="carbon"],
        body[data-theme="carbon"] {
            --bg: #020617;
            --bg-2: #0f172a;
            --card: rgba(148, 163, 184, 0.045);
            --card-strong: rgba(15, 23, 42, 0.86);
            --text: #f8fafc;
            --muted: #94a3b8;
            --lead: #cbd5e1;
            --border-strong: rgba(148, 163, 184, 0.28);
            --brand: #94a3b8;
            --brand-rgb: 148, 163, 184;
            --text-on-brand: #020617;
        }
        * { box-sizing: border-box; }
        html { overflow-x: hidden; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 50% -10%, rgba(var(--brand-rgb), 0.18), transparent 36%),
                radial-gradient(circle at 84% 68%, rgba(var(--brand-rgb), 0.08), transparent 32%),
                linear-gradient(135deg, var(--bg), var(--bg-2));
            color: var(--text);
            font-family: var(--font-main);
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }
        body[data-theme="retro"] {
            background:
                linear-gradient(90deg, rgba(0, 0, 0, 0.035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, 0.035) 1px, transparent 1px),
                var(--bg);
            background-size: 26px 26px;
        }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1120px, calc(100vw - 32px)); margin: 0 auto; }
        nav {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .nav-container {
            width: 100%;
            max-width: 1200px;
            display: grid;
            grid-template-columns: minmax(180px, 1fr) auto minmax(180px, 1fr);
            align-items: center;
            gap: 24px;
        }
        .logo { display: inline-flex; align-items: center; gap: 10px; font-weight: 900; letter-spacing: -0.04em; }
        .logo-mark { width: 12px; height: 12px; background: var(--brand); border-radius: 3px; box-shadow: 0 0 20px rgba(var(--brand-rgb), 0.5); }
        body[data-theme="retro"] .logo-mark { border: 2px solid #000000; box-shadow: 3px 3px 0 #000000; }
        .nav-links { display: flex; gap: 28px; color: var(--muted); font-size: 13px; font-weight: 800; justify-content: center; }
        .nav-actions { display: flex; gap: 12px; align-items: center; justify-content: flex-end; }
        .hero {
            padding: 92px 0 56px;
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            gap: 48px;
            align-items: center;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--brand);
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        h1 {
            margin: 0;
            font-size: clamp(44px, 8vw, 84px);
            line-height: 0.92;
            letter-spacing: -0.08em;
        }
        .lead {
            margin: 24px 0 0;
            max-width: 640px;
            color: var(--lead);
            font-size: clamp(17px, 2.3vw, 22px);
            line-height: 1.55;
        }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 34px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 50px;
            padding: 0 22px;
            border-radius: 12px;
            font-weight: 900;
            border: 1px solid var(--border);
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }
        .btn:hover { transform: translateY(-1px); border-color: var(--border-strong); }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: var(--text-on-brand); box-shadow: 0 18px 48px rgba(var(--brand-rgb), 0.26); }
        .btn-secondary { background: rgba(255, 255, 255, 0.04); color: var(--text); }
        body[data-theme="retro"] .btn { border: 2px solid #000000; border-radius: 8px; box-shadow: 3px 3px 0 #000000; }
        .panel {
            background: var(--card-strong);
            border: 1px solid var(--border);
            border-radius: var(--panel-radius);
            padding: 28px;
            box-shadow: var(--shadow);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        body[data-theme="retro"] .panel {
            border-width: 3px;
            backdrop-filter: none;
            -webkit-backdrop-filter: none;
        }
        .proof-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 18px 0;
            border-bottom: 1px solid var(--border);
        }
        .proof-row:last-child { border-bottom: 0; }
        .proof-label { color: var(--muted); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.12em; }
        .proof-value { font-size: 18px; font-weight: 900; text-align: right; }
        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            padding: 28px 0 76px;
        }
        .card {
            min-height: 210px;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--card);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.16);
        }
        body[data-theme="retro"] .card { border-width: 3px; box-shadow: 6px 6px 0 #000000; }
        .card h2 {
            margin: 0 0 12px;
            font-size: 21px;
            letter-spacing: -0.04em;
        }
        .card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }
        .services-section {
            padding: 0 0 88px;
        }
        .section-kicker {
            display: inline-flex;
            color: var(--brand);
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .section-title {
            max-width: 760px;
            margin: 0 0 18px;
            font-size: clamp(32px, 5vw, 58px);
            line-height: 0.98;
            letter-spacing: -0.07em;
        }
        .section-lead {
            max-width: 760px;
            margin: 0 0 28px;
            color: var(--lead);
            font-size: 18px;
            line-height: 1.55;
        }
        .service-list {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        .business-service-card {
            display: grid;
            gap: 14px;
            min-height: 230px;
            padding: 24px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--card-strong);
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.18);
        }
        body[data-theme="retro"] .business-service-card { border-width: 3px; box-shadow: 6px 6px 0 #000000; }
        .business-service-card h2 {
            margin: 0;
            font-size: 24px;
            letter-spacing: -0.04em;
        }
        .business-service-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.55;
        }
        .service-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: auto;
        }
        .service-pill {
            display: inline-flex;
            border: 1px solid var(--border-strong);
            border-radius: 999px;
            color: var(--lead);
            font-family: var(--font-mono);
            font-size: 11px;
            font-weight: 800;
            padding: 6px 9px;
        }
        .service-link {
            justify-self: start;
            color: var(--brand);
            font-weight: 900;
        }
        @media (max-width: 820px) {
            .hero { grid-template-columns: 1fr; padding-top: 54px; }
            .grid, .service-list { grid-template-columns: 1fr; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('business.partials.header')

    <main class="shell">

        <section id="showcase" class="hero">
            <div>
                <div class="eyebrow"><span class="logo-mark"></span> Meanly для бизнеса</div>
                <h1>Продавайте цифровые товары без ручной рутины.</h1>
                <p class="lead">
                    Meanly Merchant Center помогает подключить продавца, юрлицо, витрины, API, остатки,
                    оплату и выдачу кодов в одном понятном рабочем месте.
                </p>
                <div class="actions">
                    @auth
                        @if(auth()->user()->isMerchantNode())
                            <a class="btn btn-primary" href="{{ route('partner.dashboard') }}">Открыть Merchant Center</a>
                            <a class="btn btn-secondary" href="/vault">Сейф</a>
                        @else
                            <a class="btn btn-primary" href="{{ route('business.register') }}">Подключить бизнес</a>
                            <a class="btn btn-secondary" href="/vault">Сейф</a>
                        @endif
                    @else
                        <a class="btn btn-primary" href="{{ route('business.register') }}">Подключить бизнес</a>
                        <a class="btn btn-secondary" href="/login">У меня уже есть Passkey</a>
                    @endauth
                </div>
            </div>

            <aside class="panel" aria-label="Business capabilities">
                <div class="proof-row">
                    <div class="proof-label">Identity</div>
                    <div class="proof-value">Passkey</div>
                </div>
                <div class="proof-row">
                    <div class="proof-label">Channels</div>
                    <div class="proof-value">Yandex, Ozon, WB</div>
                </div>
                <div class="proof-row">
                    <div class="proof-label">Fulfillment</div>
                    <div class="proof-value">codes in seconds</div>
                </div>
                <div class="proof-row">
                    <div class="proof-label">History</div>
                    <div class="proof-value">clear audit trail</div>
                </div>
            </aside>
        </section>

        <section id="infrastructure" class="grid">
            <article class="card">
                <h2>Сначала профиль, потом бизнес</h2>
                <p>Один человек создает профиль. После этого можно подключить ИП, ООО, самозанятого или команду.</p>
            </article>
            <article class="card">
                <h2>Продажи без ручной выдачи</h2>
                <p>Каталог, остатки, slips, provider codes и webhooks собираются в управляемый business-периметр.</p>
            </article>
            <article class="card">
                <h2>Прозрачная поддержка</h2>
                <p>Заказы, ваучеры, холды и выдача связаны с понятной историей событий, чтобы поддержку было легко вести.</p>
            </article>
        </section>

        @isset($serviceFacts)
            <section class="services-section" aria-label="Meanly business services">
                <span class="section-kicker">Business services</span>
                <h2 class="section-title">Сервисы для продавцов и операторов.</h2>
                <p class="section-lead">
                    Услуги Meanly вынесены в B2B-раздел: onboarding, выдача цифровых ваучеров,
                    платежи, отчеты и адаптеры каналов относятся к business-подключению, а не к покупательскому каталогу.
                </p>
                <div class="service-list">
                    @foreach($serviceFacts as $service)
                        <article class="business-service-card">
                            <h2>{{ $service['name'] }}</h2>
                            <p>{{ $service['description'] }}</p>
                            <div class="service-meta">
                                <span class="service-pill">{{ $service['service_type'] }}</span>
                                <span class="service-pill">{{ $service['delivery_time'] }}</span>
                            </div>
                            <a class="service-link" href="{{ route('business.services.show', $service['slug']) }}">Подробнее →</a>
                        </article>
                    @endforeach
                </div>
            </section>
        @endisset
    </main>
</body>
</html>
