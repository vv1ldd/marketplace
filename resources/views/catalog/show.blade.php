<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $meta['label_ru'] ?? $category }} - Meanly Catalog</title>
    <meta name="description" content="{{ $meta['description_ru'] ?? ($meta['label_en'] ?? $category) }}">
    <link rel="canonical" href="{{ route('meanly.catalog.categories.show', $category) }}">
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.categories.show', $category) }}">
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
            margin: 0;
            max-width: 860px;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
            font-weight: 700;
        }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
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
        .filter-panel {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: 6px 6px 0 var(--line);
            border-radius: var(--radius);
            padding: 16px;
            margin: -12px 0 28px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            align-items: end;
            gap: 12px;
        }
        .filter-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-field span {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            color: var(--muted);
        }
        .filter-field select {
            width: 100%;
            min-height: 44px;
            border: 3px solid var(--line);
            background: #fff;
            color: var(--ink);
            box-shadow: 3px 3px 0 var(--line);
            border-radius: 4px;
            padding: 8px 10px;
            font: inherit;
            font-weight: 900;
        }
        .filter-submit, .filter-clear {
            min-height: 44px;
            white-space: nowrap;
        }
        .filter-clear {
            background: #fff;
            color: var(--ink);
        }

        .group-product-panel {
            background: transparent;
            border: 0;
            box-shadow: none;
            padding: 0;
            margin: 0 0 28px;
        }
        .group-product-head {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 390px;
            gap: 28px;
            align-items: start;
        }
        .group-product-copy {
            border: 4px solid var(--line);
            background: #fff;
            box-shadow: var(--shadow);
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 0;
        }
        .group-product-copy h2 {
            margin: 0;
            font-size: clamp(42px, 6vw, 82px);
            line-height: .9;
            letter-spacing: -.08em;
            font-weight: 950;
        }
        .group-product-copy p {
            margin: 0;
            color: var(--muted);
            font-weight: 750;
            line-height: 1.55;
        }
        .group-product-copy > .meta {
            margin: 20px 24px 16px;
        }
        .group-trust-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            padding: 0 24px 24px;
        }
        .group-trust-item {
            border: 2px solid var(--line);
            border-radius: 6px;
            background: #f8fafc;
            padding: 10px;
            font-size: 13px;
            font-weight: 750;
            color: var(--muted);
        }
        .group-trust-item strong {
            display: block;
            color: var(--ink);
            font-weight: 950;
            margin-bottom: 4px;
        }
        .group-product-visual {
            min-height: 300px;
            border: 0;
            border-bottom: 4px solid var(--line);
            background:
                radial-gradient(circle at 50% 24%, rgba(124, 58, 237, .35), transparent 34%),
                linear-gradient(135deg, #111827 0%, #181528 48%, #08111f 100%);
            box-shadow: none;
            border-radius: 0;
            padding: 28px;
            display: grid;
            grid-template-columns: minmax(110px, 160px) minmax(0, 1fr);
            gap: 24px;
            align-items: center;
        }
        .group-product-art {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            aspect-ratio: 1 / 1;
            border: 3px solid var(--line);
            background: #bda51c;
            color: #fff;
            box-shadow: 5px 5px 0 var(--line);
            padding: 20px;
            font-weight: 950;
            line-height: 1.05;
            font-size: clamp(54px, 8vw, 88px);
            text-align: center;
        }
        .group-product-summary {
            min-width: 0;
            color: #fff;
        }
        .group-product-summary .eyebrow {
            margin-bottom: 18px;
            background: rgba(255, 255, 255, .04);
            color: #fff;
        }
        .group-product-summary h2 {
            color: #fff;
            margin: 0;
        }
        .group-product-summary p {
            color: rgba(255, 255, 255, .78);
            max-width: 560px;
            margin-top: 16px;
        }
        @media (max-width: 768px) {
            .group-product-visual {
                grid-template-columns: 1fr;
                min-height: auto;
            }
            .group-product-art {
                width: 150px;
            }
        }
        .group-selector-grid {
            display: grid;
            gap: 12px;
            position: sticky;
            top: 104px;
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
            padding: 22px;
        }
        .selector-card {
            border: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }
        .selector-card strong {
            display: block;
            margin-bottom: 8px;
            font-size: 12px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            text-transform: uppercase;
        }
        .group-choice-form {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .group-choice-form .filter-field {
            min-width: 0;
        }
        .group-choice-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .group-choice-actions .btn {
            flex: 1;
        }
        .group-choice-actions .filter-clear {
            min-height: 45px;
        }
        .group-choice-submit {
            display: none;
        }
        .group-checkout-card {
            border: 0;
            background: transparent;
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }
        .group-checkout-card .seller {
            color: var(--muted);
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .group-checkout-card .price {
            margin: 10px 0;
            font-size: clamp(32px, 5vw, 48px);
            line-height: .92;
        }
        .group-checkout-note {
            border: 3px solid var(--line);
            background: #fdf5ff;
            box-shadow: none;
            padding: 12px;
            font-weight: 850;
            line-height: 1.45;
            color: var(--muted);
        }
        .group-checkout-card form {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }
        .group-checkout-card input {
            width: 100%;
            min-height: 42px;
            border: 2px solid var(--line);
            box-shadow: 2px 2px 0 var(--line);
            padding: 8px 10px;
            font: inherit;
            font-weight: 800;
        }
        .group-wallet-note {
            border: 2px solid var(--line);
            background: #ecfdf5;
            box-shadow: 2px 2px 0 var(--line);
            padding: 10px;
            font-weight: 800;
            line-height: 1.45;
        }
        .wallet-status {
            min-height: 18px;
            margin: 0;
            color: #065f46;
            font-weight: 850;
            font-size: 13px;
        }
        .btn[disabled] {
            opacity: .55;
            cursor: not-allowed;
            transform: none;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
        }
        
        .card {
            background: var(--panel);
            border: 3px solid var(--line);
            box-shadow: 5px 5px 0 var(--line);
            border-radius: var(--radius);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 100%;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .card:hover {
            transform: translate(-5px, -5px);
            box-shadow: 10px 10px 0 var(--line);
        }
        .card img {
            width: 100%;
            aspect-ratio: 1.1 / 1;
            object-fit: contain;
            background: #181528;
            padding: 16px;
            border-bottom: 3px solid var(--line);
            display: block;
        }
        .card-body {
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }
        .product-title {
            font-weight: 950;
            font-size: 18px;
            line-height: 1.1;
            transition: color 0.15s ease;
        }
        .product-title:hover { color: var(--brand); }
        .muted { color: var(--muted); font-weight: 700; font-size: 13px; }
        .price {
            margin-top: auto;
            font-size: 24px;
            font-weight: 950;
            letter-spacing: -.04em;
            margin-bottom: 6px;
        }
        
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
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
        
        .empty {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            padding: 28px;
            font-weight: 900;
            border-radius: var(--radius);
        }
        
        .catalog-pagination {
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 38px;
            max-width: 100%;
        }
        .catalog-pagination__pages {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            min-width: 0;
        }
        .catalog-pagination__item, .catalog-pagination__ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            border: 2px solid var(--line);
            background: var(--panel);
            padding: 8px 12px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-weight: 900;
            box-shadow: 3px 3px 0 var(--line);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .catalog-pagination__item:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 var(--line);
            background: var(--brand-soft);
        }
        .catalog-pagination__item--active {
            background: var(--brand);
            color: #fff;
            box-shadow: none;
            transform: none;
        }
        .catalog-pagination__item--disabled {
            opacity: .48;
            cursor: not-allowed;
            transform: none;
        }
        .catalog-pagination__item--disabled:hover {
            background: var(--panel);
            box-shadow: 3px 3px 0 var(--line);
            transform: none;
        }
        .catalog-pagination__item--control { min-width: 108px; }
        .catalog-pagination__ellipsis {
            border-color: transparent;
            background: transparent;
            box-shadow: none;
            padding-inline: 2px;
        }
        
        @media (max-width: 920px) {
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .group-product-head { grid-template-columns: 1fr; }
            .group-selector-grid { position: static; }
        }
        @media (max-width: 620px) {
            .grid { grid-template-columns: 1fr; }
            .filter-form { grid-template-columns: 1fr; }
            .group-choice-form { grid-template-columns: 1fr; }
            .group-choice-actions { flex-direction: column; align-items: stretch; }
            .group-product-panel { margin-bottom: 22px; }
            .group-product-head { gap: 0; }
            .group-product-copy {
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                box-shadow: 6px 0 0 var(--line);
            }
            .group-product-visual {
                grid-template-columns: 82px minmax(0, 1fr);
                gap: 14px;
                min-height: auto;
                padding: 14px;
            }
            .group-product-art {
                width: 82px;
                padding: 12px;
                box-shadow: 3px 3px 0 var(--line);
                font-size: 48px;
            }
            .group-product-summary .eyebrow {
                margin-bottom: 8px;
                padding: 5px 7px;
                font-size: 8px;
                box-shadow: 2px 2px 0 var(--line);
            }
            .group-product-summary h2 {
                font-size: clamp(24px, 8vw, 36px);
                line-height: .92;
                letter-spacing: -.06em;
            }
            .group-product-summary p { display: none; }
            .group-product-copy > .meta {
                margin: 10px 14px 12px;
                gap: 7px;
            }
            .group-product-copy > .meta .tag {
                padding: 4px 6px;
                font-size: 10px;
            }
            .group-trust-list { display: none; }
            .group-selector-grid {
                border-top: 0;
                border-top-left-radius: 0;
                border-top-right-radius: 0;
                box-shadow: 6px 6px 0 var(--line);
                padding: 12px 14px 14px;
                gap: 10px;
            }
            .selector-card strong,
            .filter-field span,
            .group-checkout-card .seller {
                font-size: 10px;
            }
            .filter-field select {
                min-height: 40px;
                border-width: 2px;
                box-shadow: 2px 2px 0 var(--line);
                padding: 6px 8px;
            }
            .group-checkout-card .price {
                margin: 4px 0 10px;
                font-size: clamp(32px, 10vw, 44px);
            }
            .group-checkout-note,
            .group-wallet-note {
                padding: 8px;
                font-size: 12px;
                line-height: 1.35;
            }
            .group-checkout-card form {
                gap: 8px;
                margin-top: 10px;
            }
            .group-checkout-card .btn {
                min-height: 44px;
                padding: 10px 12px;
            }
            .filter-submit, .filter-clear { width: 100%; }
            .catalog-pagination {
                align-items: stretch;
                gap: 10px;
            }
            .catalog-pagination__pages {
                order: 3;
                width: 100%;
            }
            .catalog-pagination__item--control {
                flex: 1 1 calc(50% - 10px);
                min-width: 0;
            }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        @php
            $group = $group ?? null;
        @endphp
        <a class="back-link" href="{{ $group ? route('meanly.catalog.categories.show', $category) : route('meanly.catalog.index') }}">
            ← {{ $group ? ($group['category_label'] ?? 'Категория') : 'Все категории' }}
        </a>
        
        @unless($group)
            <section class="hero">
                <div class="eyebrow">Категория</div>
                <h1>{{ $meta['label_ru'] ?? $category }}</h1>
                <p class="lead">{{ $meta['description_ru'] ?? 'Цифровые товары Meanly с понятными карточками, предложениями продавцов и электронной выдачей.' }}</p>
                <div class="meta">
                    <span class="tag">{{ $products->total() }} товаров</span>
                </div>
            </section>
        @endunless

        @php
            $facetData = $facets ?? [];
            $brandFacets = collect($facetData['brands'] ?? []);
            $regionFacets = collect($facetData['regions'] ?? []);
            $nominalFacets = collect($facetData['nominals'] ?? []);
            $selectedFacets = (array) ($facetData['selected'] ?? []);
            $sortOptions = (array) ($facetData['sort_options'] ?? ['relevance' => 'Сначала лучшие']);
            $selectedBrand = (string) ($selectedFacets['brand'] ?? '');
            $selectedRegion = (string) ($selectedFacets['region'] ?? '');
            $selectedNominalKey = (string) ($selectedFacets['nominal_key'] ?? '');
            $selectedSort = (string) ($selectedFacets['sort'] ?? 'relevance');
            $hasActiveFilters = (bool) ($selectedFacets['has_filters'] ?? false);
            $clearQuery = request()->except(['brand', 'family', 'region', 'nominal', 'face_value', 'currency', 'page']);

            if (($clearQuery['sort'] ?? null) === 'relevance') {
                unset($clearQuery['sort']);
            }

            $baseUrl = $group
                ? route('meanly.catalog.groups.show', [
                    'category' => $category,
                    'brandSlug' => $group['brand_slug'],
                    'kindSlug' => $group['kind'],
                ])
                : route('meanly.catalog.categories.show', $category);
            $clearUrl = $baseUrl;

            if ($clearQuery !== []) {
                $clearUrl .= '?'.http_build_query($clearQuery);
            }

            $formAction = $baseUrl;

            $selectedGroupProduct = $group ? (array) ($group['selected_product'] ?? []) : [];
            $selectedGroupOffer = $group ? data_get($group, 'selected_offer') : null;
            $selectedGroupPrice = data_get($selectedGroupOffer, 'price.amount');
            $selectedGroupCurrency = strtoupper((string) data_get($selectedGroupOffer, 'price.currency', 'RUB'));
            $selectedGroupPriceLabel = is_numeric($selectedGroupPrice)
                ? number_format((float) $selectedGroupPrice, 2, '.', ' ').($selectedGroupCurrency === 'RUB' ? ' ₽' : ' '.$selectedGroupCurrency)
                : 'Ожидает цену';
            $selectedGroupPriceRange = $group ? (array) ($group['price_range'] ?? []) : [];
            $groupRangePriceLabel = (string) ($selectedGroupPriceRange['label'] ?? '');
            $groupVariants = $group ? collect($group['variants'] ?? [])->values() : collect();
            $selectedGroupRegion = data_get($selectedGroupProduct, 'region') ?: $selectedRegion;
            $selectedGroupRegionLabel = $selectedGroupRegion && strtolower((string) $selectedGroupRegion) !== 'global'
                ? strtoupper((string) $selectedGroupRegion)
                : 'Глобальный регион';
            $selectedGroupNominal = data_get($selectedGroupProduct, 'face_value');
            $selectedGroupNominalCurrency = strtoupper((string) data_get($selectedGroupProduct, 'face_value_currency'));
            $selectedGroupNominalLabel = is_numeric($selectedGroupNominal)
                ? rtrim(rtrim(number_format((float) $selectedGroupNominal, 2, '.', ' '), '0'), '.').' '.$selectedGroupNominalCurrency
                : '';
            $checkoutUser = auth()->user();
            $checkoutUserEmail = trim((string) ($checkoutUser?->email ?? ''));
            $buyerRubtBalanceMinor = $checkoutUser
                ? app(\App\Services\BuyerWalletService::class)->balance($checkoutUser)['available_minor']
                : 0;
            $buyerHasPasskey = $checkoutUser ? $checkoutUser->passkeys()->exists() : false;
        @endphp

        @if($group)
            <section class="group-product-panel" aria-label="Выбор варианта товара">
                <div class="group-product-head">
                    <div class="group-product-copy">
                        <div class="group-product-visual">
                            <div class="group-product-art" aria-hidden="true">{{ mb_strtoupper(mb_substr((string) $group['title'], 0, 1)) }}</div>
                            <div class="group-product-summary">
                                <div class="eyebrow">{{ $group['kind_label'] }} · {{ $group['category_label'] }}</div>
                                <h2>{{ $group['title'] }}</h2>
                                <p>
                                    Выберите регион и номинал. Цена и оплата обновятся под конкретный вариант.
                                </p>
                            </div>
                        </div>
                        <div class="meta">
                            <span class="tag">{{ (int) $group['variant_count'] }} вариантов</span>
                            <span class="tag">{{ (int) $group['region_count'] }} регионов</span>
                            <span class="tag">{{ (int) $group['nominal_count'] }} номиналов</span>
                        </div>
                        <div class="group-trust-list" aria-label="Что важно знать перед покупкой">
                            <div class="group-trust-item">
                                <strong>Доставка</strong>
                                На email и в личный сейф после оплаты.
                            </div>
                            <div class="group-trust-item">
                                <strong>Регион</strong>
                                Выберите страну активации.
                            </div>
                            <div class="group-trust-item">
                                <strong>Оплата</strong>
                                Баланс и подтверждение Passkey.
                            </div>
                        </div>
                    </div>

                    <div class="group-selector-grid">
                        <div class="selector-card">
                            <strong>Выберите вариант</strong>
                            <form class="group-choice-form" method="GET" action="{{ $baseUrl }}" data-group-choice-form>
                                @foreach(request()->except(['page', 'region', 'nominal', 'face_value', 'currency']) as $queryName => $queryValue)
                                    @if(is_scalar($queryValue))
                                        <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
                                    @endif
                                @endforeach

                                <label class="filter-field">
                                    <span>Страна / регион</span>
                                    <select name="region" data-region-select data-auto-submit>
                                        <option value="">Выберите страну</option>
                                        @foreach($regionFacets as $regionFacet)
                                            <option value="{{ $regionFacet['value'] }}" @selected($selectedRegion === $regionFacet['name'])>
                                                {{ $regionFacet['label'] ?? strtoupper($regionFacet['name']) }}@if($regionFacet['count'] !== null) ({{ $regionFacet['count'] }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                                <label class="filter-field">
                                    <span>Номинал@if($selectedRegion !== '') · {{ $selectedGroupRegionLabel }}@endif</span>
                                    <select name="nominal" data-nominal-select data-auto-submit>
                                        <option value="">{{ $selectedRegion !== '' ? 'Выберите номинал для региона' : 'Сначала выберите страну' }}</option>
                                        @foreach($nominalFacets as $nominalFacet)
                                            <option value="{{ $nominalFacet['value'] }}" @selected($selectedNominalKey === $nominalFacet['key'])>
                                                {{ $nominalFacet['label'] }}@if($nominalFacet['count'] !== null) ({{ $nominalFacet['count'] }})@endif
                                            </option>
                                        @endforeach
                                    </select>
                                </label>

                            </form>
                        </div>

                        <div class="group-checkout-card">
                            <div class="seller" data-checkout-kicker>{{ ($group['selection_ready'] ?? false) && $selectedGroupOffer ? 'К оплате' : ($groupRangePriceLabel !== '' ? 'Цена' : 'Выберите параметры') }}</div>
                            <div class="price" data-checkout-price style="{{ $groupRangePriceLabel === '' && ! $selectedGroupOffer ? 'color: var(--muted); font-size: 30px;' : '' }}">
                                @if(($group['selection_ready'] ?? false) && $selectedGroupOffer)
                                    {{ $selectedGroupPriceLabel }}
                                @else
                                    {{ $groupRangePriceLabel !== '' ? $groupRangePriceLabel : 'Цена появится здесь' }}
                                @endif
                            </div>
                            <div class="group-checkout-note" data-checkout-note style="background: {{ ($group['selection_ready'] ?? false) && $selectedGroupOffer ? '#fdf5ff' : '#fff7d6' }};">
                                @if(($group['selection_ready'] ?? false) && $selectedGroupOffer)
                                    Код придет в личный сейф. Регион: <strong>{{ $selectedGroupRegionLabel }}</strong>@if($selectedGroupNominalLabel !== '') · Номинал: <strong>{{ $selectedGroupNominalLabel }}</strong>@endif.
                                @elseif($selectedRegion !== '')
                                    Выберите номинал: цена и оплата обновятся под выбранный регион.
                                @else
                                    Выберите страну и номинал: цена изменится под конкретную комбинацию.
                                @endif
                            </div>
                            <div class="muted" data-checkout-details style="margin-top: 10px; display: {{ ($group['selection_ready'] ?? false) ? 'block' : 'none' }};">
                                SKU: <span data-checkout-sku>{{ data_get($selectedGroupOffer, 'sku', data_get($selectedGroupProduct, 'slug')) }}</span><br>
                                Продавец: <span data-checkout-seller>{{ data_get($selectedGroupOffer, 'seller.name', 'Meanly seller') }}</span>
                            </div>
                            <form method="POST" action="{{ route('meanly.storefront.checkout') }}" data-gift-checkout style="display: {{ ($group['selection_ready'] ?? false) && $selectedGroupOffer ? 'grid' : 'none' }};">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ data_get($selectedGroupOffer, 'product_id') }}" data-checkout-product-id>
                                <input type="hidden" name="quantity" value="1">
                                <input type="hidden" name="is_gift" value="0">
                                @auth
                                    <div class="group-wallet-note">
                                        Баланс: <strong>{{ number_format($buyerRubtBalanceMinor / 100, 2, '.', ' ') }} ₽</strong>.
                                        @if($buyerHasPasskey)
                                            Подтвердите покупку через Passkey.
                                        @else
                                            Подключите Passkey в кабинете, чтобы оплатить балансом.
                                        @endif
                                    </div>
                                    <button class="btn" type="button" data-wallet-pay @disabled(! $buyerHasPasskey)>
                                        Оплатить балансом с Passkey
                                    </button>
                                    <p class="wallet-status" data-wallet-status aria-live="polite"></p>
                                @else
                                    <a class="btn" href="{{ route('login') }}">Войти и оплатить с Passkey</a>
                                @endauth
                            </form>
                            <button class="btn" type="button" disabled data-unavailable-button style="width: 100%; margin-top: 12px; display: {{ ($group['selection_ready'] ?? false) && ! $selectedGroupOffer ? 'inline-flex' : 'none' }};">Нет в продаже</button>
                            @if(isset($errors) && $errors->any())
                                <div class="muted" style="margin-top: 8px; color: #b91c1c;">{{ $errors->first() }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @unless($group)
        <section class="filter-panel" aria-label="Фильтры каталога">
            <form class="filter-form" method="GET" action="{{ $formAction }}">
                @foreach(request()->except(['page', 'brand', 'family', 'region', 'nominal', 'face_value', 'currency', 'sort']) as $queryName => $queryValue)
                    @if(is_scalar($queryValue))
                        <input type="hidden" name="{{ $queryName }}" value="{{ $queryValue }}">
                    @endif
                @endforeach

                @unless($group)
                    <label class="filter-field">
                        <span>Бренд</span>
                        <select name="brand">
                            <option value="">Все бренды</option>
                            @foreach($brandFacets as $brandFacet)
                                <option value="{{ $brandFacet['value'] }}" @selected($selectedBrand === $brandFacet['name'])>
                                    {{ $brandFacet['name'] }}@if($brandFacet['count'] !== null) ({{ $brandFacet['count'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endunless

                @if($group)
                    <label class="filter-field">
                        <span>Регион</span>
                        <select name="region">
                            <option value="">Все регионы</option>
                            @foreach($regionFacets as $regionFacet)
                                <option value="{{ $regionFacet['value'] }}" @selected($selectedRegion === $regionFacet['name'])>
                                    {{ $regionFacet['label'] ?? strtoupper($regionFacet['name']) }}@if($regionFacet['count'] !== null) ({{ $regionFacet['count'] }})@endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <label class="filter-field">
                    <span>Номинал</span>
                    <select name="nominal">
                        <option value="">Любой номинал</option>
                        @foreach($nominalFacets as $nominalFacet)
                            <option value="{{ $nominalFacet['value'] }}" @selected($selectedNominalKey === $nominalFacet['key'])>
                                {{ $nominalFacet['label'] }}@if($nominalFacet['count'] !== null) ({{ $nominalFacet['count'] }})@endif
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="filter-field">
                    <span>Сортировка</span>
                    <select name="sort">
                        @foreach($sortOptions as $sortValue => $sortLabel)
                            <option value="{{ $sortValue }}" @selected($selectedSort === $sortValue)>{{ $sortLabel }}</option>
                        @endforeach
                    </select>
                </label>

                <button class="btn filter-submit" type="submit">Показать</button>
                @if($hasActiveFilters)
                    <a class="btn filter-clear" href="{{ $clearUrl }}">Сбросить</a>
                @endif
            </form>
        </section>
        @endunless

        @if($group)
        @elseif($products->isEmpty())
            <div class="empty">
                @if($hasActiveFilters)
                    По выбранным фильтрам ничего не найдено. Попробуйте другой бренд или номинал.
                @else
                    В этой категории пока нет публичных товаров.
                @endif
            </div>
        @else
            <section class="grid" aria-label="{{ $meta['label_ru'] ?? $category }}">
                @foreach($products as $product)
                    @php
                        $selectedOffer = data_get($product, 'selected_offer');
                        $faceValue = data_get($product, 'face_value');
                        $faceCurrency = strtoupper(trim((string) data_get($product, 'face_value_currency', '')));
                        $region = trim((string) data_get($product, 'region', 'global'));
                        $regionLabel = $region !== '' && strtolower($region) !== 'global' ? strtoupper($region) : 'Все регионы';
                        $variantGroup = (array) data_get($product, 'variant_group', []);
                        $isGrouped = (bool) ($variantGroup['is_grouped'] ?? false);
                        $variantSummary = collect([
                            $isGrouped ? (($variantGroup['variant_count'] ?? 0).' вариантов') : null,
                            $isGrouped && ($variantGroup['region_count'] ?? 0) > 0 ? (($variantGroup['region_count'] ?? 0).' регионов') : null,
                            $isGrouped && ($variantGroup['nominal_count'] ?? 0) > 0 ? (($variantGroup['nominal_count'] ?? 0).' номиналов') : null,
                        ])->filter()->implode(' · ');
                    @endphp
                    <article class="card">
                        <div class="card-body">
                            <a class="product-title" href="{{ $product['url'] }}">{{ $product['name'] }}</a>
                            <div class="muted">{{ data_get($product, 'brand') ?: ($meta['label_ru'] ?? 'Digital goods') }}</div>
                            <div class="muted">{{ data_get($product, 'category_label', $meta['label_ru'] ?? 'Digital goods') }} · {{ $isGrouped ? 'несколько регионов' : $regionLabel }}</div>
                            @if($isGrouped)
                                <div style="margin: 6px 0 8px;">
                                    <span class="tag" style="background: #efe6ff; box-shadow: 2px 2px 0 var(--line); font-size: 11px; padding: 4px 8px; border: 2px solid var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">{{ $variantSummary }}</span>
                                </div>
                                @if(!empty($variantGroup['regions']))
                                    <div class="muted">Регионы: {{ collect($variantGroup['regions'])->take(4)->implode(', ') }}@if(($variantGroup['region_count'] ?? 0) > 4) и другие@endif</div>
                                @endif
                            @endif
                            @if(is_numeric($faceValue) && (float) $faceValue > 0)
                                <div style="margin: 6px 0 8px;">
                                    <span class="tag" style="background: #e7fff2; box-shadow: 2px 2px 0 var(--line); font-size: 11px; padding: 4px 8px; border: 2px solid var(--line); display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 900; text-transform: uppercase;">Номинал: {{ rtrim(rtrim(number_format((float) $faceValue, 2, '.', ' '), '0'), '.') }} {{ $faceCurrency }}</span>
                                </div>
                            @endif
                            @if($selectedOffer)
                                <div class="price">{{ number_format((float) data_get($selectedOffer, 'price.amount'), 2, '.', ' ') }} ₽</div>
                                <div class="muted">Продавец: {{ data_get($selectedOffer, 'seller.name', 'Meanly seller') }}</div>
                            @else
                                <div class="price" style="font-size: 20px;">Скоро в продаже</div>
                                <div class="muted">Доступно через сеть поставщиков: {{ (int) data_get($product, 'provider_count', 0) }}</div>
                            @endif
                            <a class="btn" href="{{ $product['url'] }}">{{ data_get($product, 'cta_label', 'Открыть') }}</a>
                        </div>
                    </article>
                @endforeach
            </section>

            @if($products->hasPages())
                @include('components.catalog-pagination', ['paginator' => $products])
            @endif
        @endif
    </main>
    @include('storefront.partials.footer')
    @if($group)
        <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
        <script>
            const groupVariantState = @json([
                'baseUrl' => $baseUrl,
                'variants' => $groupVariants,
            ]);

            document.querySelectorAll('[data-group-choice-form]').forEach((form) => {
                const regionSelect = form.querySelector('[data-region-select]');
                const nominalSelect = form.querySelector('[data-nominal-select]');
                const checkoutCard = document.querySelector('.group-checkout-card');
                const kicker = checkoutCard?.querySelector('[data-checkout-kicker]');
                const price = checkoutCard?.querySelector('[data-checkout-price]');
                const note = checkoutCard?.querySelector('[data-checkout-note]');
                const details = checkoutCard?.querySelector('[data-checkout-details]');
                const sku = checkoutCard?.querySelector('[data-checkout-sku]');
                const seller = checkoutCard?.querySelector('[data-checkout-seller]');
                const checkoutForm = checkoutCard?.querySelector('[data-gift-checkout]');
                const productId = checkoutCard?.querySelector('[data-checkout-product-id]');
                const unavailableButton = checkoutCard?.querySelector('[data-unavailable-button]');

                if (!regionSelect || !nominalSelect || !checkoutCard) {
                    return;
                }

                const variants = Array.isArray(groupVariantState.variants) ? groupVariantState.variants : [];
                const formatMoney = (amount, currency = 'RUB') => `${Number(amount).toLocaleString('ru-RU', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).replace(',', '.')} ${currency === 'RUB' ? '₽' : currency}`;
                const trimAmount = (amount) => Number(amount).toLocaleString('ru-RU', {
                    minimumFractionDigits: Number.isInteger(Number(amount)) ? 0 : 2,
                    maximumFractionDigits: 2,
                }).replace(',', '.');
                const priceFrom = (items) => {
                    const rubPrices = items
                        .map((variant) => Number(variant?.nominal_rub_price || 0))
                        .filter((amount) => amount > 0)
                        .sort((a, b) => a - b);

                    if (rubPrices.length === 0) {
                        return '';
                    }

                    return `от ${formatMoney(rubPrices[0], 'RUB')}`;
                };
                const regionVariants = () => variants.filter((variant) => variant.region === regionSelect.value);
                const selectedVariant = () => regionVariants().find((variant) => variant.nominal_value === nominalSelect.value);
                const setDisplay = (element, value) => {
                    if (element) {
                        element.style.display = value;
                    }
                };
                const setPriceMuted = (muted) => {
                    if (!price) {
                        return;
                    }

                    price.style.color = muted ? 'var(--muted)' : '';
                    price.style.fontSize = muted ? '30px' : '';
                };
                const replaceNominals = (items) => {
                    const previous = nominalSelect.value;
                    nominalSelect.innerHTML = '';

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = regionSelect.value ? 'Выберите номинал для региона' : 'Сначала выберите страну';
                    nominalSelect.appendChild(placeholder);

                    const seen = new Set();
                    items.forEach((variant) => {
                        if (!variant.nominal_value || seen.has(variant.nominal_value)) {
                            return;
                        }

                        seen.add(variant.nominal_value);
                        const option = document.createElement('option');
                        option.value = variant.nominal_value;
                        option.textContent = variant.nominal_label || variant.nominal_value;
                        nominalSelect.appendChild(option);
                    });

                    nominalSelect.disabled = !regionSelect.value;
                    nominalSelect.value = seen.has(previous) ? previous : '';
                };
                const syncUrl = () => {
                    const params = new URLSearchParams();

                    if (regionSelect.value) {
                        params.set('region', regionSelect.value);
                    }

                    if (regionSelect.value && nominalSelect.value) {
                        params.set('nominal', nominalSelect.value);
                    }

                    const query = params.toString();
                    const nextUrl = query ? `${groupVariantState.baseUrl}?${query}` : groupVariantState.baseUrl;
                    window.history.replaceState({}, '', nextUrl);
                };
                const renderCheckout = () => {
                    const regionItems = regionVariants();
                    const exact = selectedVariant();
                    const activeRange = priceFrom(regionSelect.value ? regionItems : variants);

                    if (exact && exact.offer && exact.offer.product_id) {
                        const amount = Number(exact.price?.amount || 0);
                        if (kicker) kicker.textContent = 'К оплате';
                        if (price) price.textContent = amount > 0 ? formatMoney(amount, exact.price?.currency || 'RUB') : 'Ожидает цену';
                        setPriceMuted(amount <= 0);
                        if (note) {
                            note.style.background = '#fdf5ff';
                            note.innerHTML = `Код придет в личный сейф. Регион: <strong>${exact.region_label}</strong> · Номинал: <strong>${exact.nominal_label}</strong>.`;
                        }
                        if (sku) sku.textContent = exact.offer.sku || exact.slug;
                        if (seller) seller.textContent = exact.seller?.name || 'Meanly seller';
                        if (productId) productId.value = exact.offer.product_id;
                        setDisplay(details, 'block');
                        setDisplay(checkoutForm, 'grid');
                        setDisplay(unavailableButton, 'none');
                    } else if (regionSelect.value && nominalSelect.value && exact) {
                        if (kicker) kicker.textContent = 'Пока нет цены';
                        if (price) price.textContent = 'Нет в продаже';
                        setPriceMuted(true);
                        if (note) {
                            note.style.background = '#fff7d6';
                            note.innerHTML = `Выбран вариант ${exact.region_label} · ${exact.nominal_label}, но продавец еще не подключил checkout.`;
                        }
                        if (sku) sku.textContent = exact.slug;
                        if (seller) seller.textContent = 'Meanly seller';
                        if (productId) productId.value = '';
                        setDisplay(details, 'block');
                        setDisplay(checkoutForm, 'none');
                        setDisplay(unavailableButton, 'inline-flex');
                    } else {
                        if (kicker) kicker.textContent = activeRange ? 'Цена' : 'Выберите параметры';
                        if (price) price.textContent = activeRange || 'Цена появится здесь';
                        setPriceMuted(!activeRange);
                        if (note) {
                            note.style.background = '#fff7d6';
                            note.innerHTML = regionSelect.value
                                ? 'Выберите номинал: цена и оплата обновятся под выбранный регион.'
                                : 'Выберите страну и номинал: цена изменится под конкретную комбинацию.';
                        }
                        if (productId) productId.value = '';
                        setDisplay(details, 'none');
                        setDisplay(checkoutForm, 'none');
                        setDisplay(unavailableButton, 'none');
                    }
                };
                const render = () => {
                    replaceNominals(regionVariants());
                    renderCheckout();
                    syncUrl();
                };

                regionSelect.addEventListener('change', () => {
                    nominalSelect.value = '';
                    render();
                });

                nominalSelect.addEventListener('change', () => {
                    renderCheckout();
                    syncUrl();
                });

                render();
            });

            document.querySelectorAll('[data-gift-checkout]').forEach((form) => {
                const walletButton = form.querySelector('[data-wallet-pay]');
                const status = form.querySelector('[data-wallet-status]');

                if (!walletButton || !status) {
                    return;
                }

                const firstValidationMessage = (payload, fallback) => {
                    const errors = payload && payload.errors ? Object.values(payload.errors) : [];
                    const first = errors.length > 0 && Array.isArray(errors[0]) ? errors[0][0] : null;

                    return payload?.message || first || fallback;
                };

                const setStatus = (message, isError = false) => {
                    status.textContent = message;
                    status.style.color = isError ? '#b91c1c' : '#065f46';
                };

                walletButton.addEventListener('click', async () => {
                    if (!window.SimpleWebAuthnBrowser || !window.PublicKeyCredential) {
                        setStatus('Ваш браузер не поддерживает Passkey/WebAuthn для RUBT оплаты.', true);
                        return;
                    }

                    walletButton.disabled = true;
                    setStatus('Готовим покупку для подписи Passkey...');

                    try {
                        const formData = new FormData(form);
                        const optionsResponse = await fetch("{{ route('meanly.storefront.checkout.wallet.options') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': formData.get('_token'),
                            },
                            body: formData,
                        });
                        const options = await optionsResponse.json();

                        if (!optionsResponse.ok) {
                            throw new Error(firstValidationMessage(options, 'Не удалось подготовить RUBT оплату.'));
                        }

                        const {
                            pending_tx_id: pendingTxId,
                            tx_hash: txHash,
                            tx_nonce,
                            l1_address,
                            amount_minor,
                            amount,
                            asset,
                            canonical_payload,
                            canonical_json,
                            ...passkeyOptions
                        } = options;

                        setStatus('Подтвердите списание RUBT через Passkey...');
                        const assertion = await SimpleWebAuthnBrowser.startAuthentication({ optionsJSON: passkeyOptions });

                        setStatus('Проверяем подпись и создаем заказ...');
                        const confirmResponse = await fetch("{{ route('meanly.storefront.checkout.wallet.confirm') }}", {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': formData.get('_token'),
                            },
                            body: JSON.stringify({
                                pending_tx_id: pendingTxId,
                                tx_hash: txHash,
                                assertion,
                            }),
                        });
                        const result = await confirmResponse.json();

                        if (!confirmResponse.ok) {
                            throw new Error(firstValidationMessage(result, 'RUBT оплата отклонена.'));
                        }

                        setStatus('Оплата подтверждена. Открываем сейф заказа...');
                        window.location.href = result.cabinet_safe_url || result.safe_url || result.redirect_url || '/cabinet';
                    } catch (error) {
                        setStatus(error.message || 'RUBT оплата не завершена.', true);
                        walletButton.disabled = false;
                    }
                });
            });
        </script>
    @endif
</body>
</html>
