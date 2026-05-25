<style>
    body.meanly-buyer-page {
        --buyer-font-sans: "Outfit", ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        --buyer-font-mono: "JetBrains Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        --buyer-display-weight: 900;
        --buyer-strong-weight: 800;
        --buyer-label-weight: 700;
        --buyer-body-weight: 500;
        --buyer-tight-tracking: -0.025em;
        --buyer-label-tracking: 0.035em;
        --buyer-copy-leading: 1.5;
        --buyer-line: var(--line, #050505);
        --buyer-ink: var(--ink, #050505);
        --buyer-muted: var(--muted, #4b5563);
        --buyer-panel: var(--panel, #ffffff);
        font-family: var(--buyer-font-sans);
        font-weight: var(--buyer-body-weight);
        letter-spacing: 0;
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    body.meanly-buyer-page :is(h1, h2, h3, .price, .product-title, .logo, .footer-logo) {
        font-weight: var(--buyer-display-weight) !important;
        letter-spacing: var(--buyer-tight-tracking) !important;
        line-height: 1.05 !important;
    }

    body.meanly-buyer-page :is(p, .description, .checkout-note, .recipient-help, .lead, .muted) {
        font-weight: var(--buyer-body-weight) !important;
        line-height: var(--buyer-copy-leading) !important;
        letter-spacing: 0 !important;
    }

    body.meanly-buyer-page :is(
        .eyebrow,
        .meta-pill,
        .tag,
        label,
        .seller,
        .back-link,
        .footer-title,
        .side-label,
        .tile-caption span,
        .mini span,
        .inline-safe-link,
        .revealed-inline-actions button,
        .revealed-inline-actions a,
        .scratch-proof-badge
    ) {
        font-family: var(--buyer-font-mono) !important;
        font-weight: var(--buyer-label-weight) !important;
        letter-spacing: var(--buyer-label-tracking) !important;
    }

    body.meanly-buyer-page :is(.btn, .btn-nav-cta, .btn-nav-login, .nav-links a, input, select, textarea) {
        font-weight: var(--buyer-strong-weight) !important;
        letter-spacing: 0 !important;
    }

    body.meanly-buyer-page :is(strong, b, .meta-pill, .checkout-recipient-summary strong, .mini strong) {
        font-weight: var(--buyer-strong-weight) !important;
    }

    body.meanly-buyer-page code,
    body.meanly-buyer-page pre,
    body.meanly-buyer-page .revealed-inline-code code,
    body.meanly-buyer-page .inline-safe-code code {
        font-family: var(--buyer-font-mono) !important;
        font-weight: 700 !important;
        letter-spacing: 0 !important;
    }

    body.meanly-buyer-page .btn {
        border-radius: 8px !important;
        min-height: 46px;
    }

    body.meanly-buyer-page .meanly-standard-header .nav-links a[href*="/business"],
    body.meanly-buyer-page .meanly-standard-header .nav-actions .btn-nav-cta[href*="/business"] {
        display: none !important;
    }

    body.meanly-buyer-page .product-panel,
    body.meanly-buyer-page .checkout-panel,
    body.meanly-buyer-page .description-panel,
    body.meanly-buyer-page .card {
        border-width: 3px !important;
        box-shadow: 6px 6px 0 var(--buyer-line) !important;
    }

    body.meanly-buyer-page .checkout-note {
        border-width: 2px !important;
        box-shadow: none !important;
    }

    body.meanly-buyer-page .meta-pill,
    body.meanly-buyer-page .eyebrow {
        border-width: 2px !important;
        box-shadow: 2px 2px 0 var(--buyer-line) !important;
    }

    body.meanly-buyer-page .tag {
        border-width: 2px !important;
        box-shadow: 2px 2px 0 var(--buyer-line) !important;
        color: #050505 !important;
    }

    body.meanly-buyer-page .buyer-product-hero {
        display: grid;
        grid-template-columns: 190px minmax(0, 1fr);
        gap: 24px;
        align-items: center;
        padding: 24px;
        border-bottom: 3px solid var(--buyer-line);
        background: linear-gradient(135deg, #151225 0%, #211a35 52%, #0e1726 100%);
    }

    body.meanly-buyer-page .buyer-product-image {
        border: 3px solid var(--buyer-line);
        border-radius: 16px;
        overflow: hidden;
        background: #111827;
        box-shadow: 5px 5px 0 var(--buyer-line);
    }

    body.meanly-buyer-page .buyer-product-image img {
        display: block;
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: contain;
    }

    body.meanly-buyer-page .buyer-product-summary {
        color: #ffffff;
    }

    body.meanly-buyer-page .buyer-product-summary h1 {
        max-width: 720px;
        font-size: clamp(36px, 5vw, 62px) !important;
        margin: 0;
    }

    body.meanly-buyer-page .buyer-product-summary p {
        max-width: 620px;
        margin: 14px 0 0;
        color: rgba(255, 255, 255, 0.78);
        font-size: 16px;
    }

    body.meanly-buyer-page .buyer-seller-line {
        display: inline-flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 10px;
        margin-top: 16px;
        padding: 9px 12px;
        border: 2px solid #ffffff;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.82);
        font-size: 13px;
        font-weight: 750;
    }

    body.meanly-buyer-page .buyer-seller-line strong {
        color: #ffffff;
        font-weight: var(--buyer-strong-weight);
    }

    body.meanly-buyer-page .buyer-seller-line em {
        color: rgba(255, 255, 255, 0.7);
        font-style: normal;
    }

    body.meanly-buyer-page .buyer-product-copy {
        padding: 22px 24px 24px;
    }

    body.meanly-buyer-page .buyer-trust-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 18px;
    }

    body.meanly-buyer-page .buyer-trust-item {
        border: 2px solid var(--buyer-line);
        border-radius: 8px;
        background: #f8fafc;
        padding: 12px;
        font-size: 13px;
        color: var(--buyer-muted);
    }

    body.meanly-buyer-page .buyer-trust-item strong {
        display: block;
        margin-bottom: 3px;
        color: var(--buyer-ink);
    }

    body.meanly-buyer-page .buyer-wallet-secondary {
        margin-top: 14px;
        border-top: 2px dashed rgba(5, 5, 5, 0.24);
        padding-top: 14px;
    }

    body.meanly-buyer-page .buyer-secondary-section {
        margin-top: 18px;
    }

    body.meanly-buyer-page .buyer-secondary-section details {
        border: 2px solid var(--buyer-line);
        border-radius: 8px;
        background: #f8fafc;
        padding: 12px;
    }

    body.meanly-buyer-page .buyer-secondary-section summary {
        cursor: pointer;
        font-weight: var(--buyer-strong-weight);
    }

    @media (max-width: 760px) {
        body.meanly-buyer-page .product-layout {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
            padding-bottom: 36px !important;
        }

        body.meanly-buyer-page .product-main-column {
            display: contents !important;
        }

        body.meanly-buyer-page .product-panel {
            order: 1;
            border-bottom-left-radius: 0 !important;
            border-bottom-right-radius: 0 !important;
            box-shadow: 6px 0 0 var(--buyer-line) !important;
        }

        body.meanly-buyer-page .checkout-panel {
            order: 2;
            position: static !important;
            border-top: 0 !important;
            border-top-left-radius: 0 !important;
            border-top-right-radius: 0 !important;
            box-shadow: 6px 6px 0 var(--buyer-line) !important;
            padding: 12px 14px 14px !important;
        }

        body.meanly-buyer-page .description-panel {
            order: 3;
            margin-top: 24px !important;
        }

        body.meanly-buyer-page .buyer-product-hero {
            grid-template-columns: 92px minmax(0, 1fr);
            gap: 14px;
            padding: 14px;
            align-items: center;
        }

        body.meanly-buyer-page .buyer-product-image {
            width: 92px;
            border-radius: 10px;
            box-shadow: 3px 3px 0 var(--buyer-line);
        }

        body.meanly-buyer-page .buyer-product-summary .eyebrow {
            margin-bottom: 8px;
            padding: 5px 7px;
            font-size: 8px;
            box-shadow: 2px 2px 0 var(--buyer-line);
        }

        body.meanly-buyer-page .buyer-product-summary h1 {
            font-size: clamp(24px, 8vw, 36px) !important;
            line-height: .92;
            letter-spacing: -.06em;
        }

        body.meanly-buyer-page .buyer-product-summary p {
            display: none;
        }

        body.meanly-buyer-page .buyer-seller-line {
            margin-top: 10px;
            padding: 6px 8px;
            font-size: 11px;
        }

        body.meanly-buyer-page .buyer-product-copy {
            padding: 10px 14px 12px;
        }

        body.meanly-buyer-page .buyer-product-copy .meta-row {
            margin-top: 0;
            gap: 7px;
        }

        body.meanly-buyer-page .buyer-product-copy .meta-pill {
            padding: 5px 7px;
            font-size: 10px;
        }

        body.meanly-buyer-page .buyer-trust-list {
            display: none;
        }

        body.meanly-buyer-page .checkout-panel .seller {
            font-size: 11px !important;
            margin-bottom: 4px !important;
        }

        body.meanly-buyer-page .checkout-panel .price {
            margin: 4px 0 10px !important;
            font-size: clamp(32px, 10vw, 44px) !important;
        }

        body.meanly-buyer-page .checkout-panel .checkout-note {
            padding: 8px !important;
            margin-bottom: 10px !important;
            font-size: 12px !important;
            line-height: 1.35 !important;
        }

        body.meanly-buyer-page .checkout-panel label {
            margin: 8px 0 5px !important;
            font-size: 10px !important;
        }

        body.meanly-buyer-page .checkout-panel input {
            min-height: 40px !important;
        }

        body.meanly-buyer-page .checkout-panel .gift-toggle {
            min-height: 40px !important;
            padding: 8px 10px !important;
        }

        body.meanly-buyer-page .checkout-panel .buyer-wallet-secondary {
            margin-top: 10px !important;
            padding-top: 10px !important;
        }

        body.meanly-buyer-page .checkout-panel .btn {
            min-height: 44px !important;
            padding: 10px 12px !important;
        }
    }

    html[data-theme] body.meanly-buyer-page :is(
        .eyebrow,
        .meta-pill,
        .tag,
        label,
        .seller,
        .back-link,
        .footer-title,
        .side-label,
        .tile-caption span,
        .mini span,
        .inline-safe-link
    ) {
        letter-spacing: var(--buyer-label-tracking) !important;
        text-transform: uppercase !important;
        font-weight: var(--buyer-label-weight) !important;
    }

    html[data-theme] body.meanly-buyer-page :is(.btn, .btn-nav-cta, .btn-nav-login, .nav-links a) {
        font-family: var(--buyer-font-sans) !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
        font-weight: var(--buyer-strong-weight) !important;
    }

    html[data-theme] body.meanly-buyer-page :is(h1, h2, h3, .price, .logo, .footer-logo) {
        letter-spacing: var(--buyer-tight-tracking) !important;
        font-weight: var(--buyer-display-weight) !important;
    }
</style>
@once
    @if(\Illuminate\Support\Facades\Route::has('meanly.analytics.events.store'))
        <script>
            (() => {
                const endpoint = @json(route('meanly.analytics.events.store'));
                const csrfToken = @json(csrf_token());
                const startedAt = performance.now();
                const storageKey = 'meanly_analytics_visitor_id';

                const visitorId = (() => {
                    try {
                        const existing = window.localStorage.getItem(storageKey);
                        if (existing) return existing;

                        const fresh = (window.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`);
                        window.localStorage.setItem(storageKey, fresh);

                        return fresh;
                    } catch (error) {
                        return `${Date.now()}-${Math.random()}`;
                    }
                })();

                const safePath = (value) => {
                    try {
                        return value ? new URL(value, window.location.origin).pathname : null;
                    } catch (error) {
                        return null;
                    }
                };

                const send = (eventName, metadata = {}, attributes = {}) => {
                    const payload = {
                        _token: csrfToken,
                        event_name: eventName,
                        event_type: attributes.event_type || 'client',
                        surface: attributes.surface || 'storefront',
                        severity: attributes.severity || 'info',
                        duration_ms: Math.max(0, Math.round(performance.now() - startedAt)),
                        visitor_id: visitorId,
                        metadata: {
                            path: window.location.pathname,
                            page_title: document.title,
                            viewport: `${window.innerWidth}x${window.innerHeight}`,
                            ...metadata,
                        },
                    };

                    const body = JSON.stringify(payload);

                    if (navigator.sendBeacon) {
                        navigator.sendBeacon(endpoint, new Blob([body], { type: 'application/json' }));
                        return;
                    }

                    fetch(endpoint, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body,
                        keepalive: true,
                    }).catch(() => {});
                };

                document.addEventListener('DOMContentLoaded', () => {
                    send('page.ready');
                }, { once: true });

                window.addEventListener('load', () => {
                    send('page.loaded', {
                        load_ms: Math.round(performance.now()),
                    });
                }, { once: true });

                document.addEventListener('click', (event) => {
                    const target = event.target instanceof Element
                        ? event.target.closest('[data-analytics-event], a, button, [role="button"], input[type="submit"]')
                        : null;

                    if (!target) return;

                    const label = (target.getAttribute('data-analytics-label') || target.textContent || target.getAttribute('aria-label') || '')
                        .replace(/\s+/g, ' ')
                        .trim()
                        .slice(0, 120);

                    send(target.getAttribute('data-analytics-event') || 'ui.click', {
                        label,
                        tag: target.tagName.toLowerCase(),
                        href_path: safePath(target.getAttribute('href')),
                        id: target.id || null,
                        classes: String(target.className || '').slice(0, 160),
                    });
                }, { passive: true });

                window.addEventListener('error', (event) => {
                    send('client.error', {
                        message: String(event.message || '').slice(0, 300),
                        source_path: safePath(event.filename || ''),
                        line: event.lineno || null,
                        column: event.colno || null,
                    }, { event_type: 'error', severity: 'error' });
                });

                window.addEventListener('unhandledrejection', (event) => {
                    send('client.unhandled_rejection', {
                        message: String(event.reason?.message || event.reason || '').slice(0, 300),
                    }, { event_type: 'error', severity: 'error' });
                });
            })();
        </script>
    @endif
@endonce
