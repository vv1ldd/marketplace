<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('storefront.checkout_success.title') }}</title>
    <style>
        body { margin: 0; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #080b12; color: #f8fafc; }
        .wrap { width: min(820px, calc(100% - 32px)); margin: 0 auto; padding: 48px 0; }
        .card { border: 1px solid rgba(148, 163, 184, .18); border-radius: 28px; padding: 30px; background: rgba(15, 23, 42, .72); }
        h1 { margin: 0 0 12px; font-size: clamp(2rem, 5vw, 4rem); letter-spacing: -.05em; }
        .muted { color: #94a3b8; line-height: 1.6; }
        .voucher { display: flex; justify-content: space-between; gap: 12px; align-items: center; border: 1px solid rgba(148, 163, 184, .14); border-radius: 16px; padding: 14px; margin-top: 12px; }
        a { color: #93c5fd; font-weight: 800; }
        code { color: #bbf7d0; }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page">
    <main class="wrap">
        <section class="card">
            <h1>{{ __('storefront.checkout_success.heading') }}</h1>
            <p class="muted">{{ __('storefront.checkout_success.summary', ['order' => $order->order_id, 'amount' => number_format($totalRub, 2, '.', ' ').' ₽', 'shop' => $shop->name]) }}</p>

            @forelse($vouchers as $voucher)
                <div class="voucher">
                    <code>{{ $voucher['code'] }}</code>
                    <a href="{{ $voucher['redeem_url'] }}">{{ __('storefront.checkout_success.open_redeem') }}</a>
                </div>
            @empty
                <p class="muted">{{ __('storefront.checkout_success.stock_reserved') }}</p>
            @endforelse

            <p class="muted">
                <a href="{{ $safeUrl }}">{{ __('storefront.checkout_success.open_safe') }}</a>
                ·
                <a href="{{ route('meanly.storefront.index') }}">{{ __('storefront.checkout_success.back') }}</a>
            </p>
        </section>
    </main>
</body>
</html>
