<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --amber: #f59e0b;
            --bg: #080b10;
            --bg-card: #0f1420;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #64748b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        .container {
            max-width: 600px;
            padding: 2rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(245, 158, 11, 0.1);
            color: var(--amber);
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2rem;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        h1 { font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 1rem; }
        p { color: var(--muted); font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
        
        .sovereign-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2rem;
            text-align: left;
            margin-top: 3rem;
        }
        .card-title { font-weight: 800; font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem; }
        .data-label { color: var(--muted); }
        .data-value { font-weight: 600; }

        .l1-badge {
            color: #10b981;
            font-weight: 700;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 1rem;
        }
    </style>
</head>
<body>

<div class="container">
    @if($legalEntity && !$legalEntity->is_active)
        <div class="status-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            На модерации
        </div>
        <h1>Почти готово, {{ explode(' ', $legalEntity->name)[0] }}</h1>
        <p>
            Ваши суверенные данные успешно заякорены в Simple-L1 Fabric. 
            Администратор проверяет детали партнерства. Это обычно занимает до 24 часов.
        </p>

        <div class="sovereign-card">
            <div class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Identity Manifest
            </div>
            <div class="data-row">
                <span class="data-label">Организация:</span>
                <span class="data-value">{{ $legalEntity->name }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">ИНН:</span>
                <span class="data-value">{{ $legalEntity->inn }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">L1 Address:</span>
                <span class="data-value" style="font-family: monospace; font-size: 0.8rem;">{{ Auth::user()->meta['l1_address'] ?? 'pending...' }}</span>
            </div>

            <div class="l1-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                VERIFIED & ANCHORED IN SIMPLE-L1 FABRIC
            </div>
        </div>
    @else
        <h1>Добро пожаловать!</h1>
        <p>Ваш аккаунт активен. Вы можете приступать к работе.</p>
    @endif
</div>

</body>
</html>
