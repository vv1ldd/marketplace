<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="6;url={{ $authorizeUrl }}">
    <title>Переход в Simple Layer One</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@600;700;800;900&family=JetBrains+Mono:wght@700;800;900&display=swap">
    <style>
        :root {
            --bg: #eef1ff;
            --panel: #ffffff;
            --ink: #050505;
            --muted: #3f4656;
            --brand: #7c3aed;
            --lime: #d8ff6f;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                linear-gradient(rgba(124, 58, 237, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124, 58, 237, 0.045) 1px, transparent 1px),
                var(--bg);
            background-size: 24px 24px;
            color: var(--ink);
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
        }
        .handoff-card {
            width: min(520px, 100%);
            padding: 28px;
            border: 4px solid var(--ink);
            background: var(--panel);
            box-shadow: 10px 10px 0 var(--ink);
            text-align: left;
        }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 18px;
            padding: 6px 10px;
            border: 2px solid var(--ink);
            background: #f7f3ff;
            color: var(--brand);
            box-shadow: 3px 3px 0 var(--ink);
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .mark {
            width: 18px;
            height: 18px;
            display: inline-grid;
            place-items: center;
            border: 2px solid var(--ink);
            background: var(--brand);
            color: #fff;
            font-size: 10px;
            line-height: 1;
        }
        h1 {
            margin: 0 0 12px;
            font-size: clamp(2rem, 7vw, 3.15rem);
            line-height: 0.95;
            letter-spacing: -0.065em;
            font-weight: 950;
        }
        p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            font-weight: 750;
            line-height: 1.45;
        }
        .facts {
            display: grid;
            gap: 8px;
            margin: 20px 0;
        }
        .fact {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 2px solid var(--ink);
            background: #ffffff;
            font-size: 13px;
            font-weight: 850;
        }
        .fact i {
            width: 10px;
            height: 10px;
            border: 2px solid var(--ink);
            background: var(--lime);
            flex: 0 0 auto;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin-top: 22px;
        }
        .primary {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 18px;
            border: 3px solid var(--ink);
            background: var(--brand);
            color: #ffffff;
            box-shadow: 5px 5px 0 var(--ink);
            font-size: 13px;
            font-weight: 950;
            text-decoration: none;
        }
        .primary:hover {
            transform: translate(2px, 2px);
            box-shadow: 3px 3px 0 var(--ink);
        }
        .small {
            color: #5b6272;
            font-family: 'JetBrains Mono', ui-monospace, monospace;
            font-size: 11px;
            font-weight: 800;
        }
    </style>
</head>
@include('partials.theme-sync-body')
<body>
    <main class="handoff-card" aria-labelledby="handoff-title">
        <div class="eyebrow"><span class="mark">SL</span> Simple Layer One</div>
        <h1 id="handoff-title">{{ $handoff['title'] }}</h1>
        <p>{{ $handoff['body'] }}</p>
        <div class="facts">
            @foreach($handoff['facts'] as $fact)
                <div class="fact"><i></i> {{ $fact }}</div>
            @endforeach
        </div>
        <div class="actions">
            <a class="primary" href="{{ $authorizeUrl }}">{{ $handoff['cta'] }}</a>
            <span class="small">Переходим через <span data-handoff-countdown>5</span> секунд...</span>
        </div>
    </main>
    <script>
        let secondsLeft = 5;
        const countdownNode = document.querySelector('[data-handoff-countdown]');
        const countdown = window.setInterval(() => {
            secondsLeft -= 1;
            if (countdownNode) {
                countdownNode.textContent = String(Math.max(secondsLeft, 0));
            }
            if (secondsLeft <= 0) {
                window.clearInterval(countdown);
            }
        }, 1000);
        window.setTimeout(() => {
            window.location.assign(@json($authorizeUrl));
        }, 5000);
    </script>
</body>
</html>
