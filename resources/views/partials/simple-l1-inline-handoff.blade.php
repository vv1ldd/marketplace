@once
    <style>
        .sl1-inline-handoff {
            position: fixed;
            inset: 0;
            z-index: 5000;
            display: grid;
            place-items: center;
            padding: 24px;
            background: rgba(238, 241, 255, 0.78);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 180ms ease;
        }
        .sl1-inline-handoff.is-visible {
            opacity: 1;
            pointer-events: auto;
        }
        .sl1-inline-handoff-card {
            width: min(500px, 100%);
            padding: 24px;
            border: 4px solid #050505;
            background: #ffffff;
            color: #050505;
            box-shadow: 10px 10px 0 #050505;
            transform: translateY(10px) scale(0.98);
            transition: transform 180ms ease;
        }
        .sl1-inline-handoff.is-visible .sl1-inline-handoff-card {
            transform: translateY(0) scale(1);
        }
        .sl1-inline-handoff-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            padding: 6px 10px;
            border: 2px solid #050505;
            background: #f7f3ff;
            color: #7c3aed;
            box-shadow: 3px 3px 0 #050505;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .meanly-one-inline-app-icon {
            width: 22px;
            height: 22px;
            border: 2px solid #050505;
            border-radius: 7px;
            box-shadow: 2px 2px 0 #050505;
            display: block;
        }
        .sl1-inline-handoff-title {
            margin: 0 0 10px;
            font-size: clamp(1.9rem, 7vw, 3rem);
            line-height: 0.95;
            letter-spacing: -0.065em;
            font-weight: 950;
        }
        .sl1-inline-handoff-body {
            margin: 0;
            color: #3f4656;
            font-size: 15px;
            font-weight: 750;
            line-height: 1.45;
        }
        .sl1-inline-handoff-facts {
            display: grid;
            gap: 8px;
            margin: 18px 0 0;
        }
        .sl1-inline-handoff-fact {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 2px solid #050505;
            background: #ffffff;
            font-size: 13px;
            font-weight: 850;
        }
        .sl1-inline-handoff-fact::before {
            content: '';
            width: 10px;
            height: 10px;
            border: 2px solid #050505;
            background: #d8ff6f;
            flex: 0 0 auto;
        }
        .sl1-inline-handoff-status {
            color: #5b6272;
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 800;
        }
        .sl1-inline-handoff-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            color: #5b6272;
            margin-top: 18px;
        }
        .sl1-inline-handoff-primary {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 16px;
            border: 3px solid #050505;
            background: #7c3aed;
            color: #ffffff;
            box-shadow: 5px 5px 0 #050505;
            font-size: 13px;
            font-weight: 950;
            text-decoration: none;
        }
        .sl1-inline-handoff-primary:hover {
            transform: translate(2px, 2px);
            box-shadow: 3px 3px 0 #050505;
        }
        .sl1-inline-handoff-countdown {
            font-family: "JetBrains Mono", ui-monospace, monospace;
            font-size: 11px;
            font-weight: 800;
        }
    </style>
    <div class="sl1-inline-handoff" data-sl1-inline-handoff aria-hidden="true">
        <section class="sl1-inline-handoff-card" role="status" aria-live="polite">
            <div class="sl1-inline-handoff-eyebrow"><img class="meanly-one-inline-app-icon" src="{{ asset('meanly-one-app-icon.svg') }}" alt=""> Meanly One app</div>
            <h2 class="sl1-inline-handoff-title" data-sl1-inline-handoff-title>{{ __('auth.simple_l1.inline.title') }}</h2>
            <p class="sl1-inline-handoff-body" data-sl1-inline-handoff-body>{{ __('auth.simple_l1.inline.body') }}</p>
            <div class="sl1-inline-handoff-facts" data-sl1-inline-handoff-facts></div>
            <div class="sl1-inline-handoff-actions">
                <a class="sl1-inline-handoff-primary" href="#" data-sl1-inline-handoff-action>{{ __('auth.simple_l1.inline.cta') }}</a>
                <span class="sl1-inline-handoff-countdown" data-sl1-inline-handoff-status>{{ __('auth.simple_l1.inline.countdown', ['seconds' => 5]) }}</span>
            </div>
        </section>
    </div>
    @php
        $simpleL1HandoffCopy = [
            'title' => __('auth.simple_l1.inline.title'),
            'body' => __('auth.simple_l1.inline.body'),
            'cta' => __('auth.simple_l1.inline.cta'),
            'countdown' => __('auth.simple_l1.inline.countdown', ['seconds' => '__SECONDS__']),
            'redirecting' => __('auth.simple_l1.inline.redirecting'),
        ];
    @endphp
    <script>
        window.meanlySimpleL1HandoffCopy = @json($simpleL1HandoffCopy);
        (() => {
            const initSimpleL1InlineHandoff = () => {
                const overlay = document.querySelector('[data-sl1-inline-handoff]');
                if (!overlay || overlay.dataset.ready === '1') {
                    return;
                }

                overlay.dataset.ready = '1';
                const titleNode = overlay.querySelector('[data-sl1-inline-handoff-title]');
                const bodyNode = overlay.querySelector('[data-sl1-inline-handoff-body]');
                const factsNode = overlay.querySelector('[data-sl1-inline-handoff-facts]');
                const actionNode = overlay.querySelector('[data-sl1-inline-handoff-action]');
                const statusNode = overlay.querySelector('[data-sl1-inline-handoff-status]');
                let redirectTimer = null;
                let countdownTimer = null;

                const isSimpleL1ConnectLink = (link) => {
                    if (!link?.href) {
                        return false;
                    }

                    const url = new URL(link.href, window.location.origin);

                    return url.origin === window.location.origin && url.pathname === '/simple-l1/connect';
                };

                const clearTimers = () => {
                    if (redirectTimer) {
                        window.clearTimeout(redirectTimer);
                    }
                    if (countdownTimer) {
                        window.clearInterval(countdownTimer);
                    }
                    redirectTimer = null;
                    countdownTimer = null;
                };

                const launchWithFallback = (deepLinkUrl, redirectUrl, fallbackMs = 1800) => {
                    if (!deepLinkUrl) {
                        window.location.assign(redirectUrl);
                        return;
                    }

                    let appOpened = false;
                    const markOpened = () => {
                        if (document.hidden) {
                            appOpened = true;
                        }
                    };

                    document.addEventListener('visibilitychange', markOpened, { once: true });
                    window.addEventListener('pagehide', () => {
                        appOpened = true;
                    }, { once: true });

                    window.location.assign(deepLinkUrl);
                    window.setTimeout(() => {
                        if (!appOpened) {
                            window.location.assign(redirectUrl);
                        }
                    }, fallbackMs);
                };

                const showHandoff = (handoff, redirectUrl, deepLinkUrl = null, nativeAutoLaunch = false) => {
                    clearTimers();
                    const copy = window.meanlySimpleL1HandoffCopy || {};
                    titleNode.textContent = handoff?.title || copy.title || 'Meanly One is opening';
                    bodyNode.textContent = handoff?.body || copy.body || 'Approve the identity request, then return to Meanly.';
                    actionNode.textContent = handoff?.cta || copy.cta || 'Continue now';
                    actionNode.href = nativeAutoLaunch && deepLinkUrl ? deepLinkUrl : redirectUrl;
                    factsNode.innerHTML = '';
                    (handoff?.facts || []).forEach((fact) => {
                        const item = document.createElement('div');
                        item.className = 'sl1-inline-handoff-fact';
                        item.textContent = fact;
                        factsNode.appendChild(item);
                    });
                    overlay.setAttribute('aria-hidden', 'false');
                    overlay.classList.add('is-visible');

                    let secondsLeft = 5;
                    statusNode.textContent = (copy.countdown || 'Redirecting in __SECONDS__ seconds...').replace('__SECONDS__', secondsLeft);
                    countdownTimer = window.setInterval(() => {
                        secondsLeft -= 1;
                        statusNode.textContent = secondsLeft > 0
                            ? (copy.countdown || 'Redirecting in __SECONDS__ seconds...').replace('__SECONDS__', secondsLeft)
                            : (copy.redirecting || 'Redirecting...');
                    }, 1000);
                    if (nativeAutoLaunch && deepLinkUrl) {
                        window.setTimeout(() => {
                            window.location.assign(deepLinkUrl);
                        }, 250);
                        return;
                    }
                    redirectTimer = window.setTimeout(() => {
                        window.location.assign(redirectUrl);
                    }, 5000);
                };

                document.addEventListener('click', async (event) => {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    const link = event.target.closest('a');
                    if (!isSimpleL1ConnectLink(link)) {
                        return;
                    }

                    event.preventDefault();

                    try {
                        const response = await fetch(link.href, {
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                        });

                        if (!response.ok) {
                            throw new Error('Meanly One handoff failed.');
                        }

                        const payload = await response.json();
                        if (!payload.show_handoff) {
                            if (payload.native_auto_launch) {
                                window.location.assign(payload.deep_link_url || payload.redirect_url);
                                return;
                            }

                            window.location.assign(payload.redirect_url);
                            return;
                        }

                        showHandoff(payload.handoff, payload.redirect_url, payload.deep_link_url, Boolean(payload.native_auto_launch));
                    } catch (error) {
                        window.location.assign(link.href);
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initSimpleL1InlineHandoff, { once: true });
            } else {
                initSimpleL1InlineHandoff();
            }
        })();
    </script>
@endonce
