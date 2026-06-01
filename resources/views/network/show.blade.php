@php
    $selectedOffer = $intentResolution['selected_offer'] ?? null;
    $alternatives = collect($intentResolution['alternatives'] ?? []);
    $intentLabel = $intentResolution['intent_label'] ?? 'Best offer';
    $intentKey = $intentResolution['intent'] ?? 'best_offer';
    $intentOptions = [
        'best_offer' => 'Best offer',
        'lowest_price' => 'Lowest price',
        'in_stock' => 'In stock',
        'trusted_seller' => 'Trusted seller',
    ];
@endphp
<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $facts['name'] }} - Seller Supply Preview</title>
    <meta name="description" content="{{ $facts['description'] }}">
    <meta name="robots" content="{{ data_get($facts, 'indexing_policy.robots', 'noindex,follow') }}">
    <link rel="alternate" type="application/json" href="{{ $facts['machine_readable_at'] }}">
    <link rel="alternate" type="application/json" href="{{ $intentResolution['machine_readable_at'] }}">
    @if(! empty($facts['canonical_product_machine_readable_at']))
        <link rel="alternate" type="application/json" href="{{ $facts['canonical_product_machine_readable_at'] }}">
    @endif
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
            font-size: clamp(38px, 6vw, 72px);
            line-height: .9;
            letter-spacing: -.07em;
            font-weight: 950;
        }
        h2 { margin: 0 0 14px; font-size: 28px; line-height: 1; font-weight: 950; }
        p { color: var(--muted); font-weight: 700; line-height: 1.55; }
        
        .facts, .offers { display: grid; gap: 12px; margin-top: 22px; }
        .fact, .offer, .selected-offer {
            border: 2px solid var(--line);
            background: var(--brand-soft);
            padding: 12px;
            border-radius: 6px;
        }
        .offer { background: var(--panel); box-shadow: 3px 3px 0 var(--line); }
        .selected-offer {
            margin-top: 22px;
            background: #fff7d6;
            box-shadow: 5px 5px 0 var(--line);
            padding: 18px;
        }
        .selected-offer strong { font-size: 24px; line-height: 1.1; }
        
        .fact span, .mini span, .offer span, .selected-offer span {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
        }
        .fact strong, .mini strong, .offer strong, .selected-offer strong { display: block; margin-top: 5px; }
        
        .badges, .intent-links { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .badge, .intent-links a {
            border: 2px solid var(--line);
            background: var(--panel);
            padding: 6px 8px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
        }
        .intent-links a.active { background: var(--brand); color: #fff; }
        
        .mini { border-bottom: 2px solid var(--line); padding: 12px 0; }
        .mini:last-child { border-bottom: 0; }
        
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border: 3px solid var(--line);
            background: var(--brand);
            color: #fff;
            box-shadow: 4px 4px 0 var(--line);
            padding: 12px 16px;
            font-weight: 950;
            margin-top: 20px;
            border-radius: 4px;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }
        .btn:hover { transform: translate(2px, 2px); box-shadow: 2px 2px 0 var(--line); }
        .btn-secondary { background: var(--panel); color: var(--ink); }
        
        code { font-family: "JetBrains Mono", ui-monospace, monospace; font-size: 12px; }
        
        @media (max-width: 840px) { .layout { grid-template-columns: 1fr; } }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <a class="back-link" href="{{ route('meanly.network.categories.show', $facts['canonical_category']) }}">← {{ $facts['canonical_category_label'] }}</a>
        
        <section class="layout">
            <article class="panel">
                <div class="eyebrow">Для продавцов · {{ $facts['canonical_category_label'] }}</div>
                <h1>{{ $facts['name'] }}</h1>
                
                @if($selectedOffer)
                    <p>Этот товар уже связан с предложением продавца на витрине.</p>
                    <article class="selected-offer">
                        <span>{{ data_get($selectedOffer, 'availability') }}</span>
                        <strong>{{ data_get($selectedOffer, 'seller.name') ?? 'Meanly seller' }} · {{ data_get($selectedOffer, 'price.label', number_format((float) data_get($selectedOffer, 'price.amount'), 2, '.', ' ')) }}</strong>
                        <p style="margin-top: 8px;">Покупательская страница товара ведет к доступному предложению.</p>
                        <a class="btn" style="width: 100%; text-align: center;" href="{{ $selectedOffer['url'] }}">Открыть предложение</a>
                    </article>
                @else
                    <p>{{ $facts['description'] }}</p>
                @endif
                
                <div class="facts">
                    @if(! empty($facts['canonical_product_url']))
                        <div class="fact">
                            <span>Страница товара</span>
                            <strong><a href="{{ $facts['canonical_product_url'] }}" style="color: var(--brand); text-decoration: underline;">Открыть покупательскую карточку</a></strong>
                        </div>
                    @endif
                    @if($selectedOffer)
                        <div class="fact">
                            <span>Статус</span>
                            <strong>Есть {{ $facts['seller_offers']['count'] }} предложение(я) продавцов.</strong>
                        </div>
                    @else
                        <div class="fact">
                            <span>Статус</span>
                            <strong>Можно подключить к магазину, но прямой checkout еще не открыт.</strong>
                        </div>
                    @endif
                </div>
                
                @if($selectedOffer && $alternatives->isNotEmpty())
                    <div class="offers">
                        <h2>Другие предложения</h2>
                        @foreach($alternatives as $offer)
                            <article class="offer">
                                <span>{{ $offer['availability'] }}</span>
                                <strong style="font-size: 16px; font-weight: 850;">{{ $offer['seller']['name'] ?? 'Meanly seller' }} · {{ number_format((float) $offer['price']['amount'], 2, '.', ' ') }} ₽</strong>
                                <a class="btn btn-secondary" style="margin-top: 10px;" href="{{ $offer['url'] }}">Открыть предложение</a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </article>
            
            <aside class="panel">
                <div class="mini"><span>Бренд</span><strong>{{ $facts['brand'] ?? 'Digital' }}</strong></div>
                <div class="mini"><span>Категория</span><strong>{{ $facts['canonical_category_label'] }}</strong></div>
                <div class="mini"><span>Регион</span><strong>{{ $facts['region'] ?? 'global' }}</strong></div>
                <div class="mini"><span>Номинал</span><strong>{{ $facts['face_value'] ?? 'variable' }} {{ $facts['face_value_currency'] }}</strong></div>
                <div class="mini"><span>Ориентир закупки</span><strong>{{ number_format((float) $facts['estimated_provider_price']['amount'], 2, '.', ' ') }} {{ $facts['estimated_provider_price']['currency'] }}</strong></div>
                <div class="mini"><span>Выдача</span><strong>{{ $facts['fulfillment']['delivery'] }}</strong></div>
                
                @if($selectedOffer)
                    <div class="mini"><span>Продавец</span><strong>{{ data_get($selectedOffer, 'seller.name') ?? 'Meanly seller' }}</strong></div>
                    <a class="btn" style="width: 100%; text-align: center;" href="{{ $selectedOffer['url'] }}">Открыть предложение</a>
                @endif
                <a class="btn btn-secondary" style="width: 100%; text-align: center;" href="{{ route('business.register') }}">Подключить как продавец</a>
            </aside>
        </section>
    </main>
    @include('storefront.partials.footer')
</body>
</html>
