<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --amber: #f59e0b;
            --amber-light: #fcd34d;
            --bg: #080b10;
            --bg-card: #0f1420;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #64748b;
            --muted2: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        nav {
            padding: 1.5rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(8, 11, 16, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .logo { font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; color: var(--amber); text-decoration: none; }
        .logo span { color: var(--text); }

        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 1.5rem;
            position: relative;
        }

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 2.5rem;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            z-index: 1;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h1 { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.5rem; }
        .auth-header p { color: var(--muted2); font-size: 0.9rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { 
            display: block; font-size: 0.85rem; font-weight: 600; 
            color: var(--muted2); margin-bottom: 0.5rem; 
        }
        .form-input {
            width: 100%;
            height: 52px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: var(--text);
            padding: 0 1rem;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--amber);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: var(--amber);
            color: #000;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-submit:hover {
            background: var(--amber-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(245,158,11,0.3);
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--muted2);
        }
        .auth-footer a { color: var(--amber); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<nav>
    <a href="/" class="logo">Mean<span>ly</span></a>
</nav>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Создать аккаунт</h1>
            <p>Пароли больше не нужны. Используйте биометрию для входа.</p>
        </div>

        @if($errors->any())
            <div style="color: #f87171; font-size: 0.85rem; margin-bottom: 1rem; text-align: center;">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('partner.register.submit') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="ivan@company.com" required value="{{ old('email') }}">
            </div>

            <button type="submit" class="btn-submit">Начать регистрацию →</button>
        </form>

        <div class="auth-footer">
            Уже есть аккаунт? <a href="/partner">Войти</a>
        </div>
    </div>
</div>

</body>
</html>
