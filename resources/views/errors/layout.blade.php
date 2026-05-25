<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale() ?: 'ru') }}" data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>@yield('title') | Meanly Systems</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;600;800;900&family=JetBrains+Mono:wght@400;700;800&family=Outfit:wght@500;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --error-primary: #f53003;
            --error-primary-rgb: 245, 48, 3;
            --error-bg: #030303;
            --error-bg-2: #080808;
            --error-card: #0b0b0b;
            --error-card-2: #101010;
            --error-border: rgba(255, 255, 255, 0.08);
            --error-border-strong: rgba(245, 48, 3, 0.28);
            --error-text: #ffffff;
            --error-text-soft: #b9b9b9;
            --error-muted: #666666;
            --error-shadow: 0 24px 80px rgba(0, 0, 0, 0.55);
            --error-radius: 26px;
            --error-font: 'Instrument Sans', sans-serif;
            --error-mono: 'JetBrains Mono', monospace;
        }

        html[data-theme="partner"],
        body[data-theme="partner"] {
            --error-primary: #ff9f0a;
            --error-primary-rgb: 255, 159, 10;
            --error-bg: #070707;
            --error-bg-2: #12100b;
            --error-card: rgba(255, 255, 255, 0.055);
            --error-card-2: rgba(255, 255, 255, 0.035);
            --error-border: rgba(255, 255, 255, 0.10);
            --error-border-strong: rgba(255, 159, 10, 0.34);
            --error-text: #fffaf0;
            --error-text-soft: #d8c7a8;
            --error-muted: #8c7d65;
            --error-shadow: 0 30px 90px rgba(255, 159, 10, 0.08), 0 24px 80px rgba(0, 0, 0, 0.55);
            --error-radius: 30px;
            --error-font: 'Outfit', sans-serif;
        }

        html[data-theme="retro"],
        body[data-theme="retro"] {
            --error-primary: #7c3aed;
            --error-primary-rgb: 124, 58, 237;
            --error-bg: #eef0fc;
            --error-bg-2: #ffffff;
            --error-card: #ffffff;
            --error-card-2: #f8f7ff;
            --error-border: #000000;
            --error-border-strong: #000000;
            --error-text: #000000;
            --error-text-soft: #1f2937;
            --error-muted: #4b5563;
            --error-shadow: 10px 10px 0 #000000;
            --error-radius: 10px;
            --error-font: 'Outfit', sans-serif;
        }

        html[data-theme="nordic"],
        body[data-theme="nordic"] {
            --error-primary: #38bdf8;
            --error-primary-rgb: 56, 189, 248;
            --error-bg: #07111f;
            --error-bg-2: #0e1b2e;
            --error-card: rgba(226, 240, 255, 0.06);
            --error-card-2: rgba(226, 240, 255, 0.035);
            --error-border-strong: rgba(56, 189, 248, 0.32);
        }

        html[data-theme="synthwave"],
        body[data-theme="synthwave"] {
            --error-primary: #ec4899;
            --error-primary-rgb: 236, 72, 153;
            --error-bg: #11051e;
            --error-bg-2: #1f0b35;
            --error-card: rgba(236, 72, 153, 0.07);
            --error-card-2: rgba(99, 102, 241, 0.06);
            --error-border-strong: rgba(236, 72, 153, 0.36);
        }

        html[data-theme="carbon"],
        body[data-theme="carbon"] {
            --error-primary: #94a3b8;
            --error-primary-rgb: 148, 163, 184;
            --error-bg: #020617;
            --error-bg-2: #0f172a;
            --error-card: rgba(148, 163, 184, 0.06);
            --error-card-2: rgba(15, 23, 42, 0.8);
            --error-border-strong: rgba(148, 163, 184, 0.28);
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--error-text);
            background:
                radial-gradient(circle at 18% 12%, rgba(var(--error-primary-rgb), 0.14), transparent 36%),
                radial-gradient(circle at 84% 78%, rgba(var(--error-primary-rgb), 0.08), transparent 34%),
                linear-gradient(135deg, var(--error-bg), var(--error-bg-2));
            font-family: var(--error-font);
            overflow-x: hidden;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        body[data-theme="retro"] {
            background:
                linear-gradient(90deg, rgba(0, 0, 0, 0.035) 1px, transparent 1px),
                linear-gradient(0deg, rgba(0, 0, 0, 0.035) 1px, transparent 1px),
                var(--error-bg);
            background-size: 26px 26px;
        }

        .page-shell {
            position: relative;
            display: grid;
            min-height: 100vh;
            place-items: center;
            padding: 32px 18px;
        }

        .page-shell::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px);
            background-size: 100% 4px;
            opacity: 0.22;
            mix-blend-mode: screen;
        }

        body[data-theme="retro"] .page-shell::before {
            display: none;
        }

        .error-card {
            position: relative;
            width: min(780px, 100%);
            padding: clamp(26px, 5vw, 48px);
            overflow: hidden;
            background:
                linear-gradient(145deg, var(--error-card), var(--error-card-2));
            border: 1px solid var(--error-border);
            border-radius: var(--error-radius);
            box-shadow: var(--error-shadow);
        }

        body[data-theme="partner"] .error-card {
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
        }

        body[data-theme="retro"] .error-card {
            border-width: 3px;
        }

        .topline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: clamp(34px, 7vw, 70px);
        }

        .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            width: 14px;
            height: 14px;
            flex: 0 0 auto;
            background: var(--error-primary);
            border-radius: 4px;
            box-shadow: 0 0 22px rgba(var(--error-primary-rgb), 0.55);
        }

        body[data-theme="retro"] .brand-mark {
            border: 2px solid #000;
            box-shadow: 3px 3px 0 #000;
        }

        .brand-title {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }

        .brand-title strong {
            color: var(--error-text);
            font-size: 14px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .brand-title span {
            color: var(--error-muted);
            font-family: var(--error-mono);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .error-main {
            display: grid;
            grid-template-columns: minmax(0, 0.88fr) minmax(280px, 1.12fr);
            gap: clamp(24px, 5vw, 54px);
            align-items: end;
        }

        .error-code {
            margin: 0;
            color: var(--error-primary);
            font-family: var(--error-mono);
            font-size: clamp(72px, 14vw, 152px);
            font-weight: 900;
            line-height: 0.84;
            letter-spacing: -0.1em;
        }

        body:not([data-theme="retro"]) .error-code {
            text-shadow: 0 0 38px rgba(var(--error-primary-rgb), 0.18);
        }

        .error-kicker {
            margin: 20px 0 0;
            color: var(--error-muted);
            font-family: var(--error-mono);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .error-title {
            margin: 0 0 14px;
            color: var(--error-text);
            font-size: clamp(28px, 5vw, 52px);
            font-weight: 900;
            line-height: 0.96;
            letter-spacing: -0.055em;
        }

        .error-message {
            margin: 0;
            color: var(--error-text-soft);
            font-size: 15px;
            line-height: 1.65;
        }

        .diagnostics {
            display: grid;
            gap: 8px;
            margin: 28px 0 0;
            padding: 16px;
            color: var(--error-muted);
            background: rgba(0, 0, 0, 0.16);
            border: 1px solid var(--error-border);
            border-radius: 16px;
            font-family: var(--error-mono);
            font-size: 11px;
            line-height: 1.45;
        }

        body[data-theme="retro"] .diagnostics {
            background: #f5f3ff;
            border: 2px solid #000;
            border-radius: 8px;
        }

        .diagnostics-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
        }

        .diagnostics-row span:first-child {
            color: var(--error-primary);
            font-weight: 800;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 30px;
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--error-border);
            border-radius: 999px;
            color: var(--error-text);
            background: rgba(255, 255, 255, 0.045);
            text-decoration: none;
            font-size: 13px;
            font-weight: 800;
            transition: transform 0.18s ease, border-color 0.18s ease, background 0.18s ease;
        }

        .action-link.primary {
            color: #fff;
            background: var(--error-primary);
            border-color: var(--error-primary);
        }

        .action-link:hover {
            transform: translateY(-1px);
            border-color: var(--error-border-strong);
        }

        body[data-theme="retro"] .action-link {
            border: 2px solid #000;
            border-radius: 8px;
            color: #000;
            background: #fff;
            box-shadow: 3px 3px 0 #000;
        }

        body[data-theme="retro"] .action-link.primary {
            color: #fff;
            background: var(--error-primary);
        }

        .status-strip {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 16px;
            margin-top: 34px;
            padding-top: 18px;
            border-top: 1px solid var(--error-border);
            color: var(--error-muted);
            font-family: var(--error-mono);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 18px rgba(16, 185, 129, 0.45);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.45; transform: scale(1.25); }
        }

        @media (max-width: 760px) {
            .topline {
                align-items: flex-start;
                flex-direction: column;
            }

            .error-main {
                grid-template-columns: 1fr;
            }

            .diagnostics-row {
                flex-direction: column;
                gap: 3px;
            }
        }
    </style>
