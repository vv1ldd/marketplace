<!DOCTYPE html>
<html lang="ru">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Проверка бизнеса — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@700;800&family=Outfit:wght@500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #eef0fc; --card: #ffffff; --text: #050505; --muted: #4b5563; --brand: #7c3aed; --line: #050505; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 18px 14px;
            background:
                linear-gradient(rgba(5, 5, 5, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(5, 5, 5, .045) 1px, transparent 1px),
                var(--bg);
            background-size: 24px 24px;
            color: var(--text);
            font-family: "Outfit", ui-sans-serif, system-ui, sans-serif;
        }
        .panel {
            width: min(820px, 100%);
            background: var(--card);
            border: 3px solid var(--line);
            box-shadow: 7px 7px 0 var(--line);
            padding: clamp(20px, 4vh, 36px) clamp(20px, 4vw, 42px);
        }
        .brand { display: flex; align-items: center; gap: 9px; font-weight: 900; margin-bottom: 22px; font-size: 14px; }
        .brand-mark { width: 13px; height: 13px; background: var(--brand); border: 2px solid var(--line); box-shadow: 2px 2px 0 var(--line); }
        .eyebrow { font-family: "JetBrains Mono", monospace; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--brand); font-weight: 800; }
        h1 { max-width: 640px; margin: 8px 0 10px; font-size: clamp(38px, 6vw, 56px); line-height: .9; letter-spacing: -.07em; }
        p { max-width: 640px; color: var(--muted); font-size: clamp(15px, 2vw, 17px); line-height: 1.38; margin: 0; }
        .steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin: 24px 0 18px; }
        .step { display: grid; grid-template-columns: 30px 1fr; gap: 10px; align-items: start; padding: 12px; border: 2px solid var(--line); background: #f7f3ff; min-height: 104px; }
        .step strong { display: block; margin-bottom: 3px; font-size: 14px; }
        .step span:last-child { color: var(--muted); line-height: 1.25; font-size: 13px; }
        .num { width: 30px; height: 30px; display: grid; place-items: center; background: var(--brand); color: #fff; border: 2px solid var(--line); box-shadow: 2px 2px 0 var(--line); font-family: "JetBrains Mono", monospace; font-weight: 800; font-size: 13px; }
        .meta { margin-top: 14px; padding: 12px; border: 2px dashed var(--line); font-family: "JetBrains Mono", monospace; font-size: 11px; line-height: 1.45; color: var(--muted); }
        .notice { margin-top: 14px; padding: 14px; border: 3px solid var(--line); box-shadow: 4px 4px 0 var(--line); background: #ecfeff; font-weight: 850; line-height: 1.35; font-size: 14px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 0 16px; border: 3px solid var(--line); box-shadow: 3px 3px 0 var(--line); color: var(--text); background: #fff; font-weight: 900; text-decoration: none; font-size: 14px; }
        .btn.primary { background: var(--brand); color: #fff; }
        @media (max-height: 760px) and (min-width: 760px) {
            body { padding: 10px 12px; }
            .panel { padding: 22px 34px; }
            .brand { margin-bottom: 14px; }
            h1 { font-size: 48px; }
            p { font-size: 15px; }
            .steps { margin: 16px 0 12px; }
            .step { min-height: 88px; padding: 10px; }
            .notice { margin-top: 10px; padding: 12px; }
            .meta { margin-top: 10px; padding: 10px; }
            .actions { margin-top: 12px; }
        }
        @media (max-width: 720px) {
            body { display: block; min-height: 100svh; padding: 10px; }
            .panel { min-height: calc(100svh - 20px); width: 100%; border-width: 3px; box-shadow: 5px 5px 0 var(--line); padding: 18px 16px 20px; }
            .brand { margin-bottom: 18px; font-size: 12px; }
            .eyebrow { font-size: 10px; }
            h1 { font-size: clamp(36px, 13vw, 48px); max-width: 100%; margin-bottom: 8px; }
            p { font-size: 14px; line-height: 1.34; }
            .steps { grid-template-columns: 1fr; gap: 8px; margin: 16px 0 12px; }
            .step { min-height: auto; padding: 10px; grid-template-columns: 28px 1fr; }
            .num { width: 28px; height: 28px; }
            .step strong { font-size: 13px; }
            .step span:last-child { font-size: 12px; }
            .notice { padding: 12px; margin-top: 10px; font-size: 13px; box-shadow: 3px 3px 0 var(--line); }
            .meta { margin-top: 10px; padding: 10px; font-size: 10px; overflow-wrap: anywhere; }
            .actions { display: grid; grid-template-columns: 1fr; margin-top: 12px; }
            .btn { width: 100%; min-height: 42px; }
        }
    </style>
</head>
<body>
@include('partials.theme-sync-body')
<main class="panel">
    <div class="brand"><span class="brand-mark"></span> MEANLY BUSINESS</div>

    @php
        $statusLabel = match ($legalEntity?->status) {
            'active' => 'профиль открыт',
            'pending_signature' => 'ждем подпись',
            'pending_moderation' => 'проверяем компанию',
            default => $legalEntity?->is_active ? 'профиль открыт' : 'проверяем компанию',
        };
    @endphp

    <div class="eyebrow">Заявка отправлена</div>
    <h1>Мы проверяем компанию</h1>
    <p>
        Подпись получили, компанию взяли в работу. Обычно мы просто сверяем данные и открываем доступ. Если понадобится что-то уточнить, напишем на email компании.
    </p>

    <div class="steps">
        <div class="step"><span class="num">1</span><div><strong>Все подписано</strong><span>Заявка связана с вашим профилем и компанией.</span></div></div>
        <div class="step"><span class="num">2</span><div><strong>Смотрим компанию</strong><span>Проверим ИНН, название и базовые данные, чтобы не открыть кабинет случайной организации.</span></div></div>
        <div class="step"><span class="num">3</span><div><strong>Откроем кабинет</strong><span>После одобрения здесь появится доступ к заказам, товарам и настройкам.</span></div></div>
    </div>

    <div class="notice">
        Все в порядке, сейчас ничего делать не нужно. Если нам понадобятся детали, мы напишем на подтвержденный email компании.
    </div>

    <div class="meta">
        Компания: {{ $legalEntity?->name ?? 'профиль создается' }}<br>
        Статус: {{ $statusLabel }}<br>
        Email компании: {{ $legalEntity?->email ?? 'не указан' }}<br>
        Подано: {{ $submittedAt?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }}
    </div>

    <div class="actions">
        <a class="btn primary" href="{{ route('home') }}">Вернуться на витрину</a>
        <a class="btn" href="{{ route('business.landing') }}">Для бизнеса</a>
    </div>
</main>
</body>
</html>
