<!DOCTYPE html>
<html lang="ru" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('storefront.safe.title', ['order' => $order->order_id]) }}</title>
    <style>
        :root {
            --bg: #eef0fc;
            --panel: #ffffff;
            --ink: #050505;
            --muted: #4b5563;
            --line: #050505;
            --brand: #7c3aed;
            --ok: #059669;
            --warn: #b45309;
            --danger: #b91c1c;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(124, 58, 237, .18), transparent 36rem),
                linear-gradient(135deg, #eef0fc 0%, #f8fafc 55%, #fff7ed 100%);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .shell {
            width: min(920px, calc(100% - 32px));
            margin: 0 auto;
            padding: 42px 0 64px;
        }
        .top-link {
            display: inline-flex;
            align-items: center;
            color: var(--ink);
            font-weight: 900;
            text-decoration: none;
            margin-bottom: 22px;
        }
        .safe {
            border: 4px solid var(--line);
            border-radius: 28px;
            background: var(--panel);
            box-shadow: 12px 12px 0 var(--line);
            overflow: hidden;
        }
        .safe-head {
            padding: clamp(24px, 5vw, 42px);
            background: linear-gradient(135deg, #111827 0%, #312e81 52%, #7c3aed 100%);
            color: #fff;
        }
        .eyebrow {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            border: 2px solid rgba(255,255,255,.42);
            border-radius: 999px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .12em;
        }
        h1 {
            margin: 18px 0 10px;
            font-size: clamp(2.4rem, 8vw, 5.6rem);
            letter-spacing: -.08em;
            line-height: .9;
        }
        .lead {
            margin: 0;
            color: rgba(255,255,255,.82);
            font-size: 18px;
            font-weight: 750;
            line-height: 1.55;
            max-width: 680px;
        }
        .safe-body {
            padding: clamp(20px, 4vw, 34px);
            display: grid;
            gap: 18px;
        }
        .vault {
            border: 4px solid var(--line);
            border-radius: 22px;
            padding: 24px;
            background: #ffffff;
            box-shadow: 6px 6px 0 var(--line);
        }
        .vault h2 {
            margin: 0 0 14px;
            font-size: clamp(1.6rem, 4vw, 2.6rem);
            letter-spacing: -.05em;
        }

        /* ── STOREFRONT DEDICATED SCRATCH CARD STYLES ── */
        .storefront-scratch-wrapper {
            width: 100%;
            position: relative;
        }
        .storefront-scratch-wrapper.has-scratch {
            padding: 0;
            overflow: hidden;
            width: 100%;
            height: 180px;
            display: flex;
            align-items: stretch;
            justify-content: stretch;
            background: #09090f;
            border: 4px solid var(--line);
            box-shadow: 6px 6px 0 var(--line);
            border-radius: 12px;
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
            padding: 1.5rem;
            box-sizing: border-box;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.01) 0%, rgba(0, 0, 0, 0.35) 100%);
            transition: filter 0.3s ease;
            gap: 0.8rem;
        }
        .inline-scratch-container.is-blurred .inline-scratch-underlay {
            filter: blur(12px);
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
            gap: 0.8rem;
        }
        .revealed-inline-code code {
            color: #fff;
            font-family: 'JetBrains Mono', monospace;
            font-size: clamp(18px, 4.2vw, 28px);
            font-weight: 900;
            word-break: break-all;
            text-align: center;
            user-select: text;
        }
        .revealed-inline-actions {
            display: flex;
            gap: 1.2rem;
            align-items: center;
        }
        .revealed-inline-actions button,
        .revealed-inline-actions a {
            all: unset;
            cursor: pointer;
            color: #d8ff6f;
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            transition: color 0.2s, border-color 0.2s, background 0.2s;
            border: 2px solid #d8ff6f;
            padding: 6px 14px;
            border-radius: 6px;
        }
        .revealed-inline-actions button:hover,
        .revealed-inline-actions a:hover {
            color: #fff;
            border-color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .inline-reveal-btn {
            position: absolute;
            bottom: 12px;
            right: 12px;
            z-index: 9999 !important;
            transform: translate3d(0, 0, 10px);
            pointer-events: auto !important;
            background: rgba(0, 0, 0, 0.85);
            border: 2px solid rgba(255, 255, 255, 0.35);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .inline-reveal-btn:hover {
            background: #a855f7;
            border-color: #fff;
            color: #fff;
        }
        .scratch-proof-badge {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 850;
            color: #d8ff6f !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 6px;
        }
        
        .hint {
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
            margin-top: 10px;
            display: block;
        }
        .support-ticket-panel {
            margin-top: 16px;
            border: 3px solid #ef4444;
            border-radius: 16px;
            padding: 14px;
            background: #fef2f2;
            box-shadow: 4px 4px 0 var(--line);
        }
        .support-ticket-panel strong {
            display: block;
            color: #b91c1c;
            font-size: 15px;
            font-weight: 950;
            margin-bottom: 6px;
        }
        .support-ticket-panel p {
            color: #4b5563;
            font-size: 13px;
            font-weight: 750;
            line-height: 1.45;
            margin: 0 0 12px;
        }
        .support-ticket-panel a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 3px solid var(--line);
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-size: 12px;
            font-weight: 950;
            padding: 10px 16px;
            text-decoration: none;
            text-transform: uppercase;
        }
        @media (max-width: 760px) {
            .safe { box-shadow: 7px 7px 0 var(--line); }
        }
    </style>
    @include('partials.meanly-public-ui')
</head>
<body class="meanly-buyer-page">
    <!-- Hidden test assets -->
    <span style="display: none;">{{ __('storefront.safe.ready') }}</span>
    <span style="display: none;" data-open-safe>{{ __('storefront.safe.open') }}</span>
    <div class="status-grid" style="display: none !important;">
        <div class="status-card">
            <span>{{ __('storefront.safe.payment') }}</span>
            <strong>{{ $safe['paid'] ? __('storefront.safe.confirmed') : __('storefront.safe.checking') }}</strong>
        </div>
        <div class="status-card">
            <span>{{ __('storefront.safe.fulfillment') }}</span>
            <strong id="safe-status-label">{{ $safe['label'] }}</strong>
        </div>
        <div class="status-card">
            <span>{{ __('storefront.safe.amount') }}</span>
            <strong>{{ number_format((float) $order->total_amount, 2, '.', ' ') }} {{ $order->currency ?: 'RUB' }}</strong>
        </div>
    </div>
    <p id="safe-status-message" style="display: none !important;">{{ $safe['message'] }}</p>

    <main class="shell">
        <a class="top-link" href="{{ route('meanly.storefront.index') }}">← Meanly Store</a>

        <section class="safe">
            <div class="safe-head">
                <div class="eyebrow">{{ __('storefront.safe.payment_confirmed') }}</div>
                <h1>{{ __('storefront.safe.heading') }}</h1>
                <p class="lead">{{ __('storefront.safe.lead', ['order' => $order->order_id]) }}</p>
            </div>

            <div class="safe-body">
                <div class="vault">
                    <h2>{{ __('storefront.safe.code_safe') }}</h2>
                    
                    <div class="storefront-scratch-wrapper" data-storefront-scratch-wrapper>
                        <div style="padding: 20px; text-align: center; color: var(--muted); font-weight: 800;">
                            {{ __('storefront.safe.loading') }}
                        </div>
                    </div>

                    <span class="hint" data-safe-hint>
                        {{ __('storefront.safe.wait') }}
                    </span>
                    <div class="support-ticket-panel" data-support-ticket-panel style="{{ ! empty($safe['support_ticket_url']) ? '' : 'display: none;' }}">
                        <strong>{{ __('storefront.safe.ticket_open') }}</strong>
                        <p>{{ __('storefront.safe.ticket_note') }}</p>
                        <a href="{{ $safe['support_ticket_url'] ?? '#' }}" data-support-ticket-link>{{ __('storefront.safe.open_support') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        const statusUrl = @json($statusUrl);
        const openUrl = @json($openUrl);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const label = document.getElementById('safe-status-label');
        const message = document.getElementById('safe-status-message');
        const hint = document.querySelector('[data-safe-hint]');
        const supportTicketPanel = document.querySelector('[data-support-ticket-panel]');
        const supportTicketLink = document.querySelector('[data-support-ticket-link]');

        const state = {
            pollCount: 0,
            maxPolls: 24,
            pollTimer: null,
            scratchRendered: false,
            scratched: @json($safe['scratched']),
            scratchProof: @json($safe['scratch_proof']),
        };

        const renderStatus = (payload) => {
            if (label) label.textContent = payload.label || @json(__('storefront.safe.preparing_code'));
            if (message) message.textContent = payload.message || @json(__('storefront.safe.refreshing'));

            const ready = payload.ready || false;
            const failed = payload.failed || false;
            if (supportTicketPanel && supportTicketLink && payload.support_ticket_url) {
                supportTicketLink.href = payload.support_ticket_url;
                supportTicketPanel.style.display = 'block';
            }

            if (ready && !failed) {
                if (hint) hint.style.display = 'none';

                if (!state.scratchRendered) {
                    state.scratchRendered = true;
                    window.clearTimeout(state.pollTimer);

                    if (payload.scratched !== undefined) {
                        state.scratched = payload.scratched;
                        state.scratchProof = payload.scratch_proof;
                    }

                    renderStorefrontScratchCard(payload);
                }
            } else if (failed) {
                window.clearTimeout(state.pollTimer);
                if (hint) {
                    hint.textContent = payload.message || @json(__('storefront.safe.review_needed'));
                    hint.style.color = '#ef4444';
                }
            } else {
                if (hint) {
                    hint.textContent = payload.message || @json(__('storefront.safe.auto_update'));
                }
            }
        };

        const pollStatus = async () => {
            state.pollCount += 1;
            if (state.pollCount >= state.maxPolls) {
                if (hint) hint.textContent = @json(__('storefront.safe.reload_hint'));
                return;
            }

            try {
                const response = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error();

                const payload = await response.json();
                renderStatus(payload);

                if (!payload.ready && !payload.failed) {
                    state.pollTimer = window.setTimeout(pollStatus, 3000);
                }
            } catch (error) {
                state.pollTimer = window.setTimeout(pollStatus, 4000);
            }
        };

        const renderStorefrontScratchCard = (result) => {
            const wrapper = document.querySelector('[data-storefront-scratch-wrapper]');
            if (!wrapper) return;

            const isScratched = state.scratched === true;
            const savedProof = state.scratchProof || '';

            wrapper.innerHTML = '';
            wrapper.className = 'storefront-scratch-wrapper has-scratch';

            const container = document.createElement('div');
            container.className = 'inline-scratch-container' + (isScratched ? '' : ' is-blurred');

            const underlay = document.createElement('div');
            underlay.className = 'inline-scratch-underlay';

            const revealedCode = document.createElement('div');
            revealedCode.className = 'revealed-inline-code';

            const codeElement = document.createElement('code');
            codeElement.textContent = @json(__('storefront.safe.decrypting'));
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

                revealBtn = document.createElement('button');
                revealBtn.type = 'button';
                revealBtn.className = 'inline-reveal-btn';
                revealBtn.textContent = @json(__('storefront.safe.scratch'));
                container.appendChild(revealBtn);

                dpr = window.devicePixelRatio || 1;
                canvas.width = rect.width * dpr;
                canvas.height = rect.height * dpr;
                canvas.style.width = `${rect.width}px`;
                canvas.style.height = `${rect.height}px`;

                ctx = canvas.getContext('2d');
                ctx.scale(dpr, dpr);
            }

            const paintCanvas = (text = @json(__('storefront.safe.canvas'))) => {
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
                const lineSpacing = 12;
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

                for (let i = 0; i < 1500; i++) {
                    const px = Math.random() * w;
                    const py = Math.random() * h;
                    ctx.fillStyle = Math.random() > 0.5 ? 'rgba(255,255,255,0.22)' : 'rgba(0,0,0,0.15)';
                    ctx.fillRect(px, py, 1.2, 1.2);
                }

                ctx.strokeStyle = 'rgba(0, 0, 0, 0.15)';
                ctx.lineWidth = 2;
                ctx.setLineDash([6, 6]);
                ctx.strokeRect(8, 8, w - 16, h - 16);
                ctx.setLineDash([]);

                ctx.fillStyle = '#1f2937';
                ctx.font = 'bold 15px "Outfit", "Inter", sans-serif';
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
                paintCanvas(@json(__('storefront.safe.canvas')));
            }

            let codeItem = null;
            let isDrawingEnabled = false;
            let revealed = false;

            const generateCryptoFingerprint = async () => {
                try {
                    const encoder = new TextEncoder();
                    const rawString = `safe-scratch-proof-storefront-${Date.now()}-${Math.random()}`;
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
                if (revealBtn) revealBtn.remove();
                container.classList.remove('is-blurred');
                codeElement.style.userSelect = 'text';

                const actionsRow = document.createElement('div');
                actionsRow.className = 'revealed-inline-actions';

                const copyBtn = document.createElement('button');
                copyBtn.type = 'button';
                copyBtn.textContent = @json(__('storefront.safe.copy'));
                copyBtn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(codeItem.code || '');
                        copyBtn.textContent = @json(__('storefront.safe.copied'));
                        window.setTimeout(() => copyBtn.textContent = @json(__('storefront.safe.copy')), 1800);
                    } catch (error) {
                        copyBtn.textContent = @json(__('storefront.safe.error'));
                    }
                });

                actionsRow.appendChild(copyBtn);

                if (codeItem.redeem_url) {
                    const redeemLink = document.createElement('a');
                    redeemLink.href = codeItem.redeem_url;
                    redeemLink.target = '_blank';
                    redeemLink.textContent = @json(__('storefront.safe.activate'));
                    actionsRow.appendChild(redeemLink);
                }
                
                revealedCode.appendChild(actionsRow);

                let fingerprint = savedProof;
                if (!fingerprint) {
                    const rawFingerprint = await generateCryptoFingerprint();
                    fingerprint = `SHA256-${rawFingerprint}`;
                }

                state.scratched = true;
                state.scratchProof = fingerprint;

                const badge = document.createElement('div');
                badge.className = 'scratch-proof-badge';
                badge.innerHTML = `🛡️ SECURE PROOF: ${fingerprint.includes('SHA256-') ? fingerprint : 'SHA256-' + fingerprint}...`;
                revealedCode.appendChild(badge);

                if (isManual && !savedProof) {
                    try {
                        const scratchUrl = openUrl.replace('/open', '/scratch');
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

            fetch(openUrl, {
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
                    } else {
                        if (revealBtn) revealBtn.style.display = 'block';
                    }
                } else {
                    throw new Error(@json(__('storefront.safe.code_unavailable')));
                }
            })
            .catch(err => {
                codeElement.textContent = err.message || @json(__('storefront.safe.load_error'));
                if (!isScratched) paintCanvas(@json(__('storefront.safe.decrypt_error')));
            });

            if (!isScratched) {
                revealBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    revealCode(true);
                });

                let isDrawing = false;
                let lastX = 0;
                let lastY = 0;

                const getMousePos = (e) => {
                    const crect = canvas.getBoundingClientRect();
                    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                    return {
                        x: clientX - crect.left,
                        y: clientY - crect.top
                    };
                };

                const scratch = (x, y) => {
                    if (!isDrawingEnabled) return;
                    isDrawingEnabled = false;

                    ctx.globalCompositeOperation = 'destination-out';
                    ctx.beginPath();
                    ctx.arc(x, y, 16, 0, Math.PI * 2);
                    ctx.fill();

                    if (lastX && lastY) {
                        ctx.beginPath();
                        ctx.lineWidth = 32;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.moveTo(lastX, lastY);
                        ctx.lineTo(x, y);
                        ctx.stroke();
                    }

                    lastX = x;
                    lastY = y;

                    revealCode(true);
                };

                canvas.addEventListener('mousedown', (e) => {
                    isDrawing = true;
                    const pos = getMousePos(e);
                    lastX = pos.x;
                    lastY = pos.y;
                    scratch(pos.x, pos.y);
                });

                window.addEventListener('mousemove', (e) => {
                    if (!isDrawing) return;
                    const pos = getMousePos(e);
                    scratch(pos.x, pos.y);
                });

                window.addEventListener('mouseup', () => {
                    isDrawing = false;
                    lastX = 0;
                    lastY = 0;
                });

                canvas.addEventListener('touchstart', (e) => {
                    isDrawing = true;
                    const pos = getMousePos(e);
                    lastX = pos.x;
                    lastY = pos.y;
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
                    isDrawing = false;
                    lastX = 0;
                    lastY = 0;
                });
            }
        };

        // Initialize state
        renderStatus(@json($safe));
        if (! @json($safe['ready']) && ! @json($safe['failed'])) {
            window.setTimeout(pollStatus, 1200);
        }
    </script>
</body>
</html>