</head>
<body data-theme="{{ $currentTheme ?? request()->cookie('theme', config('app.theme_fallback', 'consortium')) }}">
@include('partials.theme-sync-body')
    <main class="page-shell">
        <section class="error-card" aria-labelledby="error-title">
            <div class="topline">
                <div class="brand-lockup">
                    <div class="brand-mark" aria-hidden="true"></div>
                    <div class="brand-title">
                        <strong>Meanly Systems</strong>
                        <span>Service Status</span>
                    </div>
                </div>
            </div>

            <div class="error-main">
                <div>
                    <p class="error-code">@yield('code')</p>
                    <p class="error-kicker">Error State</p>
                </div>

                <div>
                    <h1 class="error-title" id="error-title">@yield('status')</h1>
                    <p class="error-message">@yield('message')</p>

                    <div class="diagnostics" aria-label="Диагностика запроса">
                        <div class="diagnostics-row">
                            <span>TRACE</span>
                            <strong>{{ strtoupper(substr(hash('sha256', request()->fullUrl()), 0, 16)) }}</strong>
                        </div>
                        <div class="diagnostics-row">
                            <span>PATH</span>
                            <strong>{{ '/' . ltrim(request()->path(), '/') }}</strong>
                        </div>
                        <div class="diagnostics-row">
                            <span>TIME</span>
                            <strong>{{ now()->format('H:i:s') }}</strong>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="@yield('action_url', url('/'))" class="action-link primary">@yield('action_label', 'Вернуться в терминал')</a>
                        <a href="javascript:history.back()" class="action-link">Назад</a>
                    </div>
                </div>
            </div>

            <div class="status-strip">
                <span class="status-dot" aria-hidden="true"></span>
                <span>Service operational</span>
                <span>Theme: <span id="theme-name">{{ $currentTheme ?? request()->cookie('theme', 'consortium') }}</span></span>
            </div>
        </section>
    </main>

    <script>
        (function () {
            var label = document.getElementById('theme-name');
            if (label) {
                label.textContent = document.body.getAttribute('data-theme') || document.documentElement.getAttribute('data-theme') || label.textContent;
            }
        })();
    </script>
</body>
</html>
