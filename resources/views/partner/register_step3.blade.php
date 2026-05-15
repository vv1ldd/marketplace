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

        <div id="offer-actions">
            <button type="button" id="sign-offer-btn" class="btn-submit">
                Подписать оферту и завершить регистрацию ✍️
            </button>
            <p id="status-msg" style="text-align: center; font-size: 0.8rem; margin-top: 1rem; color: var(--amber); display: none;"></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@7.2.0/dist/bundle/index.umd.min.js"></script>
    <script>
        const { startRegistration } = SimpleWebAuthnBrowser;
        const signBtn = document.getElementById('sign-offer-btn');
        const statusMsg = document.getElementById('status-msg');

        signBtn.addEventListener('click', async () => {
            let options = @json($passkeyOptions);
            
            if (typeof options === 'string') {
                options = JSON.parse(options);
            }
            
            if (!options) {
                alert('Сессия истекла. Пожалуйста, начните регистрацию сначала.');
                window.location.href = "{{ route('partner.register') }}";
                return;
            }

            signBtn.disabled = true;
            signBtn.innerText = "Подписание... 🛡️";
            statusMsg.style.display = 'block';
            statusMsg.innerText = "Пожалуйста, подтвердите вашу личность с помощью Passkey (FaceID/Fingerprint)";

            try {
                const attestationResponse = await startRegistration(options);
                
                const verifyRes = await fetch("{{ route('partner.register.passkey.store') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(attestationResponse)
                });

                const result = await verifyRes.json();

                if (result.success) {
                    statusMsg.innerText = "Подпись подтверждена! Создаем организацию...";
                    window.location.href = result.redirect;
                } else {
                    throw new Error(result.error || 'Ошибка верификации');
                }
            } catch (error) {
                console.error(error);
                signBtn.disabled = false;
                signBtn.innerText = "Попробовать снова ✍️";
                statusMsg.innerText = "Ошибка: " + error.message;
            }
        });
    </script>
</body>
</html>
