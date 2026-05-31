@php
    $selectedOffer = $intentResolution['selected_offer'] ?? null;
    $intentLabel = $intentResolution['intent_label'] ?? 'Best offer';
    $intentKey = $intentResolution['intent'] ?? 'best_offer';
    $intentOptions = [
        'best_offer' => 'Лучший оффер',
        'lowest_price' => 'Сначала дешевле',
        'in_stock' => 'В наличии',
        'trusted_seller' => 'Проверенный продавец',
    ];
    $canonicalIdentity = $facts['canonical_identity'] ?? [];
    $categoryLabel = $facts['canonical_category_label'] ?? 'Цифровой товар';
    $productName = $facts['name'] ?? 'Цифровой товар';
    $brandName = trim((string) ($facts['brand'] ?? data_get($canonicalIdentity, 'brand') ?? ''));
    $platformName = trim((string) data_get($canonicalIdentity, 'platform', ''));
    $productFamily = trim((string) data_get($canonicalIdentity, 'product_family', ''));
    $serviceName = $brandName !== ''
        ? $brandName
        : ($platformName !== ''
            ? \Illuminate\Support\Str::headline($platformName)
            : ($productFamily !== '' ? \Illuminate\Support\Str::headline($productFamily) : $categoryLabel));
    $serviceRegion = trim((string) ($facts['region'] ?? data_get($canonicalIdentity, 'region') ?? ''));
    $serviceRegionLabel = $serviceRegion !== '' && strtolower($serviceRegion) !== 'global'
        ? $serviceRegion
        : 'Глобальный регион';
    $faceValue = $facts['face_value'] ?? data_get($canonicalIdentity, 'face_value');
    $faceCurrency = strtoupper(trim((string) ($facts['face_value_currency'] ?? data_get($canonicalIdentity, 'face_value_currency') ?? '')));
    $formatAmount = function ($amount): string {
        if (! is_numeric($amount)) {
            return trim((string) $amount);
        }

        $number = (float) $amount;

        return number_format($number, floor($number) === $number ? 0 : 2, '.', ' ');
    };
    $formatMoney = function ($amount, ?string $currency = null) use ($formatAmount): string {
        if ($amount === null || $amount === '') {
            return '';
        }

        $currency = strtoupper(trim((string) $currency));
        $formatted = $formatAmount($amount);

        if ($currency === 'RUB') {
            return $formatted.' ₽';
        }

        return trim($formatted.' '.$currency);
    };
    $serviceAmountLabel = is_numeric($faceValue) && (float) $faceValue > 0
        ? $formatMoney($faceValue, $faceCurrency ?: null)
        : '';
    $serviceDetailLabel = collect([$serviceAmountLabel ?: $categoryLabel, $serviceRegionLabel])
        ->filter()
        ->implode(' · ');
    $paymentOffer = $selectedOffer ?: data_get($facts, 'seller_offers.best_offer');
    $paymentAmount = data_get($paymentOffer, 'price.amount');
    $paymentCurrency = strtoupper(trim((string) data_get($paymentOffer, 'price.currency', '')));
    if ($paymentCurrency === '' && is_numeric($paymentAmount)) {
        $paymentCurrency = 'RUB';
    }
    $paymentPriceLabel = is_numeric($paymentAmount) && (float) $paymentAmount > 0
        ? $formatMoney($paymentAmount, $paymentCurrency)
        : 'Ожидает цену продавца';
    $paymentContextLabel = $paymentCurrency === 'RUB'
        ? 'Россия / RUB'
        : ($paymentCurrency !== '' ? 'Валюта оплаты: '.$paymentCurrency : 'Валюта оплаты');
    $selectedSellerName = trim((string) (data_get($selectedOffer, 'seller.name') ?: 'Meanly seller'));
    $selectedSellerLegalName = trim((string) data_get($selectedOffer, 'seller.legal_entity', ''));
    $checkoutUser = auth()->user();
    $checkoutUserEmail = trim((string) ($checkoutUser?->email ?? ''));
    $giftCheckoutSelected = filter_var(old('is_gift', false), FILTER_VALIDATE_BOOLEAN);

    $displayImage = null;
    if ($selectedOffer && !empty($selectedOffer['product_id'])) {
        $dbProduct = \App\Models\Product::find($selectedOffer['product_id']);
        if ($dbProduct) {
            $displayImage = $dbProduct->getRedeemDisplayImageSrc();
        }
    }
    
    if (!$displayImage) {
        $letter = mb_strtoupper(mb_substr($serviceName ?: $productName, 0, 1));
        $hash = md5($productName.'|'.$serviceName);
        $hue1 = hexdec(substr($hash, 0, 2)) % 360;
        $hue2 = ($hue1 + 40) % 360;
        $color1 = "hsl({$hue1}, 65%, 45%)";
        $color2 = "hsl({$hue2}, 65%, 35%)";

        $svg = '<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="grad_canonical" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:'.$color1.';stop-opacity:1" />
                    <stop offset="100%" style="stop-color:'.$color2.';stop-opacity:1" />
                </linearGradient>
            </defs>
            <rect width="100%" height="100%" fill="url(#grad_canonical)" />
            <text x="50%" y="45%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="280" font-weight="900" fill="#ffffff" style="opacity: 0.95">'.$letter.'</text>
            '.($serviceAmountLabel ? '
            <rect x="0" y="360" width="100%" height="152" fill="#000000" fill-opacity="0.3" />
            <text x="50%" y="436" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="85" font-weight="900" fill="#ffffff">'.$serviceAmountLabel.'</text>
            ' : '').'
        </svg>';
        $displayImage = 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
@endphp
<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $facts['name'] }} - Meanly</title>
    <meta name="description" content="{{ $facts['description'] }}">
    <meta name="robots" content="{{ data_get($facts, 'indexing_policy.robots', 'noindex,follow') }}">
    <link rel="canonical" href="{{ $facts['url'] }}">
    <link rel="alternate" type="application/json" href="{{ $facts['machine_readable_at'] }}">
    <link rel="alternate" type="application/json" href="{{ $intentResolution['machine_readable_at'] }}">
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => 'Catalog',
                'item' => route('meanly.catalog.index'),
            ],
            [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $facts['canonical_category_label'] ?? 'Products',
                'item' => route('meanly.catalog.categories.show', $facts['canonical_category']),
            ],
            [
                '@type' => 'ListItem',
                'position' => 3,
                'name' => $facts['name'],
                'item' => $facts['url'],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
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
        html { overflow-x: hidden; }
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
            overflow-x: hidden;
        }
        a { color: inherit; text-decoration: none; }
        .shell { width: min(1180px, calc(100vw - 32px)); margin: 0 auto; }
        main.shell { padding-top: 96px; }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--line);
            border-radius: 3px;
            min-height: 46px;
            padding: 0 22px;
            font-weight: 950;
            background: var(--panel);
            color: var(--ink);
            box-shadow: 4px 4px 0 var(--line);
            transition: transform .15s ease, box-shadow .15s ease;
            cursor: pointer;
        }
        .btn:hover { transform: translate(2px, 2px); box-shadow: 2px 2px 0 var(--line); }
        .btn-primary { background: var(--brand); color: #fff; }
        .btn-secondary { background: var(--panel); color: var(--ink); }
        
        .back-link {
            display: inline-flex;
            margin: 30px 0 18px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 12px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
        }
        .product-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 390px;
            gap: 28px;
            align-items: start;
            padding-bottom: 72px;
        }
        .product-panel,
        .checkout-panel,
        .description-panel {
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
        }
        .product-panel { overflow: hidden; }
        .image-frame {
            background: #181528;
            border-bottom: 4px solid var(--line);
            padding: 22px;
            display: grid;
            place-items: center;
        }
        .image-frame img {
            width: min(100%, 680px);
            max-height: 560px;
            object-fit: contain;
            display: block;
        }
        .product-copy { padding: 24px; }
        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border: 3px solid var(--line);
            background: var(--brand-soft);
            color: var(--brand);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            box-shadow: 4px 4px 0 var(--line);
            margin-bottom: 18px;
        }
        h1 {
            margin: 0;
            max-width: 880px;
            font-size: clamp(42px, 6vw, 82px);
            line-height: .9;
            letter-spacing: -.08em;
            font-weight: 950;
        }
        .meta-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .meta-pill {
            border: 2px solid var(--line);
            background: #d8ff6f;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 950;
            text-transform: uppercase;
            box-shadow: 3px 3px 0 var(--line);
        }
        .meta-pill.soft { background: var(--brand-soft); }
        .checkout-panel {
            position: sticky;
            top: 104px;
            padding: 22px;
        }
        .seller {
            color: var(--muted);
            font-size: 13px;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .price {
            margin: 8px 0 18px;
            font-size: clamp(38px, 4vw, 54px);
            line-height: 1;
            font-weight: 950;
            letter-spacing: -.06em;
        }
        .checkout-note {
            border: 3px solid var(--line);
            background: #fff7d6;
            padding: 12px;
            font-weight: 850;
            color: var(--muted);
            margin-bottom: 16px;
        }
        .payment-methods {
            display: grid;
            gap: 10px;
            margin: 14px 0;
        }
        .payment-method-card {
            border: 3px solid var(--line);
            background: #eef2ff;
            padding: 12px;
            box-shadow: 3px 3px 0 var(--line);
        }
        .payment-method-card.is-disabled {
            background: #f4f4f5;
            color: var(--muted);
        }
        .payment-method-card strong {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            color: var(--ink);
            font-weight: 950;
        }
        .payment-badge {
            display: inline-flex;
            align-items: center;
            border: 2px solid var(--line);
            background: #d9f99d;
            padding: 3px 7px;
            color: var(--ink);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 10px;
            font-weight: 950;
            text-transform: uppercase;
            box-shadow: 2px 2px 0 var(--line);
            white-space: nowrap;
        }
        .payment-method-card p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
        }
        .payment-method-card button {
            width: 100%;
            margin-top: 10px;
        }
        label {
            display: block;
            margin: 12px 0 6px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        input {
            width: 100%;
            min-height: 48px;
            border: 3px solid var(--line);
            border-radius: 3px;
            padding: 0 13px;
            background: var(--panel);
            color: var(--ink);
            font: inherit;
            font-weight: 850;
            box-shadow: 3px 3px 0 var(--line);
        }
        .checkout-recipient-summary {
            border: 3px solid var(--line);
            background: #e7fff2;
            padding: 12px;
            font-size: 14px;
            font-weight: 850;
            color: var(--muted);
            margin: 14px 0 4px;
        }
        .checkout-recipient-summary strong {
            display: block;
            color: var(--ink);
            word-break: break-word;
        }
        .gift-checkbox {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .gift-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0 8px;
            border: 3px solid var(--line);
            background: var(--panel);
            padding: 11px 12px;
            box-shadow: 3px 3px 0 var(--line);
            cursor: pointer;
        }
        .gift-toggle::before {
            content: '';
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            border: 3px solid var(--line);
            background: var(--panel);
        }
        .gift-checkbox:checked + .gift-toggle::before {
            background: var(--brand);
            box-shadow: inset 0 0 0 3px var(--panel);
        }
        .gift-toggle span {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .gift-fields { margin-top: 8px; }
        .gift-checkbox:not(:checked) ~ .gift-fields { display: none; }
        .recipient-help {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.35;
        }
        button.btn { width: 100%; margin-top: 18px; }
        .error {
            border: 3px solid var(--line);
            background: #ffd7ef;
            padding: 12px;
            font-weight: 900;
            margin: 16px 0 0;
        }
        .description-panel {
            margin-top: 24px;
            padding: 24px;
        }
        .description-panel h2 {
            margin: 0 0 12px;
            font-size: clamp(26px, 3vw, 42px);
            letter-spacing: -.05em;
            line-height: 1;
        }
        .description {
            color: var(--muted);
            font-size: 18px;
            line-height: 1.55;
            font-weight: 750;
        }
        
        /* Intent switcher buttons style */
        .intent-links { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 18px; }
        .intent-links a {
            border: 2px solid var(--line);
            background: var(--panel);
            padding: 8px 12px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 950;
            box-shadow: 3px 3px 0 var(--line);
            transition: transform .1s ease, box-shadow .1s ease;
            text-transform: uppercase;
        }
        .intent-links a:hover {
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 var(--line);
        }
        .intent-links a.active {
            background: var(--brand);
            color: #fff;
        }

        .mini {
            border-bottom: 2px solid var(--line);
            padding: 12px 0;
        }
        .mini:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .mini span {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .mini strong {
            display: block;
            font-size: 14px;
            font-weight: 850;
        }
        .inline-safe-panel {
            display: grid;
            gap: 14px;
        }
        .inline-safe-status-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .inline-safe-card {
            border: 3px solid var(--line);
            background: #f8fafc;
            padding: 12px;
            box-shadow: 3px 3px 0 var(--line);
        }
        .inline-safe-card span,
        .inline-safe-link {
            display: block;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .inline-safe-card strong {
            display: block;
            margin-top: 6px;
            font-size: 15px;
            font-weight: 950;
        }
        .inline-safe-actions {
            display: grid;
            gap: 10px;
        }
        .inline-safe-actions .btn {
            margin-top: 0;
        }
        .inline-safe-link {
            text-align: center;
            padding: 10px 0 0;
            text-decoration: underline;
            text-underline-offset: 4px;
        }
        .inline-safe-code-list {
            display: grid;
            gap: 10px;
        }
        .inline-safe-code {
            border: 3px solid var(--line);
            background: #ecfdf5;
            padding: 12px;
            box-shadow: 3px 3px 0 var(--line);
        }
        .inline-safe-code span {
            display: block;
            color: var(--muted);
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .inline-safe-code code {
            display: block;
            margin-top: 8px;
            color: #064e3b;
            overflow-wrap: anywhere;
            font-size: 18px;
            font-weight: 950;
        }
        .inline-safe-code a {
            display: inline-flex;
            margin-top: 8px;
            color: var(--brand);
            font-weight: 950;
            text-decoration: underline;
        }

        /* ── STOREFRONT INLINE SCRATCH CARD STYLES ── */
        .storefront-scratch-wrapper {
            width: 100%;
            position: relative;
        }
        .storefront-scratch-wrapper.has-scratch {
            padding: 0;
            overflow: hidden;
            width: 100%;
            height: 84px;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            background: transparent;
            border: 3px solid var(--line);
            box-shadow: 4px 4px 0 var(--line);
            border-radius: 8px;
            position: relative;
        }
        .inline-scratch-container {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.4);
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .inline-scratch-underlay {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            box-sizing: border-box;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.01) 0%, rgba(0, 0, 0, 0.35) 100%);
            transition: filter 0.3s ease;
            gap: 0.35rem;
        }
        .inline-scratch-container.is-blurred .inline-scratch-underlay {
            filter: blur(8px);
        }
        .inline-scratch-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            z-index: 5;
            transition: opacity 0.4s ease, transform 0.4s ease;
            touch-action: none;
        }
        .inline-scratch-canvas.fade-out {
            opacity: 0;
            transform: scale(1.05);
            pointer-events: none;
        }
        
        .revealed-inline-code {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            gap: 0.25rem;
        }
        .revealed-inline-code code {
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            font-weight: 900;
            word-break: break-all;
            text-align: center;
            user-select: text;
        }
        .revealed-inline-actions {
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        .revealed-inline-actions button,
        .revealed-inline-actions a {
            all: unset;
            cursor: pointer;
            color: #d8ff6f;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: color 0.2s;
        }
        .revealed-inline-actions button:hover,
        .revealed-inline-actions a:hover {
            color: #fff;
        }

        .inline-reveal-btn {
            position: absolute;
            bottom: 6px;
            right: 6px;
            z-index: 9999 !important;
            transform: translate3d(0, 0, 10px);
            pointer-events: auto !important;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 3px 8px;
            border-radius: 4px;
            cursor: pointer;
            display: none;
            transition: all 0.2s ease;
        }
        .inline-reveal-btn:hover {
            background: #a855f7;
            border-color: #fff;
            color: #fff;
        }
        .scratch-proof-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 8px;
            font-weight: 850;
            color: #d8ff6f !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        @media (max-width: 920px) {
            .product-layout {
                grid-template-columns: 1fr;
                gap: 0;
            }
            .product-main-column { display: contents; }
            .product-panel {
                order: 1;
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                box-shadow: 8px 0 0 var(--line);
            }
            .checkout-panel {
                position: static;
                order: 2;
                border-top: 0;
                border-top-left-radius: 0;
                border-top-right-radius: 0;
                box-shadow: var(--shadow);
                padding: 16px;
            }
            .description-panel {
                order: 3;
                margin-top: 28px;
            }
            .inline-safe-status-grid { grid-template-columns: 1fr; }
        }

        .product-card-visual {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 64px 150px 64px minmax(0, 1fr);
            align-items: center;
            justify-items: center;
            width: 100%;
            min-height: 300px;
            background:
                radial-gradient(circle at 50% 24%, rgba(124, 58, 237, .35), transparent 34%),
                linear-gradient(135deg, #111827 0%, #181528 48%, #08111f 100%);
            padding: 28px;
            border-bottom: 4px solid var(--line);
            position: relative;
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .product-card-visual {
                grid-template-columns: 1fr;
                min-height: auto;
                gap: 18px;
                padding: 26px 16px;
            }
            .card-connector { display: none !important; }
        }
        .card-side {
            border: 3px solid var(--line);
            border-radius: 10px;
            padding: 16px 14px;
            background: #ffffff;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            width: 100%;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 8px;
            box-shadow: 4px 4px 0 var(--line);
            position: relative;
            z-index: 2;
        }
        .payment-side {
            background: #d8ff6f;
            color: var(--ink);
        }
        .service-side {
            background: #efe6ff;
            color: var(--ink);
        }
        .side-label {
            font-size: 10px;
            font-weight: 900;
            color: var(--brand);
            display: block;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .card-side strong {
            display: block;
            font-size: clamp(20px, 2.4vw, 30px);
            font-weight: 900;
            color: var(--ink);
            letter-spacing: -.04em;
            line-height: 1;
        }
        .side-meta,
        .side-note {
            display: block;
            font-size: 12px;
            font-weight: 850;
            line-height: 1.3;
            color: var(--muted);
        }
        .side-note {
            padding-top: 6px;
            border-top: 2px dashed rgba(5, 5, 5, .24);
        }
        .card-connector {
            width: 100%;
            height: 4px;
            background: var(--line);
            position: relative;
            z-index: 1;
        }
        .connector-beam {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 40px;
            background: linear-gradient(90deg, transparent, #d8ff6f, transparent);
            animation: cardFlow 1.8s linear infinite;
        }
        .right-connector .connector-beam { background: linear-gradient(90deg, transparent, #c4b5fd, transparent); }
        .denomination-tile {
            position: relative;
            z-index: 2;
            width: 150px;
            border: 3px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: #ffffff;
            box-shadow: 6px 6px 0 var(--line);
        }
        .denomination-tile img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: contain;
            background: #1c1930;
            display: block;
        }
        .tile-caption {
            border-top: 3px solid var(--line);
            padding: 10px;
            background: var(--panel);
            text-align: center;
        }
        .tile-caption span,
        .tile-caption strong {
            display: block;
            font-family: 'JetBrains Mono', monospace;
            line-height: 1.2;
        }
        .tile-caption span {
            font-size: 11px;
            font-weight: 900;
            color: var(--brand);
            text-transform: uppercase;
        }
        .tile-caption strong {
            margin-top: 4px;
            font-size: 15px;
            font-weight: 950;
            color: var(--ink);
        }
        @keyframes cardFlow {
            0% { left: -20%; }
            100% { left: 120%; }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <a class="back-link" href="{{ route('meanly.catalog.categories.show', $facts['canonical_category']) }}">← {{ $facts['canonical_category_label'] }}</a>

        <section class="product-layout">
            <div class="product-main-column">
                <article class="product-panel">
                    <div class="buyer-product-hero" aria-label="Карточка товара">
                        <div class="buyer-product-image">
                            <img src="{{ $displayImage }}" alt="{{ $productName }}">
                        </div>
                        <div class="buyer-product-summary">
                            <div class="eyebrow">{{ $categoryLabel }} · {{ $serviceRegionLabel }}</div>
                            <h1>{{ $productName }}</h1>
                            <p>
                                Цифровой код для {{ $serviceName }}. После оплаты покупка появится в личном сейфе и будет отправлена на email.
                            </p>
                            @if($selectedOffer)
                                <div class="buyer-seller-line">
                                    <span>Продавец:</span>
                                    <strong>{{ $selectedSellerName }}</strong>
                                    @if($selectedSellerLegalName !== '' && $selectedSellerLegalName !== $selectedSellerName)
                                        <em>Магазин: {{ $selectedSellerLegalName }}</em>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="buyer-product-copy product-copy">
                        <div class="meta-row">
                            <span class="meta-pill">цифровая выдача</span>
                            <span class="meta-pill soft">{{ $serviceRegionLabel }}</span>
                            @if($serviceAmountLabel !== '')
                                <span class="meta-pill" style="background: #e7fff2; color: #000;">номинал {{ $serviceAmountLabel }}</span>
                            @endif
                            <span class="meta-pill soft">{{ $selectedOffer ? 'в наличии у продавца' : 'ожидает цену' }}</span>
                        </div>

                        <div class="buyer-trust-list" aria-label="Что важно знать перед покупкой">
                            <div class="buyer-trust-item">
                                <strong>Доставка</strong>
                                На email и в личный сейф после оплаты.
                            </div>
                            <div class="buyer-trust-item">
                                <strong>Регион</strong>
                                {{ $serviceRegionLabel }}.
                            </div>
                            <div class="buyer-trust-item">
                                <strong>Поддержка</strong>
                                Поможем по заказу из кабинета.
                            </div>
                            @if($selectedOffer)
                                <div class="buyer-trust-item">
                                    <strong>Продавец</strong>
                                    {{ $selectedSellerName }}.
                                </div>
                            @endif
                        </div>
                    </div>
                </article>

                <section class="description-panel">
                    <h2>Описание</h2>
                    <div class="description">
                        {!! nl2br(e(trim(strip_tags((string) $facts['description'])) ?: 'Цифровой товар Meanly с выдачей через защищенный checkout и redeem-ссылку.')) !!}
                        
                        @if($serviceAmountLabel !== '')
                            <div style="margin-top: 18px; border: 2px solid var(--line); border-radius: 8px; padding: 16px; background: #e7fff2;">
                                <strong style="display: block; font-size: 16px; margin-bottom: 6px;">{{ $serviceName }} · {{ $serviceAmountLabel }}</strong>
                                <p style="margin: 0; font-size: 15px; color: var(--muted); line-height: 1.55;">
                                    Перед оплатой проверьте регион активации: <strong>{{ $serviceRegionLabel }}</strong>.
                                </p>
                            </div>
                        @endif
                    </div>
                </section>

            </div>

            <aside class="checkout-panel" data-checkout-panel>
                @if($selectedOffer)
                    <div data-checkout-flow>
                        <div class="seller">К оплате</div>
                        <div class="price">{{ $paymentPriceLabel }}</div>
                        <div class="checkout-note" style="background: #fdf5ff; border-color: #a855f7;">Код появится в личном сейфе. Email нужен только для подарочной или гостевой доставки. Регион активации: {{ $serviceRegionLabel }}.</div>
                        
                        <form method="POST" action="{{ route('meanly.storefront.checkout') }}" data-gift-checkout>
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $selectedOffer['product_id'] }}">
                            <label for="quantity">Количество</label>
                            <input id="quantity" name="quantity" type="number" min="1" max="5" value="{{ old('quantity', 1) }}">

                            @if($checkoutUser)
                                <input type="hidden" name="is_gift" value="0">
                                <input
                                    id="is_gift"
                                    class="gift-checkbox"
                                    name="is_gift"
                                    type="checkbox"
                                    value="1"
                                    data-gift-toggle
                                    @checked($giftCheckoutSelected)
                                >
                                <label class="gift-toggle" for="is_gift">
                                    <span>Отправить на другой email</span>
                                </label>
                                <div class="gift-fields">
                                    <label for="email">Email получателя</label>
                                    <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="укажите другой email для доставки" data-gift-email @if($giftCheckoutSelected) required @endif>
                                    <label for="name">Имя получателя</label>
                                    <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="укажите имя получателя">
                                </div>
                            @else
                                <label for="email">Email для доставки кода</label>
                                <input id="email" name="email" type="email" required value="{{ old('email') }}" placeholder="укажите адрес доставки кода">
                                <label for="name">Имя получателя</label>
                                <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="укажите имя">
                                <p class="recipient-help">Войдите через SL1E wallet, чтобы получать коды в личный сейф без email.</p>
                            @endif

                            <div class="payment-methods" aria-label="Способы оплаты">
                                <div class="payment-method-card is-disabled" aria-disabled="true">
                                    <strong>
                                        СБП
                                        <span class="payment-badge">Скоро</span>
                                    </strong>
                                    <p>Оплата через Систему быстрых платежей появится здесь: QR/deep link банка, подтверждение платежа и выдача кода только после verified capture.</p>
                                    <button class="btn btn-secondary" type="button" disabled>
                                        Оплата СБП скоро будет
                                    </button>
                                </div>
                            </div>

                            @if($checkoutUser)
                                <div class="buyer-wallet-secondary">
                                    <div class="checkout-note" style="background: #ecfdf5; border-color: #10b981; margin-top: 12px;">
                                        Баланс и операции перенесены в SL1 Wallet. Meanly показывает чек, статус оплаты и выдачу кода.
                                    </div>
                                </div>
                            @else
                                <a class="btn btn-primary" href="{{ route('login') }}" style="width: 100%; margin-top: 18px;">
                                    Войти для покупки
                                </a>
                            @endif
                        </form>
                        
                        @if($errors->any())
                            <p class="error">{{ $errors->first() }}</p>
                        @endif
                    </div>

                    <template data-inline-order-safe-template>
                        <div class="inline-safe-panel" data-inline-order-safe-panel>
                            <span style="display: none;">Открыть код</span>
                            <div class="seller">Выдача заказа</div>
                            <div class="price" data-inline-safe-order style="display: none;">Заказ оплачен</div>
                            <div class="checkout-note" data-inline-safe-message style="display: none;">
                                Оплата подтверждена. Готовим карту выдачи.
                            </div>
                            
                            <div class="storefront-scratch-wrapper" data-storefront-scratch-wrapper style="margin-top: 16px;"></div>

                            <div class="inline-safe-actions" data-inline-safe-actions-row style="margin-top: 16px; display: grid; gap: 10px;">
                                <button class="btn btn-secondary" type="button" data-inline-safe-refresh>Обновить статус</button>
                                <a class="inline-safe-link" href="#" data-inline-safe-link style="text-align: center; text-decoration: underline; text-underline-offset: 4px; padding-top: 10px; display: block;">Открыть заказ в личном сейфе</a>
                            </div>
                            <p class="recipient-help" data-inline-safe-hint aria-live="polite" style="display: none;">
                                Выдача обновится автоматически, когда код будет готов.
                            </p>
                        </div>
                    </template>
                @else
                    <div class="seller" style="margin-bottom: 8px;">Пока нет цены продавца</div>
                    <div class="price" style="font-size: 28px; line-height: 1.1; margin-bottom: 16px; color: var(--muted); letter-spacing: -0.04em;">Ожидает цену</div>
                    <div class="checkout-note" style="background: #fff7d6;">Сейчас нет активного предложения для покупки. Мы покажем цену на витрине, когда продавец подключит этот товар.</div>
                    <button class="btn" style="width: 100%; margin-top: 8px;" disabled>Нет в продаже</button>
                @endif
            </aside>
        </section>
    </main>
    @include('storefront.partials.footer')
    <script>
        document.querySelectorAll('[data-gift-checkout]').forEach((form) => {
            const toggle = form.querySelector('[data-gift-toggle]');
            const email = form.querySelector('[data-gift-email]');

            if (!toggle || !email) {
                return;
            }

            const syncGiftFields = () => {
                email.required = toggle.checked;
            };

            toggle.addEventListener('change', syncGiftFields);
            syncGiftFields();
        });

        const inlineSafeTemplate = document.querySelector('[data-inline-order-safe-template]');
        const checkoutPanel = document.querySelector('[data-checkout-panel]');


        const safeEndpointUrl = (safeUrl, endpoint, explicitUrl = null) => {
            if (explicitUrl) {
                return explicitUrl;
            }

            const url = new URL(safeUrl, window.location.origin);
            url.pathname = `${url.pathname.replace(/\/$/, '')}/${endpoint}`;

            return url.toString();
        };

        const standaloneSafeUrlFor = (result) => {
            const safeUrl = result?.safe_url || result?.redirect_url || null;

            if (!safeUrl) {
                return null;
            }

            try {
                const parsed = new URL(safeUrl, window.location.origin);

                if (parsed.pathname.includes('/cabinet') || parsed.pathname.includes('/vault')) {
                    return null;
                }
            } catch (error) {
                if (String(safeUrl).includes('/cabinet') || String(safeUrl).includes('/vault')) {
                    return null;
                }
            }

            return safeUrl;
        };

        const renderStandaloneSafeFallback = (result, reason = null) => {
            const standaloneSafeUrl = standaloneSafeUrlFor(result);

            if (!checkoutPanel || !standaloneSafeUrl) {
                return false;
            }

            const panel = document.createElement('div');
            const title = document.createElement('div');
            const order = document.createElement('div');
            const note = document.createElement('div');
            const actions = document.createElement('div');
            const standaloneLink = document.createElement('a');
            const hint = document.createElement('p');

            panel.className = 'inline-safe-panel';
            title.className = 'seller';
            title.textContent = 'Выдача заказа';
            order.className = 'price';
            order.textContent = result?.order_id ? `Заказ ${result.order_id}` : 'Заказ оплачен';
            note.className = 'checkout-note';
            note.style.background = '#fff7d6';
            note.style.borderColor = '#f59e0b';
            note.textContent = reason || 'Встроенная выдача недоступна. Заказ оплачен, откройте отдельную защищенную страницу заказа.';
            actions.className = 'inline-safe-actions';
            standaloneLink.className = 'btn btn-primary';
            standaloneLink.href = standaloneSafeUrl;
            standaloneLink.textContent = 'Открыть выдачу отдельно';
            hint.className = 'recipient-help';
            hint.textContent = 'Мы не перенаправляем в личный кабинет автоматически. Страница выдачи откроется только по отдельной ссылке.';
            actions.appendChild(standaloneLink);

            if (result?.cabinet_safe_url) {
                const cabinetLink = document.createElement('a');
                cabinetLink.className = 'inline-safe-link';
                cabinetLink.href = result.cabinet_safe_url;
                cabinetLink.textContent = 'Открыть заказ в личном сейфе';
                actions.appendChild(cabinetLink);
            }

            panel.append(title, order, note, actions, hint);
            checkoutPanel.replaceChildren(panel);

            return true;
        };

        const renderStorefrontScratchCard = (panel, result) => {
            const wrapper = panel.querySelector('[data-storefront-scratch-wrapper]');
            if (!wrapper) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
                || document.querySelector('input[name="_token"]')?.value
                || '';

            const isScratched = result.scratched === true;
            const savedProof = result.scratch_proof || '';

            wrapper.innerHTML = '';
            wrapper.className = 'storefront-scratch-wrapper has-scratch';

            const container = document.createElement('div');
            container.className = 'inline-scratch-container' + (isScratched ? '' : ' is-blurred');

            const underlay = document.createElement('div');
            underlay.className = 'inline-scratch-underlay';

            const revealedCode = document.createElement('div');
            revealedCode.className = 'revealed-inline-code';

            const codeElement = document.createElement('code');
            codeElement.textContent = 'КОД ГОТОВИТСЯ...';
            codeElement.style.userSelect = 'none';

            revealedCode.appendChild(codeElement);
            underlay.appendChild(revealedCode);
            container.appendChild(underlay);

            let canvas = null;
            let revealBtn = null;
            let ctx = null;
            let dpr = 1;

            wrapper.appendChild(container);

            const rect = container.getBoundingClientRect();

            if (!isScratched) {
                canvas = document.createElement('canvas');
                canvas.className = 'inline-scratch-canvas';
                container.appendChild(canvas);

                dpr = window.devicePixelRatio || 1;
                canvas.width = rect.width * dpr;
                canvas.height = rect.height * dpr;
                canvas.style.width = `${rect.width}px`;
                canvas.style.height = `${rect.height}px`;

                ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);
            }

            const paintCanvas = (text = 'ВЫДАЧА ЗАКАЗА // СОТРИТЕ КАРТУ') => {
                if (isScratched || !canvas) return;
                const w = rect.width;
                const h = rect.height;

                ctx.clearRect(0, 0, w, h);
                
                const grad = ctx.createLinearGradient(0, 0, w, h);
                grad.addColorStop(0, '#e5e7eb');
                grad.addColorStop(0.2, '#d1d5db');
                grad.addColorStop(0.5, '#f9fafb');
                grad.addColorStop(0.8, '#9ca3af');
                grad.addColorStop(1, '#4b5563');

                ctx.fillStyle = grad;
                ctx.fillRect(0, 0, w, h);

                ctx.strokeStyle = 'rgba(255, 255, 255, 0.22)';
                ctx.lineWidth = 1;
                const lineSpacing = 8;
                for (let x = -h; x < w; x += lineSpacing) {
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x + h, h);
                    ctx.stroke();
                }
                for (let x = 0; x < w + h; x += lineSpacing) {
                    ctx.beginPath();
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x - h, h);
                    ctx.stroke();
                }

                for (let i = 0; i < 600; i++) {
                    const px = Math.random() * w;
                    const py = Math.random() * h;
                    ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.15)';
                    ctx.fillRect(px, py, 1.2, 1.2);
                }

                ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
                ctx.lineWidth = 1.5;
                ctx.setLineDash([4, 4]);
                ctx.strokeRect(5, 5, w - 10, h - 10);
                ctx.setLineDash([]);

                ctx.fillStyle = '#1f2937';
                ctx.font = 'bold 9.5px "Outfit", "Inter", sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.letterSpacing = '0.12em';
                ctx.shadowColor = 'rgba(255, 255, 255, 0.5)';
                ctx.shadowBlur = 1;
                ctx.shadowOffsetX = 0;
                ctx.shadowOffsetY = 1;
                ctx.fillText(text, w / 2, h / 2);
                ctx.shadowColor = 'transparent';
            };

            if (!isScratched) {
                paintCanvas('ВЫДАЧА ЗАКАЗА // СОТРИТЕ КАРТУ');
            }

            let codeItem = null;
            let isDrawingEnabled = false;
            let revealed = false;

            const generateCryptoFingerprint = async () => {
                try {
                    const encoder = new TextEncoder();
                    const rawString = `order-issue-scratch-proof-storefront-${Date.now()}-${Math.random()}`;
                    const data = encoder.encode(rawString);
                    const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
                    const hashArray = Array.from(new Uint8Array(hashBuffer));
                    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').substring(0, 16).toUpperCase();
                } catch (e) {
                    return Math.random().toString(36).substring(2, 10).toUpperCase();
                }
            };

            const revealCode = async (isManual = true) => {
                if (revealed || !codeItem) return;
                revealed = true;
                isDrawingEnabled = false;

                if (canvas) canvas.classList.add('fade-out');
                container.classList.remove('is-blurred');
                codeElement.style.userSelect = 'text';

                const actionsRow = document.createElement('div');
                actionsRow.className = 'revealed-inline-actions';

                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.textContent = 'Скопировать';
                copyBtn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(codeItem.code || '');
                        copyBtn.textContent = 'Скопировано';
                        window.setTimeout(() => copyBtn.textContent = 'Скопировать', 1800);
                    } catch (error) {
                        copyBtn.textContent = 'Ошибка';
                    }
                });

                actionsRow.appendChild(copyBtn);

                if (codeItem.redeem_url) {
                    const redeemLink = document.createElement('a');
                    redeemLink.href = codeItem.redeem_url;
                    redeemLink.target = '_blank';
                    redeemLink.textContent = 'Активировать';
                    actionsRow.appendChild(redeemLink);
                }
                
                revealedCode.appendChild(actionsRow);

                let fingerprint = savedProof;
                if (!fingerprint) {
                    const rawFingerprint = await generateCryptoFingerprint();
                    fingerprint = `SHA256-${rawFingerprint}`;
                }

                result.scratched = true;
                result.scratch_proof = fingerprint;

                const badge = document.createElement('div');
                badge.className = 'scratch-proof-badge';
                badge.innerHTML = `<i class="ph-bold ph-shield-check"></i> SECURE PROOF: ${fingerprint.includes('SHA256-') ? fingerprint : 'SHA256-' + fingerprint}...`;
                revealedCode.appendChild(badge);

                if (isManual && !savedProof) {
                    try {
                        const scratchUrl = result.safe_open_url.replace('/open', '/scratch');
                        await fetch(scratchUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ scratch_proof: fingerprint.includes('SHA256-') ? fingerprint : 'SHA256-' + fingerprint }),
                        });
                    } catch (err) {}
                }

                if (canvas) {
                    window.setTimeout(() => {
                        canvas.remove();
                    }, 400);
                }
            };

            fetch(result.safe_open_url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ open: true }),
            })
            .then(res => res.json())
            .then(payload => {
                if (payload && payload.ready && Array.isArray(payload.codes) && payload.codes.length > 0) {
                    codeItem = payload.codes[0];
                    codeElement.textContent = codeItem.code || '';
                    isDrawingEnabled = true;

                    if (isScratched) {
                        revealCode(false);
                    }
                } else {
                    throw new Error('Код недоступен');
                }
            })
            .catch(err => {
                codeElement.textContent = err.message || 'Ошибка загрузки';
                if (!isScratched) paintCanvas('ОШИБКА ДЕШИФРОВАНИЯ');
            });

            if (!isScratched) {
                let isDrawing = false;
                let lastPoint = null;
                let lastScratchCheck = 0;
                const revealThreshold = 0.45;

                const getMousePos = (e) => {
                    const crect = canvas.getBoundingClientRect();
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return {
                        x: clientX - crect.left,
                        y: clientY - crect.top
                    };
                };

                const erasedRatio = () => {
                    if (!ctx || !canvas) return 0;

                    const pixels = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    let erased = 0;

                    for (let i = 3; i < pixels.length; i += 4) {
                        if (pixels[i] === 0) erased++;
                    }

                    return erased / (pixels.length / 4);
                };

                const maybeRevealScratchedCode = (force = false) => {
                    const now = Date.now();

                    if (!force && now - lastScratchCheck < 120) return;

                    lastScratchCheck = now;

                    if (erasedRatio() >= revealThreshold) {
                        revealCode(true);
                    }
                };

                const scratch = (x, y) => {
                    if (!isDrawingEnabled || revealed || !ctx) return;

                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.beginPath();
                    ctx.arc(x, y, 18, 0, Math.PI * 2);
                    ctx.fill();

                    if (lastPoint) {
                        ctx.beginPath();
                        ctx.lineWidth = 36;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.moveTo(lastPoint.x, lastPoint.y);
                        ctx.lineTo(x, y);
                        ctx.stroke();
                    }

                    lastPoint = { x, y };

                    maybeRevealScratchedCode();
                };

                canvas.addEventListener('mousedown', (e) => {
                    isDrawing = true;
                    const pos = getMousePos(e);
                    lastPoint = pos;
                    scratch(pos.x, pos.y);
                });

                window.addEventListener('mousemove', (e) => {
                    if (!isDrawing) return;
                    const pos = getMousePos(e);
                    scratch(pos.x, pos.y);
                });

                window.addEventListener('mouseup', () => {
                    if (isDrawing) {
                        maybeRevealScratchedCode(true);
                    }

                    isDrawing = false;
                    lastPoint = null;
                });

                canvas.addEventListener('touchstart', (e) => {
                    isDrawing = true;
                    const pos = getMousePos(e);
                    lastPoint = pos;
                    scratch(pos.x, pos.y);
                    e.preventDefault();
                }, { passive: false });

                canvas.addEventListener('touchmove', (e) => {
                    if (!isDrawing) return;
                    const pos = getMousePos(e);
                    scratch(pos.x, pos.y);
                    e.preventDefault();
                }, { passive: false });

                canvas.addEventListener('touchend', () => {
                    if (isDrawing) {
                        maybeRevealScratchedCode(true);
                    }

                    isDrawing = false;
                    lastPoint = null;
                });
            }
        };

        const renderInlineOrderSafe = (result, csrfToken) => {
            // openSafe({ automatic: true });
            if (!checkoutPanel || !inlineSafeTemplate || !result?.safe_status_url || !result?.safe_open_url) {
                return false;
            }

            const safeUrl = standaloneSafeUrlFor(result) || result.safe_url || result.safe_status_url;
            const statusUrl = safeEndpointUrl(safeUrl, 'status', result.safe_status_url);
            const panel = inlineSafeTemplate.content.firstElementChild.cloneNode(true);
            const state = {
                pollCount: 0,
                maxPolls: 24,
                pollTimer: null,
                scratchRendered: false,
            };

            checkoutPanel.replaceChildren(panel);

            const order = panel.querySelector('[data-inline-safe-order]');
            const message = panel.querySelector('[data-inline-safe-message]');
            const refreshButton = panel.querySelector('[data-inline-safe-refresh]');
            const safeLink = panel.querySelector('[data-inline-safe-link]');
            const hint = panel.querySelector('[data-inline-safe-hint]');

            if (result.cabinet_safe_url) {
                safeLink.href = result.cabinet_safe_url;
                safeLink.textContent = 'Открыть заказ в личном сейфе';
            } else {
                safeLink.hidden = true;
            }

            order.textContent = result.order_id ? `Заказ ${result.order_id}` : 'Заказ оплачен';

            const renderStatus = (payload = {}) => {
                const ready = payload.ready || false;
                const failed = payload.failed || false;

                if (ready && !failed) {
                    message.style.display = 'none';
                    if (hint) hint.style.display = 'none';
                    const actionsRow = panel.querySelector('[data-inline-safe-actions-row]');
                    if (actionsRow) actionsRow.style.display = 'none';

                    if (!state.scratchRendered) {
                        state.scratchRendered = true;
                        window.clearTimeout(state.pollTimer);

                        // Merge scratched state from payload into result
                        if (payload.scratched !== undefined) {
                            result.scratched = payload.scratched;
                            result.scratch_proof = payload.scratch_proof;
                        }

                        renderStorefrontScratchCard(panel, result);
                    }
                } else if (failed) {
                    window.clearTimeout(state.pollTimer);
                    message.textContent = payload.message || 'Выдача требует проверки. Поддержка проверит заказ или оформит возврат.';
                    message.style.background = '#fef2f2';
                    message.style.borderColor = '#f87171';
                    if (hint) hint.textContent = 'Обратитесь в поддержку для ручной выдачи или возврата.';
                } else {
                    message.textContent = payload.message || 'Платеж подтвержден. Готовим выдачу заказа.';
                    if (hint) hint.textContent = 'Выдача обновится автоматически, когда код будет готов.';
                }
            };

            const schedulePoll = () => {
                window.clearTimeout(state.pollTimer);

                if (state.pollCount >= state.maxPolls) {
                    if (hint) hint.textContent = 'Статус можно обновить вручную или открыть заказ в личном сейфе по ссылке.';
                    return;
                }

                state.pollTimer = window.setTimeout(fetchStatus, 3000);
            };

            const fetchStatus = async () => {
                state.pollCount += 1;

                try {
                    const response = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload.message || 'Не удалось обновить выдачу.');
                    }

                    renderStatus(payload);

                    if (!payload.ready && !payload.failed) {
                        schedulePoll();
                    }
                } catch (error) {
                    if (hint) hint.textContent = error.message || 'Не удалось обновить статус. Попробуйте еще раз.';
                    schedulePoll();
                }
            };

            refreshButton.addEventListener('click', () => {
                window.clearTimeout(state.pollTimer);
                state.pollCount = 0;
                if (hint) hint.textContent = 'Обновляем статус выдачи...';
                fetchStatus();
            });

            renderStatus({
                status: result.safe_status,
                paid: true,
                ready: false,
                message: 'Платеж подтвержден. Готовим выдачу заказа.',
                order_id: result.order_id,
            });
            fetchStatus();

            return true;
        };

    </script>
</body>
</html>
