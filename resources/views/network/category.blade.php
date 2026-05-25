<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['label_ru'] ?? $category }} - Seller Supply Preview</title>
    <meta name="description" content="{{ $meta['description_ru'] ?? 'Products that sellers can connect to the Meanly marketplace.' }}">
    <meta name="robots" content="noindex,follow">
    <link rel="alternate" type="application/json" href="{{ route('llms.network.categories.show', $category) }}">
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
        
        .hero {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            padding: 28px;
            border-radius: var(--radius);
            margin-bottom: 32px;
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
            font-size: clamp(40px, 7vw, 82px);
            line-height: .88;
            letter-spacing: -.075em;
            font-weight: 950;
        }
        .lead {
            max-width: 860px;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
            font-weight: 700;
        }
        .tags { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
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
        
        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }
        
        .card {
            background: var(--panel);
            border: 3px solid var(--line);
            box-shadow: 5px 5px 0 var(--line);
            border-radius: var(--radius);
            padding: 16px;
            display: grid;
            gap: 10px;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 10px 10px 0 var(--line);
        }
        .card h2 {
            margin: 0;
            font-size: 18px;
            line-height: 1.08;
            font-weight: 950;
        }
        .muted { color: var(--muted); font-weight: 700; font-size: 13px; }
        .price { font-size: 22px; font-weight: 950; }
        
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: 3px solid var(--line);
            background: var(--brand);
            color: #fff;
            box-shadow: 4px 4px 0 var(--line);
            padding: 10px 14px;
            font-weight: 950;
            border-radius: 4px;
            transition: transform 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.15s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
        }
        .btn:hover { transform: translate(-3px, -3px); box-shadow: 7px 7px 0 var(--line); }
        .btn:active { transform: translate(1px, 1px); box-shadow: 2px 2px 0 var(--line); }
        
        .pagination {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 30px;
        }
        .pagination a, .pagination span {
            border: 2px solid var(--line);
            background: var(--panel);
            padding: 8px 11px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-weight: 900;
            box-shadow: 2px 2px 0 var(--line);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .pagination a:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 var(--line);
            background: var(--brand-soft);
        }
        .pagination .active { background: var(--brand); color: #fff; box-shadow: none; transform: none; }
        
        @media (max-width: 920px) { .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 620px) { .grid { grid-template-columns: 1fr; } }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <a class="back-link" href="{{ route('meanly.network.index') }}">← Предварительный список</a>
        
        <section class="hero">
            <div class="eyebrow">{{ $category }} · для продавцов</div>
            <h1>{{ $meta['label_ru'] ?? $category }}</h1>
            <p class="lead">{{ $meta['description_ru'] ?? 'Каталог товаров, которые продавец может подключить в магазин Meanly.' }}</p>
            <div class="tags">
                <span class="tag">{{ $products->total() }} товаров</span>
                <span class="tag">не покупательский checkout</span>
            </div>
        </section>
        
        <section class="grid">
            @foreach($products as $product)
                @php($facts = $network->facts($product))
                <article class="card">
                    <div class="eyebrow">Можно подключить</div>
                    <h2>{{ $product->name }}</h2>
                    <div class="muted">{{ $facts['brand'] }} · {{ $facts['region'] ?? 'global' }}</div>
                    <div class="muted">{{ $facts['canonical_category_label'] }}</div>
                    @if($facts['seller_offers']['count'] > 0)
                        <div class="muted">Уже есть предложения продавцов: {{ $facts['seller_offers']['count'] }}</div>
                        <div class="price">от {{ number_format((float) data_get($facts, 'seller_offers.best_offer.price.amount', 0), 2, '.', ' ') }} ₽</div>
                    @else
                        <div class="muted">Пока нет предложения продавца на витрине</div>
                        <div class="price">{{ number_format((float) $facts['estimated_provider_price']['amount'], 2, '.', ' ') }} {{ $facts['estimated_provider_price']['currency'] }}</div>
                    @endif
                    
                    <div style="display: flex; flex-direction: column; gap: 8px; margin-top: auto;">
                        <a class="btn" href="{{ route('meanly.network.products.show', $network->publicSlug($product)) }}">Открыть</a>
                        @if(! empty($facts['canonical_product_url']))
                            <a class="btn btn-secondary" style="width: 100%; text-align: center;" href="{{ $facts['canonical_product_url'] }}">Страница товара</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>
        
        @if($products->hasPages())
            <nav class="pagination">
                @if($products->onFirstPage())<span>←</span>@else<a href="{{ $products->previousPageUrl() }}">←</a>@endif
                <span class="active">{{ $products->currentPage() }} / {{ $products->lastPage() }}</span>
                @if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}">→</a>@else<span>→</span>@endif
            </nav>
        @endif
    </main>
    @include('storefront.partials.footer')
</body>
</html>
