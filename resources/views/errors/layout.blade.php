<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') | Consortium Terminal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --amber: #f59e0b;
            --bg: #080b10;
            --bg-card: #0f1420;
            --text: #f1f5f9;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .error-container {
            position: relative;
            z-index: 10;
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            width: 100%;
        }

        .error-code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 1rem;
            background: linear-gradient(180deg, var(--amber) 0%, rgba(245,158,11,0.2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.05em;
            position: relative;
        }

        .error-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text);
            margin-bottom: 1.5rem;
        }

        .error-message {
            color: #94a3b8;
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 3rem;
        }

        .intent-box {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px;
            padding: 2rem;
            text-align: left;
            margin-bottom: 2.5rem;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
        }

        .box-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .btn-home {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--amber);
            color: #000;
            padding: 1rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245,158,11,0.2);
        }

        .glow {
            position: absolute;
            width: 400px;
            height: 400px;
            background: var(--amber);
            filter: blur(150px);
            opacity: 0.05;
            border-radius: 50%;
            z-index: 1;
        }

        .status-dot {
            width: 8px; height: 8px;
            background: var(--amber);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.2); }
        }
    </style>
</head>
<body>
    <div class="glow"></div>
    <div class="error-container">
        <div class="error-code">@yield('code')</div>
        <div class="error-title">@yield('status')</div>
        
        <div class="intent-box">
            <div class="box-header">
                <span>Kernel Integrity Report</span>
                <span id="ts">{{ now()->format('H:i:s.v') }}</span>
            </div>
            <div style="color: #cbd5e1; line-height: 1.5;">
                <span style="color: var(--amber);">ERROR_STATE:</span> @yield('message')
                <br><br>
                <span style="color: #475569;">SYSTEM_TRACE:</span>
                <div style="color: #64748b; margin-top: 5px;">
                    DID: {{ substr(hash('sha256', request()->fullUrl()), 0, 16) }}<br>
                    IP: {{ request()->ip() }}<br>
                    PATH: {{ request()->path() }}
                </div>
            </div>
        </div>

        <a href="/" class="btn-home">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            Вернуться в Терминал
        </a>

        <div style="margin-top: 2rem; display: flex; align-items: center; justify-content: center; gap: 10px;">
            <div class="status-dot"></div>
            <span style="font-size: 10px; font-weight: 800; color: #475569; text-transform: uppercase; letter-spacing: 0.2em;">L1 Node Status: Operational</span>
        </div>
    </div>
</body>
</html>
