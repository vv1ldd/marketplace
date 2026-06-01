@php
    $initialPrompt = trim((string) request('q', ''));
@endphp
<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('storefront.ai.page_title') }}</title>
    <meta name="description" content="{{ __('storefront.ai.meta_description') }}">
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
        html, body { min-height: 100%; overflow-x: hidden; }
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
            overflow-y: hidden;
        }
        a { color: inherit; }
        .ai-shell {
            width: min(920px, calc(100vw - 32px));
            height: 100dvh;
            min-height: 0;
            margin: 0 auto;
            padding: 104px 0 28px;
            display: flex;
            align-items: stretch;
            justify-content: center;
            overflow: hidden;
        }
        .ai-chat-app {
            border: 4px solid var(--line);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: var(--shadow);
        }
        .ai-badge {
            display: inline-flex;
            border: 2px solid var(--line);
            background: var(--brand-soft);
            color: var(--brand);
            box-shadow: 3px 3px 0 var(--line);
            padding: 7px 10px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        h1 {
            margin: 18px 0 10px;
            font-size: clamp(28px, 5vw, 48px);
            line-height: .96;
            letter-spacing: -.055em;
            font-weight: 950;
            text-align: center;
        }
        .lead {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.45;
            font-weight: 750;
            text-align: center;
        }
        .quick-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
        }
        .quick-list button {
            min-height: 36px;
            border: 2px solid var(--line);
            background: #fff;
            box-shadow: 2px 2px 0 var(--line);
            border-radius: 999px;
            padding: 7px 12px;
            text-align: center;
            font: inherit;
            font-size: 12px;
            font-weight: 900;
            cursor: pointer;
        }
        .quick-list button:hover {
            background: #d8ff6f;
            transform: translate(1px, 1px);
            box-shadow: 2px 2px 0 var(--line);
        }
        .ai-chat-app {
            width: 100%;
            height: calc(100dvh - 132px);
            min-height: 0;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            overflow: hidden;
        }
        .ai-chat-app:not(.has-chat) {
            border: 0;
            background: transparent;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ai-chat-app:not(.has-chat) .ai-chat-topbar,
        .ai-chat-app:not(.has-chat) .ai-messages,
        .ai-chat-app:not(.has-chat) .ai-composer {
            display: none;
        }
        .ai-chat-app.has-chat .ai-home {
            display: none;
        }
        .ai-home {
            width: min(660px, 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .ai-chat-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 4px solid var(--line);
            background: linear-gradient(135deg, #151225 0%, #211a35 52%, #0e1726 100%);
            color: #fff;
        }
        .ai-chat-topbar strong {
            display: block;
            font-size: 18px;
            font-weight: 950;
        }
        .ai-chat-topbar span {
            color: rgba(255, 255, 255, .72);
            font-size: 12px;
            font-weight: 800;
        }
        .ai-status {
            border: 2px solid #fff;
            border-radius: 999px;
            padding: 6px 10px;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .ai-messages {
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            scrollbar-gutter: stable;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #f8fafc;
        }
        .ai-messages::-webkit-scrollbar {
            width: 12px;
        }
        .ai-messages::-webkit-scrollbar-track {
            background: #e5e7eb;
            border-left: 3px solid var(--line);
        }
        .ai-messages::-webkit-scrollbar-thumb {
            background: var(--brand);
            border: 3px solid var(--line);
            border-radius: 999px;
        }
        .ai-message {
            width: fit-content;
            max-width: min(720px, 86%);
            border: 3px solid var(--line);
            border-radius: 10px;
            box-shadow: 4px 4px 0 var(--line);
            padding: 13px 15px;
            font-size: 15px;
            font-weight: 750;
            line-height: 1.45;
            white-space: normal;
        }
        .ai-message.assistant {
            align-self: flex-start;
            background: #fff;
        }
        .ai-message.user {
            align-self: flex-end;
            background: var(--brand-soft);
        }
        .ai-message.error {
            align-self: flex-start;
            background: #fee2e2;
            border-color: #ef4444;
            box-shadow: 4px 4px 0 #ef4444;
        }
        .ai-message a {
            display: inline-flex;
            align-items: center;
            margin: 8px 4px 0 0;
            border: 2px solid var(--line);
            background: #d8ff6f;
            box-shadow: 2px 2px 0 var(--line);
            border-radius: 6px;
            padding: 6px 9px;
            font-weight: 950;
            text-decoration: none;
        }
        .ai-product-grid {
            width: min(760px, 92%);
            align-self: flex-start;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }
        .ai-product-card {
            display: grid;
            gap: 8px;
            border: 3px solid var(--line);
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 4px 4px 0 var(--line);
            padding: 12px;
            color: var(--ink);
            text-decoration: none;
            transition: transform .12s ease, box-shadow .12s ease;
        }
        .ai-product-card:hover {
            transform: translate(2px, 2px);
            box-shadow: 2px 2px 0 var(--line);
        }
        .ai-product-card strong {
            font-size: 14px;
            font-weight: 950;
            line-height: 1.15;
        }
        .ai-product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 850;
        }
        .ai-product-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .ai-product-price {
            font-size: 12px;
            font-weight: 950;
        }
        .ai-product-cta {
            border: 2px solid var(--line);
            border-radius: 999px;
            background: #d8ff6f;
            box-shadow: 2px 2px 0 var(--line);
            padding: 5px 8px;
            font-size: 11px;
            font-weight: 950;
            white-space: nowrap;
        }
        .ai-external-grid {
            width: min(760px, 92%);
            align-self: flex-start;
            display: grid;
            gap: 10px;
        }
        .ai-external-card {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: center;
            gap: 12px;
            border: 3px solid var(--line);
            border-radius: 12px;
            background: var(--brand-soft);
            box-shadow: 4px 4px 0 var(--line);
            padding: 12px;
        }
        .ai-external-art {
            width: 54px;
            height: 54px;
            border: 2px solid var(--line);
            border-radius: 12px;
            object-fit: cover;
            background: #fff;
        }
        .ai-external-card strong {
            display: block;
            font-size: 14px;
            font-weight: 950;
            line-height: 1.15;
        }
        .ai-external-card span {
            display: block;
            color: var(--muted);
            font-size: 11px;
            font-weight: 850;
            margin-top: 3px;
        }
        .ai-external-note {
            grid-column: 1 / -1;
            border-top: 2px solid rgba(5, 5, 5, .18);
            padding-top: 8px;
            color: var(--ink) !important;
            font-size: 12px !important;
            line-height: 1.35;
        }
        .ai-external-price {
            border: 2px solid var(--line);
            border-radius: 999px;
            background: #fff;
            box-shadow: 2px 2px 0 var(--line);
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 950;
            white-space: nowrap;
        }
        .ai-typing {
            width: fit-content;
            border: 3px solid var(--line);
            background: #fff;
            box-shadow: 4px 4px 0 var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            font-weight: 900;
        }
        .ai-composer {
            border-top: 4px solid var(--line);
            background: #fff;
            padding: 16px;
        }
        .ai-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            width: 100%;
        }
        .ai-form input {
            min-height: 52px;
            border: 3px solid var(--line);
            border-radius: 8px;
            box-shadow: 3px 3px 0 var(--line);
            padding: 0 14px;
            font: inherit;
            font-size: 15px;
            font-weight: 850;
            outline: none;
        }
        .ai-home .ai-form {
            margin-top: 22px;
        }
        .ai-home .ai-form input {
            min-height: 58px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .92);
        }
        .ai-home .ai-form button {
            min-width: 58px;
            border-radius: 14px;
            font-size: 0;
        }
        .ai-home .ai-form button::before {
            content: "⌕";
            font-size: 24px;
            line-height: 1;
        }
        .ai-form button {
            min-width: 118px;
            min-height: 52px;
            border: 3px solid var(--line);
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            box-shadow: 4px 4px 0 var(--line);
            font: inherit;
            font-weight: 950;
            cursor: pointer;
        }
        .ai-form button:disabled {
            opacity: .55;
            cursor: wait;
        }
        @media (max-width: 860px) {
            body {
                overflow-y: auto;
            }
            .ai-shell {
                width: min(100vw - 20px, 720px);
                height: auto;
                min-height: 100dvh;
                padding-top: 84px;
                overflow: visible;
            }
            .ai-home h1 {
                margin: 12px 0 8px;
                font-size: clamp(30px, 11vw, 42px);
            }
            .lead { font-size: 14px; }
            .quick-list {
                display: flex;
                overflow-x: auto;
                gap: 8px;
                margin-top: 14px;
                padding-bottom: 4px;
            }
            .quick-list button {
                flex: 0 0 auto;
                white-space: nowrap;
            }
            .ai-chat-app {
                height: min(620px, calc(100dvh - 250px));
                min-height: 420px;
            }
            .ai-chat-app:not(.has-chat) {
                height: calc(100dvh - 112px);
                min-height: 520px;
            }
            .ai-messages {
                padding: 14px;
            }
            .ai-message {
                max-width: 92%;
                font-size: 14px;
            }
            .ai-product-grid {
                width: 100%;
                grid-template-columns: 1fr;
            }
            .ai-external-grid {
                width: 100%;
            }
            .ai-external-card {
                grid-template-columns: auto minmax(0, 1fr);
            }
            .ai-external-price {
                grid-column: 1 / -1;
                width: fit-content;
            }
            .ai-composer {
                padding: 12px;
            }
            .ai-form {
                grid-template-columns: 1fr;
            }
            .ai-form button {
                width: 100%;
            }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
@include('storefront.partials.header')

<main class="ai-shell">
    <section class="ai-chat-app" data-ai-app aria-label="Meanly AI chat">
        <div class="ai-home" data-ai-home>
            <span class="ai-badge">Meanly AI</span>
            <h1>{{ __('storefront.ai.heading') }}</h1>
            <p class="lead">
                {{ __('storefront.ai.lead') }}
            </p>
            <form class="ai-form" data-ai-form>
                <input data-ai-input type="text" placeholder="{{ __('storefront.ai.placeholder_short') }}" autocomplete="off" autofocus>
                <button data-ai-submit type="submit">{{ __('storefront.ai.send') }}</button>
            </form>
            <div class="quick-list" aria-label="{{ __('storefront.ai.quick_label') }}">
                <button type="button" data-prompt="{{ __('storefront.ai.prompt_playstation') }}">{{ __('storefront.ai.prompt_playstation_label') }}</button>
                <button type="button" data-prompt="{{ __('storefront.ai.prompt_steam') }}">{{ __('storefront.ai.prompt_steam_label') }}</button>
                <button type="button" data-prompt="{{ __('storefront.ai.prompt_spotify') }}">{{ __('storefront.ai.prompt_spotify_label') }}</button>
                <button type="button" data-prompt="{{ __('storefront.ai.prompt_xbox') }}">Xbox gift card</button>
            </div>
        </div>

        <header class="ai-chat-topbar">
            <div>
                <strong>Meanly AI</strong>
                <span>{{ __('storefront.ai.catalog_helper') }}</span>
            </div>
            <div class="ai-status" data-ai-status>{{ __('storefront.ai.ready') }}</div>
        </header>

        <div class="ai-messages" data-ai-messages>
            <div class="ai-message assistant">
                {{ __('storefront.ai.greeting') }}
            </div>
        </div>

        <footer class="ai-composer">
            <form class="ai-form" data-ai-form data-ai-composer>
                <input data-ai-input type="text" placeholder="{{ __('storefront.ai.placeholder_example') }}" autocomplete="off" autofocus>
                <button data-ai-submit type="submit">{{ __('storefront.ai.send') }}</button>
            </form>
        </footer>
    </section>
</main>

<script>
    (() => {
        const app = document.querySelector('[data-ai-app]');
        const forms = Array.from(document.querySelectorAll('[data-ai-form]'));
        const inputs = Array.from(document.querySelectorAll('[data-ai-input]'));
        const submitButtons = Array.from(document.querySelectorAll('[data-ai-submit]'));
        const composerInput = document.querySelector('[data-ai-composer] [data-ai-input]');
        const homeInput = document.querySelector('[data-ai-home] [data-ai-input]');
        const messages = document.querySelector('[data-ai-messages]');
        const status = document.querySelector('[data-ai-status]');
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const history = [];
        const initialPrompt = @json($initialPrompt);
        let waiting = false;

        const escapeHtml = (text) => String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const parseMarkdown = (text) => {
            let parsed = escapeHtml(text);
            parsed = parsed.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
            parsed = parsed.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');

            return parsed.replace(/\n/g, '<br>');
        };

        const scrollBottom = () => {
            messages.scrollTop = messages.scrollHeight;
        };

        const appendMessage = (role, content) => {
            const bubble = document.createElement('div');
            bubble.className = `ai-message ${role}`;
            bubble.innerHTML = parseMarkdown(content);
            messages.appendChild(bubble);
            scrollBottom();

            return bubble;
        };

        const appendProducts = (products) => {
            if (!Array.isArray(products) || products.length === 0) {
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'ai-product-grid';

            products.slice(0, 6).forEach((product) => {
                const card = document.createElement('a');
                card.className = 'ai-product-card';
                card.href = product.url || '#';
                const groupMeta = product.is_grouped
                    ? `<span>${escapeHtml(product.variant_count || @json(__('storefront.ai.multiple')))} ${@json(__('storefront.ai.variants'))}</span>`
                    : '';
                card.innerHTML = `
                    <strong>${escapeHtml(product.name || @json(__('storefront.ai.product_fallback')))}</strong>
                    <div class="ai-product-meta">
                        <span>${escapeHtml(product.brand || 'Meanly')}</span>
                        <span>${escapeHtml(product.region || 'global')}</span>
                        <span>${escapeHtml(product.category || '')}</span>
                        ${groupMeta}
                    </div>
                    <div class="ai-product-bottom">
                        <span class="ai-product-price">${escapeHtml(product.price || @json(__('storefront.ai.coming_soon')))}</span>
                        <span class="ai-product-cta">${escapeHtml(product.cta || @json(__('storefront.ai.open_product')))}</span>
                    </div>
                `;
                grid.appendChild(card);
            });

            messages.appendChild(grid);
            scrollBottom();
        };

        const appendExternalResults = (results) => {
            if (!Array.isArray(results) || results.length === 0) {
                return;
            }

            const grid = document.createElement('div');
            grid.className = 'ai-external-grid';

            results.slice(0, 2).forEach((item) => {
                const card = document.createElement('div');
                card.className = 'ai-external-card';
                const image = item.artwork_url
                    ? `<img class="ai-external-art" src="${escapeHtml(item.artwork_url)}" alt="">`
                    : '<div class="ai-external-art"></div>';

                card.innerHTML = `
                    ${image}
                    <div>
                        <strong>${escapeHtml(item.name || 'App Store')}</strong>
                        <span>${escapeHtml(item.developer || 'Apple App Store')} · ${escapeHtml(item.country || '')} · ${escapeHtml(item.genre || '')}</span>
                    </div>
                    <div class="ai-external-price">${escapeHtml(item.install_price_label || item.price || 'Price unknown')}</div>
                    <span class="ai-external-note">${escapeHtml(item.monetization_note || @json(__('storefront.ai.external_note')))}</span>
                `;
                grid.appendChild(card);
            });

            messages.appendChild(grid);
            scrollBottom();
        };

        const setWaiting = (value) => {
            waiting = value;
            submitButtons.forEach((button) => {
                button.disabled = value;
            });
            status.textContent = value ? @json(__('storefront.ai.thinking')) : @json(__('storefront.ai.ready'));
        };

        const sendMessage = async (message) => {
            const clean = message.trim();

            if (!clean || waiting) {
                return;
            }

            appendMessage('user', clean);
            app.classList.add('has-chat');
            inputs.forEach((field) => {
                field.value = '';
            });
            setWaiting(true);

            const typing = document.createElement('div');
            typing.className = 'ai-typing';
            typing.textContent = @json(__('storefront.ai.typing'));
            messages.appendChild(typing);
            scrollBottom();

            try {
                const response = await fetch('{{ route('storefront.chat') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token,
                    },
                    body: JSON.stringify({
                        message: clean,
                        history,
                    }),
                });
                const payload = await response.json();
                typing.remove();

                if (!response.ok || !payload.success) {
                    appendMessage('error', payload.error || @json(__('storefront.ai.error_response')));
                    return;
                }

                appendMessage('assistant', payload.response);
                appendExternalResults(payload.external_results);
                appendProducts(payload.products);
                history.push({ role: 'user', content: clean });
                history.push({ role: 'assistant', content: payload.response });

                if (history.length > 10) {
                    history.splice(0, history.length - 10);
                }
            } catch (error) {
                typing.remove();
                appendMessage('error', @json(__('storefront.ai.error_network')));
            } finally {
                setWaiting(false);
                composerInput?.focus();
            }
        };

        forms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const field = form.querySelector('[data-ai-input]');
                sendMessage(field?.value || '');
            });
        });

        document.querySelectorAll('[data-prompt]').forEach((button) => {
            button.addEventListener('click', () => sendMessage(button.dataset.prompt || ''));
        });

        if (initialPrompt) {
            homeInput.value = initialPrompt;
            window.requestAnimationFrame(() => sendMessage(initialPrompt));
        }
    })();
</script>
</body>
</html>
