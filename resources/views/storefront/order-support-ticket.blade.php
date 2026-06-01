<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('storefront.support.title', ['order' => $order->order_id]) }}</title>
    <style>
        :root {
            --bg: #eef2ff;
            --line: #050505;
            --brand: #7c3aed;
            --brand-soft: #ede9fe;
            --text: #111827;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: "Inter", "Outfit", system-ui, sans-serif;
        }
        .shell {
            width: min(920px, calc(100% - 32px));
            margin: 32px auto 120px;
        }
        .top-link {
            display: inline-flex;
            color: var(--text);
            font-size: 13px;
            font-weight: 950;
            margin-bottom: 18px;
            text-decoration: none;
            text-transform: uppercase;
        }
        .hero {
            background: #fff;
            border: 4px solid var(--line);
            border-radius: 24px;
            box-shadow: 10px 10px 0 var(--line);
            padding: clamp(22px, 5vw, 38px);
        }
        .badge {
            display: inline-flex;
            border: 3px solid var(--line);
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 950;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h1 {
            max-width: 760px;
            margin: 18px 0 12px;
            font-size: clamp(2.4rem, 7vw, 5.6rem);
            line-height: .86;
            letter-spacing: -.08em;
        }
        .lead {
            color: var(--muted);
            max-width: 680px;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.5;
            margin: 0;
        }
        .ai-chat-trigger {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 998;
            border: 4px solid var(--line);
            border-radius: 999px;
            background: #d8ff6f;
            color: var(--line);
            box-shadow: 6px 6px 0 var(--line);
            padding: 12px 18px;
            font-size: 14px;
            font-weight: 950;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .ai-chat-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,.22);
            z-index: 999;
            display: none;
            backdrop-filter: blur(2px);
        }
        .ai-chat-drawer {
            position: fixed;
            top: 0;
            right: -430px;
            width: 410px;
            max-width: 100%;
            height: 100vh;
            background: #fff;
            border-left: 5px solid var(--line);
            box-shadow: -10px 0 0 rgba(0,0,0,.16);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: right .3s cubic-bezier(.16,1,.3,1);
        }
        .ai-chat-drawer.open { right: 0; }
        .ai-chat-header {
            background: var(--brand);
            color: #fff;
            border-bottom: 4px solid var(--line);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .ai-chat-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 950;
            letter-spacing: -.03em;
        }
        .ai-chat-header span {
            display: block;
            font-size: 10px;
            font-weight: 850;
            letter-spacing: .05em;
            opacity: .88;
            text-transform: uppercase;
        }
        .ai-chat-close {
            background: #fff;
            color: var(--line);
            border: 3px solid var(--line);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 22px;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 2px 2px 0 var(--line);
        }
        .ai-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f8fafc;
        }
        .ai-chat-bubble {
            max-width: 88%;
            padding: 12px 16px;
            border: 3px solid var(--line);
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.45;
            font-weight: 750;
            box-shadow: 4px 4px 0 var(--line);
            white-space: pre-wrap;
        }
        .ai-chat-bubble.assistant {
            background: #fff;
            align-self: flex-start;
        }
        .ai-chat-bubble.user {
            background: var(--brand-soft);
            align-self: flex-end;
        }
        .ai-chat-bubble.error {
            background: #fee2e2;
            border-color: #ef4444;
            align-self: flex-start;
            box-shadow: 4px 4px 0 #ef4444;
        }
        .chat-meta {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 950;
            letter-spacing: .06em;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .ai-chat-footer {
            border-top: 4px solid var(--line);
            padding: 16px;
            background: #fff;
        }
        .ai-chat-input-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 8px;
        }
        .ai-chat-input-wrapper input {
            border: 3px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            font-weight: 850;
            outline: none;
            background: var(--bg);
        }
        .ai-chat-input-wrapper button {
            border: 3px solid var(--line);
            background: var(--brand);
            color: #fff;
            border-radius: 8px;
            width: 48px;
            font-weight: 950;
            cursor: pointer;
            box-shadow: 3px 3px 0 var(--line);
        }
        @media (max-width: 480px) {
            .ai-chat-drawer {
                width: 100%;
                right: -100%;
                border-left: none;
            }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page">
    <main class="shell">
        <a class="top-link" href="{{ $safeUrl }}">← {{ __('storefront.support.back_safe') }}</a>
        <section class="hero">
            <span class="badge">{{ __('storefront.support.ticket_badge', ['id' => $ticket->id, 'status' => $ticket->status]) }}</span>
            <h1>{{ __('storefront.support.heading') }}</h1>
            <p class="lead">
                {{ __('storefront.support.lead', ['order' => $order->order_id]) }}
            </p>
        </section>
    </main>

    <div id="aiChatOverlay" class="ai-chat-overlay"></div>

    <button id="aiChatTrigger" class="ai-chat-trigger" type="button" aria-label="{{ __('storefront.support.chat_label') }}">
        <span>💬</span>
        <span>{{ __('storefront.support.chat_label') }}</span>
    </button>

    <div id="aiChatDrawer" class="ai-chat-drawer">
        <div class="ai-chat-header">
            <div>
                <h3>{{ __('storefront.support.heading_short') }}</h3>
                <span>{{ __('storefront.support.order_ticket', ['order' => $order->order_id, 'ticket' => $ticket->id]) }}</span>
            </div>
            <button id="aiChatClose" class="ai-chat-close" title="{{ __('storefront.support.close') }}">&times;</button>
        </div>
        <div id="aiChatMessages" class="ai-chat-messages"></div>
        <div class="ai-chat-footer">
            <form id="aiChatForm">
                <div class="ai-chat-input-wrapper">
                    <input type="text" id="aiChatInput" placeholder="{{ __('storefront.support.placeholder') }}" autocomplete="off">
                    <button type="submit" aria-label="{{ __('storefront.support.send') }}">➤</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const chatTrigger = document.getElementById('aiChatTrigger');
        const chatClose = document.getElementById('aiChatClose');
        const chatDrawer = document.getElementById('aiChatDrawer');
        const chatOverlay = document.getElementById('aiChatOverlay');
        const chatMessages = document.getElementById('aiChatMessages');
        const chatForm = document.getElementById('aiChatForm');
        const chatInput = document.getElementById('aiChatInput');
        const replyUrl = @json($replyUrl);
        const messagesUrl = @json($messagesUrl);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        let renderedMessageIds = new Set();
        let isSending = false;

        function openDrawer() {
            chatDrawer.classList.add('open');
            chatOverlay.style.display = 'block';
            chatInput.focus();
        }

        function closeDrawer() {
            chatDrawer.classList.remove('open');
            chatOverlay.style.display = 'none';
        }

        function escapeHtml(text) {
            return String(text || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        function appendBubble(message) {
            if (renderedMessageIds.has(message.id)) return;
            renderedMessageIds.add(message.id);

            const bubble = document.createElement('div');
            bubble.classList.add('ai-chat-bubble', message.role === 'assistant' ? 'assistant' : 'user');
            bubble.innerHTML = `<span class="chat-meta">${escapeHtml(message.author)} · ${escapeHtml(message.created_at || '')}</span>${escapeHtml(message.message).replace(/\n/g, '<br>')}`;
            chatMessages.appendChild(bubble);
            scrollToBottom();
        }

        function appendError(text) {
            const bubble = document.createElement('div');
            bubble.classList.add('ai-chat-bubble', 'error');
            bubble.textContent = text;
            chatMessages.appendChild(bubble);
            scrollToBottom();
        }

        async function loadMessages() {
            try {
                const response = await fetch(messagesUrl, { headers: { 'Accept': 'application/json' } });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.error || @json(__('storefront.support.update_failed')));
                data.messages.forEach(appendBubble);
            } catch (error) {
                // Keep polling quiet after first render; the next tick can recover.
            }
        }

        async function sendMessage(text) {
            if (isSending || !text.trim()) return;
            isSending = true;
            chatInput.value = '';

            try {
                const response = await fetch(replyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ message: text }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.error || @json(__('storefront.support.send_failed')));
                data.messages.forEach(appendBubble);
            } catch (error) {
                appendError(error.message || @json(__('storefront.support.network_error')));
            } finally {
                isSending = false;
            }
        }

        chatTrigger.addEventListener('click', openDrawer);
        chatClose.addEventListener('click', closeDrawer);
        chatOverlay.addEventListener('click', closeDrawer);
        chatForm.addEventListener('submit', (event) => {
            event.preventDefault();
            sendMessage(chatInput.value);
        });

        loadMessages().then(openDrawer);
        window.setInterval(loadMessages, 5000);
    </script>
</body>
</html>
