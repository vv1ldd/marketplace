<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Seller Supply Preview - Meanly</title>
    <meta name="description" content="A seller-oriented preview of products that can be connected to the Meanly marketplace.">
    <meta name="robots" content="noindex,follow">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
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
            position: relative;
            background:
                linear-gradient(90deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, .035) 1px, transparent 1px),
                var(--bg);
            background-size: 28px 28px;
            color: var(--ink);
            font-family: "Outfit", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 500px;
            background: radial-gradient(circle at 50% -120px, rgba(124, 58, 237, 0.16) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
        }
        a { color: inherit; text-decoration: none; }
        
        .shell { width: min(1180px, calc(100vw - 32px)); margin: 0 auto; position: relative; z-index: 1; }
        main.shell { padding-top: 96px; padding-bottom: 80px; }
        
        .hero { padding: 48px 0 24px; }
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
            max-width: 820px;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
            font-weight: 750;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
            padding-bottom: 80px;
        }
        
        .card {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            border-radius: var(--radius);
            padding: 24px;
            display: grid;
            gap: 12px;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .card:hover {
            transform: translate(-6px, -6px);
            box-shadow: 13px 13px 0 var(--line);
        }
        .card h2 {
            margin: 0;
            font-size: 28px;
            line-height: 1;
            letter-spacing: -.04em;
            font-weight: 950;
        }
        .card p {
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
            color: #fff;
            box-shadow: 4px 4px 0 var(--line);
            padding: 11px 16px;
            font-weight: 950;
            border-radius: 4px;
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .btn:hover { transform: translate(-3px, -3px); box-shadow: 7px 7px 0 var(--line); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 2px 2px 0 var(--line); }
        
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <section class="hero">
            <div class="eyebrow">{{ __('network.index.sellers') }}</div>
            <h1>{{ __('network.index.title') }}</h1>
            <p class="lead">{{ __('network.index.lead') }}</p>
        </section>
        
        <section class="grid">
            @foreach($categories as $category)
                <article class="card">
                    <div class="eyebrow">{{ $category['slug'] }}</div>
                    <h2>{{ $category['label_ru'] }}</h2>
                    <p>{{ $category['description_ru'] ?? $category['label_en'] }}</p>
                    <div class="tags">
                        <span class="tag">{{ __('network.index.products_count', ['count' => $category['candidate_count']]) }}</span>
                    </div>
                    <a class="btn" href="{{ route('meanly.network.categories.show', $category['slug']) }}">{{ __('network.index.open') }}</a>
                </article>
            @endforeach
        </section>
    </main>
    @include('storefront.partials.footer')
</body>
</html>
