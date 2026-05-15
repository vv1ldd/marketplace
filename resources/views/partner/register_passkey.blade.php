<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sovereign Identity — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --amber: #f59e0b;
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
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .passkey-container {
            text-align: center;
            max-width: 500px;
            padding: 2rem;
            position: relative;
        }

        .auth-glow {
            position: absolute; width: 400px; height: 400px; 
            background: rgba(245,158,11,0.1); 
            border-radius: 50%; filter: blur(100px); 
            top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: -1;
        }

        .icon-box {
            font-size: 4rem;
            margin-bottom: 2rem;
            animation: pulse 2s infinite ease-in-out;
        }

        h1 { font-size: 2rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 1rem; }
        p { color: var(--muted2); font-size: 1.1rem; margin-bottom: 2.5rem; line-height: 1.5; }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.05);
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--muted2);
            border: 1px solid var(--border);
        }

        .btn-manual {
            background: var(--amber);
            color: #000;
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: none;
            margin-top: 2rem;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        #loading-dots::after {
            content: '...';
            animation: dots 1.5s infinite;
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }
    </style>
</head>
<body>

    <div class="auth-glow"></div>

    <div class="passkey-container">
        <div class="icon-box">🔐</div>
        <h1>Sovereign Auth Layer</h1>
        <p>Для завершения регистрации создайте биометрический ключ (Passkey). Это позволит вам входить в систему без пароля и создаст ваш уникальный адрес в сети L1.</p>
        
        <div class="status-badge">
            <span id="status-icon">⏳</span>
            <span id="status-text">Ожидание браузера<span id="loading-dots"></span></span>
        </div>

        <button id="retry-btn" class="btn-manual" onclick="register()">Попробовать снова</button>
    </div>

    <script>
        async function register() {
            const { startRegistration } = SimpleWebAuthnBrowser;
            const options = {!! $options !!};
            options.rp.id = window.location.hostname; 
            console.log('Passkey Options (fixed):', options);
            const statusText = document.getElementById('status-text');
            const statusIcon = document.getElementById('status-icon');
            const retryBtn = document.getElementById('retry-btn');

            // Reset UI
            statusText.innerText = 'Ожидание браузера...';
            statusIcon.innerText = '⏳';
            retryBtn.style.display = 'none';

            try {
                // 1. Create credential
                const credential = await startRegistration(options);
                
                statusText.innerText = 'Ключ создан! Синхронизируем...';
                
                // 2. Send to Spatie's endpoint to register
                const response = await fetch('/passkeys/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(credential)
                });

                if (response.ok) {
                    statusText.innerText = 'Ключ создан! Синхронизируем...';
                    
                    const finalizeRes = await fetch('{{ route('partner.register.finalize') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    const data = await finalizeRes.json();
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    }
                } else {
                    const errData = await response.text();
                    console.error('Passkey registration failed:', errData);
                    throw new Error('Ошибка сохранения ключа на сервере: ' + response.status);
                }

            } catch (error) {
                console.error(error);
                statusText.innerText = error.message || 'Ошибка создания ключа';
                document.getElementById('status-icon').innerText = '❌';
                retryBtn.style.display = 'inline-block';
            }
        }

        // Auto-start
        setTimeout(register, 1000);
    </script>
</body>
</html>
