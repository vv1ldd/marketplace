<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $service['name'] }} - Meanly Service</title>
    <meta name="description" content="{{ $service['description'] }}">
    <link rel="canonical" href="{{ route('business.services.show', $service['slug']) }}">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.services.show', $service['slug']) }}">
    <script type="application/ld+json">{!! json_encode($serviceJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/json" id="meanly-service-facts">{!! json_encode($service, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
        
        .back-link {
            display: inline-flex;
            margin: 30px 0 18px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
            transition: transform 0.15s ease;
        }
        .back-link:hover { transform: translateX(-4px); }
        
        .layout { display: grid; grid-template-columns: minmax(0, 1fr) 390px; gap: 28px; align-items: start; }
        
        .panel {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            border-radius: var(--radius);
            padding: 28px;
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
            font-size: clamp(38px, 6vw, 76px);
            line-height: .9;
            letter-spacing: -.07em;
            font-weight: 950;
        }
        h2 { margin: 0 0 14px; font-size: 26px; letter-spacing: -.04em; font-weight: 950; }
        p { color: var(--muted); font-weight: 700; line-height: 1.55; }
        
        .fact-grid { display: grid; gap: 14px; margin-top: 24px; }
        .fact {
            border: 2px solid var(--line);
            background: var(--brand-soft);
            padding: 14px;
            border-radius: 6px;
        }
        .fact span {
            display: block;
            margin-bottom: 6px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--brand);
        }
        .fact strong { font-size: 16px; font-weight: 850; }
        
        .aside-list { display: grid; gap: 12px; }
        
        .mini { border-bottom: 2px solid var(--line); padding-bottom: 12px; }
        .mini:last-child { border-bottom: 0; padding-bottom: 0; }
        .mini span {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .mini strong { display: block; font-size: 14px; font-weight: 850; }
        
        .audiences { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
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
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: 3px solid var(--line);
            background: var(--brand);
            color: white;
            box-shadow: 4px 4px 0 var(--line);
            padding: 12px 16px;
            font-weight: 950;
            border-radius: 4px;
            margin-top: 20px;
            width: 100%;
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .btn:hover { transform: translate(-3px, -3px); box-shadow: 7px 7px 0 var(--line); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 2px 2px 0 var(--line); }
        
        code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; }
        
        @media (max-width: 840px) { .layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('business.partials.header')

    <main class="shell">
        <a class="back-link" href="{{ route('business.services.index') }}">← Все сервисы</a>
        
        <section class="layout">
            <article class="panel">
                <div class="eyebrow">{{ $service['service_type'] }}</div>
                <h1>{{ $service['name'] }}</h1>
                <p>{{ $service['description'] }}</p>

                <div class="fact-grid">
                    <div class="fact">
                        <span>Deliverable</span>
                        <strong>{{ $service['deliverable'] }}</strong>
                    </div>
                    <div class="fact">
                        <span>Outcome</span>
                        <strong>{{ $service['outcome'] }}</strong>
                    </div>
                    <div class="fact">
                        <span>Machine-readable endpoint</span>
                        <strong><code>{{ $service['machine_readable_at'] }}</code></strong>
                    </div>
                </div>
            </article>

            <aside class="panel">
                <h2>Service Facts</h2>
                <div class="aside-list">
                    <div class="mini">
                        <span>SLA</span>
                        <strong>{{ $service['sla'] }}</strong>
                    </div>
                    <div class="mini">
                        <span>Delivery time</span>
                        <strong>{{ $service['delivery_time'] }}</strong>
                    </div>
                    <div class="mini">
                        <span>Execution mode</span>
                        <strong>{{ $service['execution_mode'] }}</strong>
                    </div>
                    <div class="mini">
                        <span>Pricing</span>
                        <strong>{{ $service['pricing']['summary'] ?? 'Custom commercial offer' }}</strong>
                    </div>
                    <div class="mini">
                        <span>Audience</span>
                        <div class="audiences">
                            @foreach($service['audience'] as $audience)
                                <span class="tag">{{ $audience }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <a class="btn" href="{{ route('business.register') }}">Обсудить подключение</a>
            </aside>
        </section>
    </main>
    @include('storefront.partials.footer')
</body>
</html>
