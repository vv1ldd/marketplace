<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Организация — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --amber: #f59e0b;
            --bg: #080b10;
            --bg-card: #0f1420;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted2: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 2.5rem;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
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
        }

        .step-indicator {
            display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 2rem;
        }
        .step-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--border); }
        .step-dot.active { background: var(--amber); width: 24px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="auth-card">
    <div class="step-indicator">
        <div class="step-dot"></div>
        <div class="step-dot active"></div>
    </div>

    <div class="auth-header">
        <h1>Данные компании</h1>
        <p>Почти готово! Укажите реквизиты вашей организации для работы с Кернелом.</p>
    </div>

    <form action="{{ route('partner.register.step2.submit') }}" method="POST">
        @csrf
        
        <div class="form-group">
            <label class="form-label">ИНН</label>
            <input type="text" id="inn-field" name="inn" class="form-input" placeholder="7700123456" required autocomplete="off">
        </div>

        <div class="form-group" id="name-container" style="display: none; opacity: 0; transition: all 0.3s;">
            <label class="form-label">Официальное название (автоматически)</label>
            <input type="text" id="name-field" name="legal_name" class="form-input" readonly style="background: rgba(0,255,0,0.05); border-color: rgba(0,255,0,0.2);">
            <div style="font-size: 10px; color: #0f0; margin-top: 5px; font-weight: 600;">✓ ANCHORED IN SIMPLE-L1 FABRIC</div>
        </div>

        <button type="submit" class="btn-submit">Завершить настройку →</button>
    </form>

    <script>
        const innField = document.getElementById('inn-field');
        const nameContainer = document.getElementById('name-container');
        const nameField = document.getElementById('name-field');

        innField.addEventListener('input', async (e) => {
            const inn = e.target.value.trim();
            if (inn.length === 10 || inn.length === 12) {
                try {
                    const response = await fetch('/api/b2b/search', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ inn: inn })
                    });
                    
                    const data = await response.json();
                    
                    if (data.verified) {
                        nameContainer.style.display = 'block';
                        setTimeout(() => {
                            nameContainer.style.opacity = '1';
                            nameField.value = data.name;
                        }, 50);
                    }
                } catch (e) {
                    console.error("DaData lookup failed", e);
                }
            } else {
                nameContainer.style.opacity = '0';
                setTimeout(() => { nameContainer.style.display = 'none'; }, 300);
            }
        });
    </script>
</div>

</body>
</html>
