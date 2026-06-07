<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meanly Services - инфраструктура цифровой коммерции</title>
    <meta name="description" content="Meanly services: B2B onboarding, digital voucher fulfillment, Meanly One approvals and marketplace channel adapters.">
    <link rel="canonical" href="{{ route('business.services.index') }}">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.services.index') }}">
    <script type="application/ld+json">{!! json_encode($serviceJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="meanly-service-facts">{!! json_encode($services, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        html, body { overflow-x: hidden; }
        body {
            margin: 0;
            background:
                linear-gradient(90deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                var(--bg);
            background-size: 28px 28px;
            color: var(--ink);
            font-family: "Outfit", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        
        .shell { width: min(1180px, calc(100vw - 32px)); margin: 0 auto; }
        main.shell { padding-top: 96px; padding-bottom: 80px; }
        
        .hero {
            padding: 48px 0 24px;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 360px;
            gap: 28px;
            align-items: end;
        }
        .eyebrow {
            display: inline-flex;
            padding: 6px 10px;
            border: 2px solid var(--line);
            background: var(--brand-soft);
            color: var(--brand);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            box-shadow: 2px 2px 0 var(--line);
            margin-bottom: 12px;
        }
        h1 {
            margin: 12px 0 18px;
            font-size: clamp(42px, 7vw, 84px);
            line-height: .88;
            letter-spacing: -.075em;
            font-weight: 950;
        }
        .lead {
            margin: 0;
            max-width: 760px;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
            font-weight: 700;
        }
        .panel, .service-card {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            border-radius: var(--radius);
        }
        .panel { padding: 24px; }
        .metric {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid var(--line);
            padding: 10px 0;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
        }
        .metric:last-child { border-bottom: 0; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            padding: 28px 0 80px;
        }
        .service-card {
            padding: 24px;
            display: grid;
            gap: 14px;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .service-card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 12px 12px 0 var(--line);
        }
        .service-card h2 {
            margin: 0;
            font-size: 27px;
            line-height: 1;
            letter-spacing: -.04em;
            font-weight: 950;
        }
        .service-card p {
            margin: 0;
            color: var(--muted);
            font-weight: 700;
            line-height: 1.5;
        }
        .tags { display: flex; flex-wrap: wrap; gap: 8px; }
        .tag {
            display: inline-flex;
            border: 2px solid var(--line);
            background: #d8ff6f;
            padding: 5px 8px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            box-shadow: 2px 2px 0 var(--line);
        }
        
        .btn {
            justify-self: start;
            display: inline-flex;
            border: 3px solid var(--line);
            background: var(--brand);
            color: white;
            box-shadow: 4px 4px 0 var(--line);
            padding: 11px 16px;
            font-weight: 950;
            border-radius: 4px;
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .btn:hover { transform: translate(-3px, -3px); box-shadow: 7px 7px 0 var(--line); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 2px 2px 0 var(--line); }
        
        @media (max-width: 820px) { .hero, .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('business.partials.header')

    <main class="shell">
        <section class="hero">
            <div>
                <div class="eyebrow">Service taxonomy · LLM-readable</div>
                <h1>Сервисы Meanly как понятная товарная система.</h1>
                <p class="lead">Каждая услуга описана как стабильная business-сущность: тип сервиса, аудитория, результат, SLA, способ исполнения и machine-readable JSON для Google, Yandex и LLM crawlers.</p>
            </div>
            <aside class="panel">
                <div class="metric"><span>Schema</span><strong>Service + Offer</strong></div>
                <div class="metric"><span>Facts</span><strong>{{ $services->count() }}</strong></div>
                <div class="metric"><span>LLM</span><strong>llms/services.json</strong></div>
            </aside>
        </section>

        <section class="grid" aria-label="Meanly services">
            @foreach($services as $service)
                <article class="service-card">
                    <div class="eyebrow">{{ $service['service_type'] }}</div>
                    <h2>{{ $service['name'] }}</h2>
                    <p>{{ $service['description'] }}</p>
                    <div class="tags">
                        <span class="tag">{{ $service['execution_mode'] }}</span>
                        <span class="tag">{{ $service['delivery_time'] }}</span>
                        @foreach(array_slice($service['audience'], 0, 2) as $audience)
                            <span class="tag">{{ $audience }}</span>
                        @endforeach
                    </div>
                    <a class="btn" href="{{ route('business.services.show', $service['slug']) }}">Открыть сервис</a>
                </article>
            @endforeach
        </section>
    </main>
    @include('storefront.partials.footer')
</body>
</html>
