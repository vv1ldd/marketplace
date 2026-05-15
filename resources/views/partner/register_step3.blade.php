<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Суверенная Оферта — Meanly Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0c;
            --card: #141417;
            --amber: #f59e0b;
            --text: #ffffff;
            --muted: #a1a1aa;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.03) 1px, transparent 0);
            background-size: 40px 40px;
        }
        .container {
            width: 100%;
            max-width: 600px;
            padding: 2rem;
            background: var(--card);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            border: 1px solid rgba(255,255,255,0.05);
        }
        h1 { font-weight: 800; font-size: 1.75rem; margin-bottom: 1.5rem; text-align: center; }
        .agreement-box {
            background: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1.5rem;
            height: 350px;
            overflow-y: auto;
            font-size: 0.85rem;
            line-height: 1.6;
            color: var(--muted);
            margin-bottom: 2rem;
        }
        .btn-submit {
            background: var(--amber);
            color: black;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.3); }
        .btn-submit:active { transform: translateY(0); }
    </style>
</head>
<body>
    <div class="container">
        <h1>Публичная оферта</h1>
        <p style="text-align: center; color: var(--muted); margin-bottom: 2rem;">
            Вы почти у цели. Пожалуйста, подтвердите ваше согласие с правилами работы в суверенной экосистеме.
        </p>

        <div class="agreement-box">
            <h3>Договор на оказание услуг по размещению Товарных предложений</h3>
            <p>Дата размещения: 30 апреля 2026 г. <br> Дата вступления в силу: 01 мая 2026 г.</p>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 0;">
            <p>{{ $agreementText }}</p>
        </div>

        <form action="{{ route('partner.register.offer.submit') }}" method="POST">
            @csrf
            <button type="submit" class="btn-submit">
                Я принимаю условия оферты ✍️
            </button>
        </form>
    </div>
</body>
</html>
