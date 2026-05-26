@php
    $checkoutUser = auth()->user();
    $checkoutUserEmail = trim((string) ($checkoutUser?->email ?? ''));
    $giftCheckoutSelected = filter_var(old('is_gift', false), FILTER_VALIDATE_BOOLEAN);
    $buyerRubtBalanceMinor = $checkoutUser
        ? app(\App\Services\BuyerWalletService::class)->balance($checkoutUser)['available_minor']
        : 0;
    $buyerHasPasskey = $checkoutUser ? $checkoutUser->passkeys()->exists() : false;
    $initialCheckoutAvailability = $checkoutAvailability ?? [
        'status' => 'idle',
        'reason' => 'Проверим наличие у продавца перед оплатой.',
    ];
    $initialCheckoutAvailable = ($initialCheckoutAvailability['status'] ?? null) === 'available';
    $simpleL1Identity = session('simple_l1_identity');
    $simpleL1Address = is_array($simpleL1Identity) ? ($simpleL1Identity['l1_address'] ?? null) : null;
@endphp
<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $product->meta_title ?: $product->name }}</title>
    <meta name="description" content="{{ $product->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 155) }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="canonical" href="{{ route('meanly.storefront.products.show', $product->slug) }}">
    <link rel="alternate" type="application/json" href="{{ route('llms.products.show', $product->slug) }}">
    @isset($productJsonLd)
        <script type="application/ld+json">{!! json_encode($productJsonLd, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
    @isset($productFacts)
        <script type="application/json" id="meanly-product-facts">{!! json_encode($productFacts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endisset
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
        .nav {
            position: sticky;
            top: 0;
            z-index: 20;
            background: var(--panel);
            border-bottom: 4px solid var(--line);
            box-shadow: 0 4px 0 rgba(5, 5, 5, .12);
        }
        .nav-inner {
            min-height: 74px;
            display: grid;
            grid-template-columns: minmax(160px, 1fr) auto minmax(180px, 1fr);
            align-items: center;
            gap: 24px;
        }
        .logo { display: inline-flex; align-items: center; gap: 9px; font-weight: 950; letter-spacing: -.05em; }
        .logo-mark { width: 12px; height: 12px; background: var(--brand); border: 2px solid var(--line); box-shadow: 2px 2px 0 var(--line); }
        .nav-links { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 13px; font-weight: 900; }
        .nav-links a,
        .nav-action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 0 12px;
            border: 2px solid transparent;
            border-radius: 3px;
            transition: transform .15s ease, background .15s ease, border-color .15s ease;
        }
        .nav-links a:hover,
        .nav-action-link:hover {
            background: var(--brand-soft);
            border-color: var(--line);
            transform: translate(1px, 1px);
        }
        .nav-actions { display: flex; justify-content: flex-end; align-items: center; gap: 14px; font-size: 13px; font-weight: 900; }
        .nav-action-link {
            background: var(--panel);
            color: var(--ink);
            border-color: var(--line);
            box-shadow: 3px 3px 0 var(--line);
        }
        .nav-action-link:hover { box-shadow: 2px 2px 0 var(--line); }
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
        .simple-l1-panel {
            border: 3px solid var(--line);
            background: #ecfdf5;
            padding: 12px;
            margin-bottom: 14px;
            font-weight: 850;
            color: var(--muted);
            box-shadow: 3px 3px 0 var(--line);
        }
        .simple-l1-panel strong,
        .simple-l1-panel code {
            display: block;
            margin-top: 4px;
            color: var(--ink);
            overflow-wrap: anywhere;
        }
        .simple-l1-panel .btn {
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
        .availability-panel {
            border: 3px solid var(--line);
            background: #eef2ff;
            padding: 12px;
            margin: 14px 0;
            font-weight: 850;
            color: var(--muted);
            box-shadow: 3px 3px 0 var(--line);
        }
        .availability-panel strong {
            display: block;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .availability-panel[data-state="available"] { background: #e7fff2; }
        .availability-panel[data-state="unavailable"],
        .availability-panel[data-state="error"] { background: #ffd7ef; }
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
        .marketplace-footer {
            width: min(1180px, calc(100vw - 32px));
            margin: 0 auto 40px;
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: minmax(260px, 1.2fr) repeat(3, minmax(0, 1fr));
            gap: 24px;
            padding: 26px;
        }
        .footer-logo {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-weight: 950;
            letter-spacing: -.05em;
            margin-bottom: 14px;
        }
        .footer-brand-block p,
        .footer-proof-block p {
            margin: 0;
            color: var(--muted);
            font-weight: 800;
            line-height: 1.45;
        }
        .footer-links-block {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .footer-title {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--brand);
        }
        .footer-links-block a {
            font-weight: 900;
            color: var(--ink);
        }
        .footer-links-block a:hover { color: var(--brand); }
        .footer-bottom {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 14px 26px;
            border-top: 3px solid var(--line);
            background: var(--brand-soft);
            color: var(--muted);
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        @media (max-width: 920px) {
            .nav-inner { grid-template-columns: 1fr; padding: 14px 0; gap: 12px; }
            .nav-links, .nav-actions { justify-content: flex-start; flex-wrap: wrap; }
            .product-layout { grid-template-columns: 1fr; }
            .checkout-panel { position: static; }
            .footer-grid { grid-template-columns: 1fr; }
            .footer-bottom { flex-direction: column; }
        }

        /* Cyberpunk Balance Reconstitution Visualizer Styles */
        .cyber-visualizer {
            display: grid;
            grid-template-columns: 120px 1fr 140px 1fr 120px;
            align-items: center;
            justify-items: center;
            width: 100%;
            min-height: 280px;
            background: #080710;
            padding: 24px;
            border-bottom: 4px solid var(--line);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }
        @media (max-width: 768px) {
            .cyber-visualizer {
                grid-template-columns: 1fr;
                min-height: auto;
                gap: 20px;
                padding: 30px 15px;
            }
            .cyber-connector { display: none !important; }
        }
        .cyber-node {
            border: 3px solid var(--line);
            border-radius: 6px;
            padding: 12px 10px;
            background: #121020;
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            width: 100%;
            text-align: center;
            box-shadow: 4px 4px 0 var(--line);
            position: relative;
            z-index: 2;
        }
        .cyber-node.input-node {
            border-color: #06b6d4;
            box-shadow: 4px 4px 0 #06b6d4;
        }
        .cyber-node.output-node {
            border-color: #a855f7;
            box-shadow: 4px 4px 0 #a855f7;
        }
        .node-tag {
            font-size: 9px;
            font-weight: 900;
            color: #8b9bb4;
            display: block;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .cyber-node strong {
            display: block;
            font-size: 15px;
            font-weight: 900;
            margin: 6px 0;
            color: #fff;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .node-status {
            font-size: 9px;
            font-weight: 900;
            display: block;
            text-transform: uppercase;
            color: #10b981;
            letter-spacing: 0.02em;
        }
        .cyber-connector {
            width: 100%;
            height: 4px;
            background: var(--line);
            position: relative;
            z-index: 1;
        }
        .laser-beam {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 40px;
            background: linear-gradient(90deg, transparent, #06b6d4, transparent);
            animation: laserFlow 1.5s linear infinite;
        }
        .right-connector .laser-beam {
            background: linear-gradient(90deg, transparent, #a855f7, transparent);
        }
        .cyber-core {
            position: relative;
            z-index: 2;
            width: 140px;
            height: 140px;
            display: grid;
            place-items: center;
        }
        .cyber-core img {
            width: 110px;
            height: 110px;
            object-fit: contain;
            border-radius: 6px;
            border: 3px solid var(--line);
            box-shadow: 4px 4px 0 var(--line);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: #1c1930;
        }
        .cyber-core:hover img {
            transform: scale(1.08) rotate(1deg);
        }
        .core-ring {
            position: absolute;
            width: 134px;
            height: 134px;
            border: 2px dashed #a855f7;
            border-radius: 50%;
            animation: spinRing 25s linear infinite;
            pointer-events: none;
            opacity: 0.5;
        }
        
        /* Cyber Telemetry Terminal log in checkout panel */
        .cyber-terminal-log {
            margin-top: 22px;
            border: 3px solid var(--line);
            border-radius: 6px;
            background: #09090f;
            color: #38bdf8;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            box-shadow: 4px 4px 0 var(--line);
            overflow: hidden;
            text-align: left;
        }
        .terminal-header {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--line);
            padding: 8px 12px;
            color: #fff;
        }
        .terminal-header .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }
        .terminal-header .dot.red { background: #ef4444; }
        .terminal-header .dot.yellow { background: #f59e0b; }
        .terminal-header .dot.green { background: #10b981; }
        .terminal-title {
            margin-left: auto;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: 0.08em;
            opacity: 0.8;
            color: #fff;
        }
        .terminal-body {
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 160px;
            overflow-y: auto;
        }
        .log-line {
            line-height: 1.4;
            opacity: 0.85;
            word-break: break-all;
        }
        .log-line.active {
            color: #a78bfa;
            animation: blinkLog 2s infinite;
        }
        .timestamp { color: #52525b; margin-right: 4px; }
        .tag {
            display: inline-block;
            padding: 1px 4px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: 900;
            text-transform: uppercase;
            margin-right: 4px;
            color: #fff;
        }
        .tag.sys { background: #0284c7; }
        .tag.line { background: #7c3aed; }
        .tag.core { background: #d97706; }
        .tag.link { background: #059669; }

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

        @keyframes laserFlow {
            0% { left: -20%; }
            100% { left: 120%; }
        }
        @keyframes spinRing {
            100% { transform: rotate(360deg); }
        }
        @keyframes blinkLog {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    @include('storefront.partials.header')

    <main class="shell">
        <a class="back-link" href="{{ route('meanly.storefront.index') }}">← Назад в маркетплейс</a>

        <section class="product-layout">
            <div class="product-main-column">
                @php
                    $nominalValue = $product->nominal_value;
                    $currency = $product->purchase_currency ?: 'USD';
                    $brandName = $product->brand?->name ?: $product->vendor ?: 'Platform';
                @endphp
                <article class="product-panel">
                    <div class="buyer-product-hero" aria-label="Карточка товара">
                        <div class="buyer-product-image">
                            <img src="{{ $product->getRedeemDisplayImageSrc() }}" alt="{{ $product->name }}">
                        </div>
                        <div class="buyer-product-summary">
                            <div class="eyebrow">{{ $product->category ?? 'Digital goods' }}</div>
                            <h1>{{ $product->name }}</h1>
                            <p>
                                Цифровой код {{ $brandName }}. После оплаты он придет на email и появится в личном сейфе.
                            </p>
                        </div>
                    </div>
                    <div class="buyer-product-copy product-copy">
                        <div class="meta-row">
                            <span class="meta-pill">цифровая выдача</span>
                            @isset($productFacts)
                                <span class="meta-pill soft">{{ $productFacts['canonical_category_label'] ?? $product->category }}</span>
                            @endisset
                            @if($product->market_category_name)
                                <span class="meta-pill soft">{{ $product->market_category_name }}</span>
                            @endif
                            @if($nominalValue > 0)
                                <span class="meta-pill" style="background: #e7fff2; color: #000;">номинал {{ $nominalValue }} {{ $currency }}</span>
                            @endif
                        </div>
                        <div class="buyer-trust-list" aria-label="Что важно знать перед покупкой">
                            <div class="buyer-trust-item">
                                <strong>Доставка</strong>
                                Email и личный сейф после оплаты.
                            </div>
                            <div class="buyer-trust-item">
                                <strong>Проверка</strong>
                                Наличие проверяется перед оплатой.
                            </div>
                            <div class="buyer-trust-item">
                                <strong>Поддержка</strong>
                                Чат по заказу доступен в кабинете.
                            </div>
                        </div>
                    </div>
                </article>

                <section class="description-panel">
                    <h2>Описание</h2>
                    <div class="description">
                        {!! nl2br(e(trim(strip_tags((string) $product->description)) ?: 'Цифровой товар Meanly с выдачей через защищенный checkout и redeem-ссылку.')) !!}
                        
                        @if($nominalValue > 0)
                            <div style="margin-top: 18px; border: 2px solid var(--line); border-radius: 8px; padding: 16px; background: #e7fff2;">
                                <strong style="display: block; font-size: 16px; margin-bottom: 6px;">{{ $brandName }} · {{ $nominalValue }} {{ $currency }}</strong>
                                <p style="margin: 0; font-size: 15px; color: var(--muted); line-height: 1.55;">
                                    Проверьте, что сервис и регион подходят вашему аккаунту перед оплатой.
                                </p>
                            </div>
                        @endif
                    </div>
                </section>
            </div>

            <aside class="checkout-panel" data-checkout-panel>
                <div class="seller">К оплате</div>
                <div class="price">{{ number_format(((float) $product->price_rub) / 100, 2, '.', ' ') }} ₽</div>
                <div class="checkout-note" style="background: #fdf5ff; border-color: #a855f7;">
                    Код придет на email и появится в личном сейфе после оплаты.
                </div>
                <div class="simple-l1-panel" data-simple-l1-panel>
                    <span>Simple L1 identity</span>
                    @if($simpleL1Address)
                        <strong>Кошелек подключен</strong>
                        <code>{{ $simpleL1Address }}</code>
                    @else
                        <strong>Подключите SL1 passkey wallet</strong>
                        <p class="recipient-help" style="margin-bottom: 0;">Подтвердите passkey на api.wildflow.test и вернитесь на Meanly с переносимым sl1 адресом.</p>
                    @endif
                    <a class="btn {{ $simpleL1Address ? 'btn-secondary' : 'btn-primary' }}"
                       href="{{ route('meanly.simple_l1.connect', ['return_to' => request()->getRequestUri()]) }}">
                        {{ $simpleL1Address ? 'Переподключить SL1 wallet' : 'Connect Simple L1 wallet' }}
                    </a>
                </div>
                <form method="POST" action="{{ route('meanly.storefront.checkout') }}" data-gift-checkout>
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="quantity" value="1">
                    <div class="availability-panel" data-availability-panel data-state="{{ $initialCheckoutAvailable ? 'available' : 'unavailable' }}" style="display: none !important;">
                        <strong data-availability-title>{{ $initialCheckoutAvailable ? 'Товар в наличии' : 'Скоро в продаже' }}</strong>
                        <span data-availability-message>{{ $initialCheckoutAvailability['reason'] ?? ($initialCheckoutAvailable ? 'Продавец подготовил код для выдачи после оплаты.' : 'Нет в наличии у продавца.') }}</span>
                    </div>
                    <button class="btn" style="width: 100%; margin-top: 8px; display: none !important;" type="button" data-check-availability>
                        Проверить наличие
                    </button>

                    @if($checkoutUserEmail !== '')
                        <div class="checkout-recipient-summary">
                            Код придет на ваш email
                            <strong>{{ $checkoutUserEmail }}</strong>
                        </div>
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
                            <span>Покупаю в подарок / отправить на другой email</span>
                        </label>
                        <div class="gift-fields">
                            <label for="email">Email получателя подарка</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="укажите адрес доставки подарка" data-gift-email @if($giftCheckoutSelected) required @endif>
                            <label for="name">Имя получателя</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="укажите имя получателя">
                        </div>
                    @else
                        <label for="email">Email для доставки кода</label>
                        <input id="email" name="email" type="email" required value="{{ old('email') }}" placeholder="укажите адрес доставки кода">
                        <label for="name">Имя получателя</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="укажите имя">
                        <p class="recipient-help">Войдите в аккаунт с email, чтобы получать коды без повторного ввода адреса.</p>
                    @endif

                    @if($checkoutUser)
                        <div class="buyer-wallet-secondary">
                            <details>
                                <summary>Оплатить балансом</summary>
                                <div class="checkout-note" style="background: #ecfdf5; border-color: #10b981; margin-top: 12px;">
                                    Баланс: <strong>{{ number_format($buyerRubtBalanceMinor / 100, 2, '.', ' ') }} ₽</strong>.
                                    @if($buyerHasPasskey)
                                        Подтверждение пройдет через Passkey.
                                    @else
                                        Добавьте Passkey в профиле, чтобы оплатить балансом.
                                    @endif
                                </div>
                                <button class="btn btn-secondary" style="width: 100%; margin-top: 8px;" type="button" data-wallet-pay data-passkey-ready="{{ $buyerHasPasskey ? '1' : '0' }}" @disabled(! $buyerHasPasskey || ! $initialCheckoutAvailable)>
                                    Оплатить балансом с Passkey
                                </button>
                                <p class="recipient-help" data-wallet-status aria-live="polite"></p>
                            </details>
                        </div>
                    @endif

                    <button class="btn btn-primary" type="submit" data-submit-checkout @disabled(! $initialCheckoutAvailable)>Купить сейчас</button>
                </form>
                @if($errors->any())
                    <p class="error">{{ $errors->first() }}</p>
                @endif

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
                            <a class="inline-safe-link" href="#" data-inline-safe-link style="text-align: center; text-decoration: underline; text-underline-offset: 4px; padding-top: 10px; display: block;">Открыть в личном сейфе</a>
                        </div>
                        <p class="recipient-help" data-inline-safe-hint aria-live="polite" style="display: none;">
                            Выдача обновится автоматически, когда код будет готов.
                        </p>
                    </div>
                </template>
            </aside>
        </section>
    </main>
    @include('storefront.partials.footer')
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
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

                if (parsed.pathname.includes('/cabinet')) {
                    return null;
                }
            } catch (error) {
                if (String(safeUrl).includes('/cabinet')) {
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

        document.querySelectorAll('[data-gift-checkout]').forEach((form) => {
            const walletButton = form.querySelector('[data-wallet-pay]');
            const status = form.querySelector('[data-wallet-status]');
            const availabilityPanel = form.querySelector('[data-availability-panel]');
            const availabilityTitle = form.querySelector('[data-availability-title]');
            const availabilityMessage = form.querySelector('[data-availability-message]');
            const checkAvailabilityButton = form.querySelector('[data-check-availability]');
            const fulfillmentModeInput = form.querySelector('[data-fulfillment-mode]');
            const quantityInput = form.querySelector('[name="quantity"]');
            const submitButton = form.querySelector('[data-submit-checkout]');

            const setStatus = (message, isError = false) => {
                if (!status) {
                    return;
                }
                status.textContent = message;
                status.style.color = isError ? '#b91c1c' : '#065f46';
            };

            const setAvailability = (state, title, message) => {
                availabilityPanel.dataset.state = state;
                availabilityTitle.textContent = title;
                availabilityMessage.textContent = message;
            };

            const setPaymentEnabled = (isAvailable) => {
                if (submitButton) {
                    submitButton.disabled = !isAvailable;
                }

                if (walletButton) {
                    walletButton.disabled = !isAvailable || walletButton.dataset.passkeyReady !== '1';
                }
            };

            const resetAvailability = () => {
                fulfillmentModeInput.value = 'instant';
                setPaymentEnabled(false);
                setAvailability(
                    'idle',
                    'Проверим наличие перед оплатой',
                    'Проверим seller stock для защищенной выдачи после оплаты.',
                );
            };

            const ensureAvailability = async () => {
                const formData = new FormData(form);
                setPaymentEnabled(false);
                setAvailability('checking', 'Проверяем наличие...', 'Смотрим подготовленный seller stock без резерва и без provider-заказа.');

                const response = await fetch("{{ route('meanly.storefront.checkout.availability') }}", {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': formData.get('_token'),
                    },
                    body: formData,
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload.message || Object.values(payload.errors || {})?.[0]?.[0] || 'Не удалось проверить наличие.');
                }

                if (payload.status === 'available') {
                    fulfillmentModeInput.value = 'instant';
                    setPaymentEnabled(true);
                    setAvailability('available', 'Seller stock готов', payload.reason || 'Продавец подготовил stock для secure exchange после оплаты.');

                    return payload;
                }

                fulfillmentModeInput.value = 'instant';
                setPaymentEnabled(false);
                setAvailability('unavailable', 'Скоро в продаже', payload.reason || 'Нет в наличии у продавца.');
                throw new Error(payload.reason || 'Нет в наличии у продавца.');
            };

            quantityInput?.addEventListener('change', resetAvailability);
            checkAvailabilityButton?.addEventListener('click', async () => {
                checkAvailabilityButton.disabled = true;
                try {
                    await ensureAvailability();
                } catch (error) {
                    setAvailability('error', 'Проверьте условия', error.message || 'Проверка не завершена.');
                } finally {
                    checkAvailabilityButton.disabled = false;
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                const submitter = event.submitter;
                if (submitter) {
                    submitter.disabled = true;
                }

                try {
                    await ensureAvailability();
                    HTMLFormElement.prototype.submit.call(form);
                } catch (error) {
                    setAvailability('error', 'Оплата заблокирована', error.message || 'Сначала подтвердите доступный способ выдачи.');
                    setPaymentEnabled(false);
                }
            });

            if (!walletButton || !status) {
                return;
            }

            walletButton.addEventListener('click', async () => {
                if (!window.SimpleWebAuthnBrowser || !window.PublicKeyCredential) {
                    setStatus('Ваш браузер не поддерживает Passkey/WebAuthn для RUBT оплаты.', true);
                    return;
                }

                walletButton.disabled = true;
                setStatus('Собираем SL1-транзакцию для подписи...');

                try {
                    await ensureAvailability();
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
                        throw new Error(options.message || Object.values(options.errors || {})?.[0]?.[0] || 'Не удалось подготовить RUBT оплату.');
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

                    setStatus('Проверяем подпись и списываем RUBT...');
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
                        throw new Error(result.message || Object.values(result.errors || {})?.[0]?.[0] || 'RUBT оплата отклонена.');
                    }

                    setStatus('Оплата подтверждена. Показываем выдачу заказа на этой странице...');

                    let renderedInlineSafe = false;

                    try {
                        renderedInlineSafe = renderInlineOrderSafe(result, formData.get('_token'));
                    } catch (safeError) {
                        if (renderStandaloneSafeFallback(result, safeError.message || 'Не удалось встроить выдачу заказа на страницу.')) {
                            return;
                        }

                        throw safeError;
                    }

                    if (!renderedInlineSafe) {
                        const renderedFallback = renderStandaloneSafeFallback(
                            result,
                            'Заказ оплачен, но встроенная выдача не получила inline endpoint. Откройте отдельную защищенную страницу заказа.',
                        );

                        if (!renderedFallback) {
                            throw new Error('Выдача заказа создана, но ссылка не вернулась.');
                        }
                    }
                } catch (error) {
                    setStatus(error.message || 'RUBT оплата не завершена.', true);
                    setPaymentEnabled(false);
                }
            });
        });

        const recentStorageKey = 'meanly_marketplace_recently_viewed';
        const currentProduct = {!! \Illuminate\Support\Js::from([
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'category' => $product->category,
        ]) !!};

        try {
            const recent = JSON.parse(localStorage.getItem(recentStorageKey) || '[]')
                .filter(item => Number(item.id) !== Number(currentProduct.id));
            recent.unshift(currentProduct);
            localStorage.setItem(recentStorageKey, JSON.stringify(recent.slice(0, 12)));
        } catch (e) {
            localStorage.setItem(recentStorageKey, JSON.stringify([currentProduct]));
        }
    </script>
</body>
</html>
