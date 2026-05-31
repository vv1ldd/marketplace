<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Decision Console — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg: #050505;
            --card: #0d0d0d;
            --muted: #9ca3af;
            --text: #f8fafc;
            --line: rgba(255, 255, 255, 0.08);
            --primary: #f53003;
            --green: #10b981;
            --rose: #f43f5e;
            --amber: #f59e0b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 10%, rgba(245, 48, 3, 0.12), transparent 32rem),
                radial-gradient(circle at 90% 0%, rgba(59, 130, 246, 0.08), transparent 26rem),
                var(--bg);
            color: var(--text);
            font-family: Inter, system-ui, sans-serif;
        }
        .shell { max-width: 1440px; margin: 0 auto; padding: 32px; }
        .topbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 28px; }
        .eyebrow { color: var(--primary); font-family: "JetBrains Mono", monospace; font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        h1 { margin: 8px 0 10px; font-size: clamp(32px, 4vw, 56px); line-height: .95; letter-spacing: -0.05em; }
        .subtitle { max-width: 760px; color: var(--muted); font-size: 16px; line-height: 1.6; }
        .back { color: var(--text); text-decoration: none; border: 1px solid var(--line); border-radius: 999px; padding: 10px 14px; font-weight: 800; }
        .notice { margin-bottom: 18px; border: 1px solid rgba(16, 185, 129, .25); background: rgba(16, 185, 129, .08); color: #bbf7d0; padding: 14px 16px; border-radius: 16px; }
        .tabs { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 18px; }
        .tab { color: var(--muted); text-decoration: none; border: 1px solid var(--line); border-radius: 999px; padding: 10px 14px; font-weight: 800; }
        .tab.active { color: #fff; background: var(--primary); border-color: var(--primary); }
        .grid { display: grid; gap: 16px; }
        .card { background: rgba(13, 13, 13, .88); border: 1px solid var(--line); border-radius: 22px; padding: 18px; box-shadow: 0 20px 80px rgba(0,0,0,.25); }
        .rec-head { display: grid; grid-template-columns: 1fr auto; gap: 18px; align-items: start; }
        .type { font-family: "JetBrains Mono", monospace; font-size: 12px; color: var(--primary); font-weight: 900; letter-spacing: .08em; }
        .query { margin: 6px 0; font-size: 24px; font-weight: 900; letter-spacing: -0.03em; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; color: var(--muted); font-size: 13px; }
        .pill { border: 1px solid var(--line); border-radius: 999px; padding: 6px 9px; background: rgba(255,255,255,.03); }
        .status-proposed { color: var(--amber); }
        .status-approved { color: var(--green); }
        .status-rejected { color: var(--rose); }
        .status-applied { color: #60a5fa; }
        .scores { display: flex; gap: 10px; text-align: right; }
        .score { min-width: 96px; border: 1px solid var(--line); border-radius: 16px; padding: 10px; }
        .score strong { display: block; font-size: 22px; }
        .score span { color: var(--muted); font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .details { margin-top: 16px; display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px; }
        .box { background: rgba(255,255,255,.03); border: 1px solid var(--line); border-radius: 16px; padding: 14px; overflow: auto; }
        .box h3 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: var(--muted); letter-spacing: .08em; }
        pre { margin: 0; white-space: pre-wrap; word-break: break-word; font-family: "JetBrains Mono", monospace; color: #d1d5db; font-size: 12px; line-height: 1.55; }
        .actions { margin-top: 16px; display: flex; gap: 10px; justify-content: flex-end; }
        button { border: 0; border-radius: 12px; padding: 10px 14px; color: white; font-weight: 900; cursor: pointer; }
        .approve { background: var(--green); }
        .reject { background: var(--rose); }
        .empty { text-align: center; color: var(--muted); padding: 60px 20px; }
        .pagination { margin-top: 18px; color: var(--muted); }
        @media (max-width: 900px) {
            .rec-head, .details { grid-template-columns: 1fr; }
            .scores { text-align: left; }
        }
    </style>
</head>
<body>
    <main class="shell">
        <header class="topbar">
            <div>
                <div class="eyebrow">Governance Interface</div>
                <h1>Decision Console</h1>
                <div class="subtitle">
                    Authorize, reject, and inspect recommended market-model changes. Approval changes only the governance state; it does not mutate SearchProfile, ranking, catalog facts, or provider supply.
                </div>
            </div>
            <a class="back" href="{{ route('ops.dashboard') }}">Ops Center</a>
        </header>

        @if (session('status'))
            <div class="notice">{{ session('status') }}</div>
        @endif

        @php
            $tabs = ['proposed', 'approved', 'rejected', 'applied', 'all'];
        @endphp
        <nav class="tabs" aria-label="Recommendation status filters">
            @foreach ($tabs as $tab)
                <a class="tab {{ $status === $tab ? 'active' : '' }}" href="{{ route('ops.decision-console', ['status' => $tab]) }}">
                    {{ strtoupper($tab) }}
                    @if ($tab !== 'all')
                        · {{ (int) ($statusCounts[$tab] ?? 0) }}
                    @endif
                </a>
            @endforeach
        </nav>

        <section class="grid">
            @forelse ($recommendations as $recommendation)
                <article class="card">
                    <div class="rec-head">
                        <div>
                            <div class="type">{{ $recommendation->type }}</div>
                            <div class="query">{{ $recommendation->query }}</div>
                            <div class="meta">
                                <span class="pill">{{ $recommendation->insight_type }}</span>
                                <span class="pill status-{{ $recommendation->status }}">{{ $recommendation->status }}</span>
                                <span class="pill">updated {{ optional($recommendation->updated_at)->diffForHumans() }}</span>
                                @if ($recommendation->decided_at)
                                    <span class="pill">decided {{ $recommendation->decided_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="scores">
                            <div class="score">
                                <strong>{{ number_format((float) $recommendation->impact_score, 1) }}</strong>
                                <span>Impact</span>
                            </div>
                            <div class="score">
                                <strong>{{ number_format((float) $recommendation->confidence, 1) }}</strong>
                                <span>Confidence</span>
                            </div>
                        </div>
                    </div>

                    <div class="details">
                        <div class="box">
                            <h3>Expected Entity</h3>
                            <pre>{{ json_encode($recommendation->expected_entity ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                        <div class="box">
                            <h3>Evidence</h3>
                            <pre>{{ json_encode($recommendation->evidence ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    </div>

                    @if ($recommendation->status !== \App\Models\SearchDemandRecommendation::STATUS_APPLIED)
                        <div class="actions">
                            <form method="POST" action="{{ route('ops.decision-console.recommendations.approve', $recommendation) }}">
                                @csrf
                                <button class="approve" type="submit">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('ops.decision-console.recommendations.reject', $recommendation) }}">
                                @csrf
                                <button class="reject" type="submit">Reject</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <div class="card empty">
                    No recommendations in this state yet. Run <code>php artisan search-signals:recommend</code> to generate proposals from interpreted demand signals.
                </div>
            @endforelse
        </section>

        <div class="pagination">
            {{ $recommendations->links() }}
        </div>
    </main>
</body>
</html>
