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
        <h1>Юридическое оформление</h1>
        <p>
            Для активации продаж необходимо подписать суверенную оферту и подтвердить расчетный счет.
        </p>

        <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- ⚖️ Agreement Section -->
            <div class="sovereign-card">
                <div class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Публичная оферта
                </div>
                <div style="font-size: 0.85rem; color: var(--muted); margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; height: 120px; overflow-y: auto;">
                    Настоящим подтверждаю присоединение к суверенной экосистеме Simple-L1... [Полный текст оферты]
                </div>
                <button class="btn-submit" style="margin-top: 0; font-size: 0.8rem; height: 44px;">
                    Подписать через Passkey ✍️
                </button>
            </div>

            <!-- 💰 Banking Section -->
            <div class="sovereign-card">
                <div class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Расчетный счет
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <input type="text" placeholder="БИК Банка" class="form-input" style="height: 40px; font-size: 0.9rem;">
                    <input type="text" placeholder="Номер счета (407...)" class="form-input" style="height: 40px; font-size: 0.9rem;">
                    <button class="btn-submit" style="margin-top: 5px; font-size: 0.8rem; height: 44px; background: transparent; border: 1px solid var(--amber); color: var(--amber);">
                        Проверить реквизиты 🏦
                    </button>
                </div>
            </div>
        </div>

        <div class="sovereign-card" style="margin-top: 2rem;">
            <div class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Sovereign Manifest
            </div>
            <div class="data-row">
                <span class="data-label">Организация:</span>
                <span class="data-value">{{ $legalEntity->name }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">ИНН / ОГРН:</span>
                <span class="data-value">{{ $legalEntity->inn }} / {{ $legalEntity->ogrn ?? 'verified' }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">Юр. адрес:</span>
                <span class="data-value" style="font-size: 0.75rem;">{{ $legalEntity->legal_address ?? 'verified' }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">L1 Address:</span>
                <span class="data-value" style="font-family: monospace; font-size: 0.8rem;">{{ Auth::user()->meta['l1_address'] ?? 'pending...' }}</span>
            </div>
            <div class="l1-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                ANCHORED IN SIMPLE-L1 FABRIC
            </div>
        </div>
    @else
        <h1>Добро пожаловать!</h1>
        <p>Ваш аккаунт активен. Вы можете приступать к работе.</p>
    @endif
</div>

</body>
</html>
