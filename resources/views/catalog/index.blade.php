<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($landing) ? $landing['title'] : (isset($collection) && $collection->meta_title ? $collection->meta_title : 'Каталог цифровых товаров Meanly') }}</title>
    <meta name="description" content="{{ isset($landing) ? $landing['description'] : (isset($collection) && $collection->meta_description ? $collection->meta_description : 'Общий каталог цифровых товаров Meanly: подарочные карты, игровые коды, подписки, лицензии и prepaid-карты с фильтрами.') }}">
    @isset($landing)
        <link rel="canonical" href="{{ $landing['canonical_url'] }}">
    @else
        <link rel="canonical" href="{{ isset($collection) ? route('meanly.catalog.collections.show', $collection->slug) : route('meanly.catalog.index') }}">
    @endisset
    <link rel="alternate" type="text/plain" href="{{ route('llms.txt') }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.catalog.index') }}">
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
        .hero {
            background: var(--panel);
            border: 4px solid var(--line);
            box-shadow: var(--shadow);
            padding: 28px;
            border-radius: var(--radius);
            margin: 30px 0 24px;
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
            max-width: 860px;
            color: var(--muted);
            font-size: 19px;
            line-height: 1.55;
            font-weight: 750;
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
            margin: 0 0 28px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: minmax(180px, 1.25fr) repeat(4, minmax(130px, .9fr)) minmax(170px, 1fr) auto auto;
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
        .filter-field input,
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
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            cursor: pointer;
        }
        .btn:hover { transform: translate(-3px, -3px); box-shadow: 7px 7px 0 var(--line); }
        .filter-submit, .filter-clear { min-height: 44px; white-space: nowrap; }
        .filter-clear { background: #fff; color: var(--ink); }
        .related-discovery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 18px;
        }
        .related-discovery a {
            display: inline-flex;
            border: 2px solid var(--line);
            background: var(--panel);
            padding: 6px 10px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            box-shadow: 2px 2px 0 var(--line);
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
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover { transform: translate(-5px, -5px); box-shadow: 10px 10px 0 var(--line); }
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
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 38px;
            max-width: 100%;
        }
        .catalog-pagination__pages { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; min-width: 0; }
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
        }
        .catalog-pagination__item--active { background: var(--brand); color: #fff; box-shadow: none; }
        .catalog-pagination__item--disabled { opacity: .48; cursor: not-allowed; }
        .catalog-pagination__ellipsis { border-color: transparent; background: transparent; box-shadow: none; padding-inline: 2px; }
        @media (max-width: 1080px) {
            .filter-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 620px) {
            .filter-form, .grid { grid-template-columns: 1fr; }
            .filter-submit, .filter-clear { width: 100%; }
        }
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
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        @php
            $facetData = $facets ?? [];
            $categoryFacets = collect($facetData['categories'] ?? $categories ?? []);
            $brandFacets = collect($facetData['brands'] ?? []);
            $regionFacets = collect($facetData['regions'] ?? []);
            $nominalFacets = collect($facetData['nominals'] ?? []);
            $selectedFacets = (array) ($facetData['selected'] ?? []);
            $sortOptions = (array) ($facetData['sort_options'] ?? ['relevance' => 'Сначала лучшие']);
            $selectedQuery = (string) ($selectedFacets['query'] ?? request('q', ''));
            $selectedCategory = (string) ($selectedFacets['category'] ?? '');
            $selectedBrand = (string) ($selectedFacets['brand'] ?? '');
            $selectedRegion = (string) ($selectedFacets['region'] ?? '');
            $selectedNominalKey = (string) ($selectedFacets['nominal_key'] ?? '');
            $selectedSort = (string) ($selectedFacets['sort'] ?? 'relevance');
            $hasActiveFilters = (bool) ($selectedFacets['has_filters'] ?? false);
            $clearQuery = request()->except(['q', 'category', 'brand', 'region', 'nominal', 'face_value', 'currency', 'page']);

            if (($clearQuery['sort'] ?? null) === 'relevance') {
                unset($clearQuery['sort']);
            }

            $clearUrl = route('meanly.catalog.index');

            if ($clearQuery !== []) {
                $clearUrl .= '?'.http_build_query($clearQuery);
            }
        @endphp

        @if(isset($collection) || isset($landing))
            <a class="back-link" href="{{ route('meanly.catalog.index') }}">← Весь каталог</a>
        @endif

        <section class="hero">
            <div class="eyebrow">{{ isset($landing) ? $landing['eyebrow'] : (isset($collection) ? 'Коллекция' : 'Каталог') }}</div>
            <h1>{{ isset($landing) ? $landing['h1'] : (isset($collection) && $collection->h1 ? $collection->h1 : 'Все цифровые товары.') }}</h1>
            <p class="lead">{{ isset($landing) ? $landing['description'] : (isset($collection) && $collection->title ? $collection->title : 'Единая сетка карточек Meanly: подарочные карты, игровые коды, подписки и prepaid-карты. Фильтруйте по категории, бренду и номиналу.') }}</p>
            <div class="meta">
                <span class="tag">{{ $products->total() }} товаров</span>
                @if($selectedCategory !== '')
                    <span class="tag">Категория выбрана</span>
                @endif
                @if($selectedBrand !== '')
                    <span class="tag">Бренд: {{ $selectedBrand }}</span>
                @endif
                @if($selectedRegion !== '')
                    <span class="tag">Регион: {{ strtoupper($selectedRegion) }}</span>
                @endif
            </div>
            @isset($landing)
                @php
                    $relatedLinks = collect($landing['related_regions'] ?? [])
                        ->concat(collect($landing['related_brands'] ?? []))
                        ->concat(collect($landing['related_categories'] ?? []))
                        ->take(12);
                @endphp
                @if($relatedLinks->isNotEmpty())
                    <div class="related-discovery" aria-label="Связанные сущности">
                        @foreach($relatedLinks as $link)
                            <a href="{{ $link['url'] }}">{{ $link['label'] ?? $link['name'] ?? $link['category'] }}@if(isset($link['product_count'])) · {{ $link['product_count'] }}@endif</a>
                        @endforeach
                    </div>
                @endif
            @endisset
        </section>

        <section class="filter-panel" aria-label="Фильтры каталога">
            <form class="filter-form" method="GET" action="{{ route('meanly.catalog.index') }}">
                <label class="filter-field">
                    <span>Поиск</span>
                    <input name="q" value="{{ $selectedQuery }}" placeholder="Steam, PlayStation, Spotify...">
                </label>

                <label class="filter-field">
                    <span>Категория</span>
                    <select name="category">
                        <option value="">Все категории</option>
                        @foreach($categoryFacets as $categoryFacet)
                            <option value="{{ $categoryFacet['slug'] }}" @selected($selectedCategory === $categoryFacet['slug'])>
                                {{ $categoryFacet['label_ru'] ?? $categoryFacet['name'] ?? $categoryFacet['slug'] }} ({{ $categoryFacet['product_count'] ?? $categoryFacet['count'] ?? 0 }})
                            </option>
                        @endforeach
                    </select>
                </label>

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

                <label class="filter-field filter-field--sort">
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

        @if($products->isEmpty())
            <div class="empty">
                @if($hasActiveFilters)
                    По выбранным фильтрам ничего не найдено. Попробуйте другой запрос, бренд или номинал.
                @else
                    В каталоге пока нет публичных товаров.
                @endif
            </div>
        @else
            <section class="grid" aria-label="Товарная сетка каталога">
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
                            <div class="muted">{{ data_get($product, 'brand') ?: data_get($product, 'category_label', 'Digital goods') }}</div>
                            <div class="muted">{{ data_get($product, 'category_label', 'Digital goods') }} · {{ $isGrouped ? 'несколько регионов' : $regionLabel }}</div>
                            @if($isGrouped)
                                <div>
                                    <span class="tag" style="background: #efe6ff;">{{ $variantSummary }}</span>
                                </div>
                                @if(!empty($variantGroup['regions']))
                                    <div class="muted">Регионы: {{ collect($variantGroup['regions'])->take(4)->implode(', ') }}@if(($variantGroup['region_count'] ?? 0) > 4) и другие@endif</div>
                                @endif
                            @endif
                            @if(is_numeric($faceValue) && (float) $faceValue > 0)
                                <div>
                                    <span class="tag" style="background: #e7fff2;">Номинал: {{ rtrim(rtrim(number_format((float) $faceValue, 2, '.', ' '), '0'), '.') }} {{ $faceCurrency }}</span>
                                </div>
                            @endif
                            @if($selectedOffer)
                                <div class="price">{{ data_get($selectedOffer, 'price.label', number_format((float) data_get($selectedOffer, 'price.amount'), 2, '.', ' ')) }}</div>
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
</body>
</html>
