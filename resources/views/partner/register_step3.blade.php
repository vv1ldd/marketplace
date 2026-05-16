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
        .qr-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: rgba(255,255,255,0.03);
            border-radius: 16px;
            border: 1px dashed rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .qr-code {
            width: 100px;
            height: 100px;
            background: white;
            padding: 8px;
            border-radius: 8px;
        }
        .qr-text {
            font-size: 0.8rem;
            color: var(--muted);
            text-align: left;
        }
        .signer-selection {
            margin-bottom: 2rem;
            text-align: left;
            background: rgba(255,255,255,0.03);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,0.07);
        }
        .signer-option {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            padding: 12px;
            border-radius: 10px;
            transition: background 0.2s;
        }
        .signer-option:hover { background: rgba(255,255,255,0.05); }
        .signer-option input { width: 20px; height: 20px; accent-color: var(--amber); }
        
        .poa-fields {
            display: none;
            margin-top: 1rem;
            padding-left: 32px;
            flex-direction: column;
            gap: 12px;
        }
        .input-group { display: flex; flex-direction: column; gap: 4px; }
        .input-group label { font-size: 0.75rem; color: var(--muted); }
        .input-field {
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .confirmation-box {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 2rem;
            text-align: left;
            font-size: 0.85rem;
            color: var(--muted);
            line-height: 1.5;
        }
        .confirmation-box input { margin-top: 4px; width: 18px; height: 18px; accent-color: var(--amber); }

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
            <p>Дата размещения: {{ \App\Models\Agreement::where('is_active', true)->latest('published_at')->first()?->published_at->format('d.m.Y') ?? '30.04.2026' }} г.</p>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 0;">
            <p style="white-space: pre-wrap;">{{ $agreementText }}</p>
        </div>

        <!-- 🛡️ Authority Check (Institutional) -->
        <div style="margin-top: 1rem; margin-bottom: 2rem; padding: 1.25rem; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.1); border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                <div style="width: 28px; height: 28px; background: #10b981; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 0.9rem; font-weight: 800;">✓</div>
                <div style="font-weight: 800; font-size: 0.85rem; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">Полномочия авторизованы</div>
            </div>
            <p style="font-size: 0.75rem; color: var(--muted2); margin-left: 38px;">
                Право подписи подтверждено на основе данных государственного реестра и предоставленных полномочий.
            </p>
        </div>

        <label class="confirmation-box">
            <input type="checkbox" id="legal-confirm" onchange="toggleSignButton()">
            <span>
                Я подтверждаю свои полномочия на подписание документов от имени <strong>{{ session('partner_registration')['legal_name'] ?? 'организации' }}</strong>, 
                ознакомлен с условиями Публичной оферты и принимаю их в полном объеме.
            </span>
        </label>

        <div id="offer-actions">
            <button type="button" id="sign-offer-btn" class="btn-submit" disabled style="opacity: 0.5; cursor: not-allowed;">
                Подписать оферту и завершить регистрацию ✍️
            </button>
            <p id="status-msg" style="text-align: center; font-size: 0.8rem; margin-top: 1rem; color: var(--amber); display: none;"></p>
        </div>

        <div class="qr-section">
            <div class="qr-code">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data={{ urlencode(url()->current()) }}" alt="QR Code" style="width: 100%; height: 100%;">
            </div>
            <div class="qr-text">
                <strong>Нет сканера на компьютере?</strong><br>
                Отсканируйте этот код камерой смартфона, чтобы подписать оферту через FaceID или отпечаток пальца.
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
    <script>
        const { startAuthentication } = SimpleWebAuthnBrowser;
        const signBtn = document.getElementById('sign-offer-btn');
        const legalConfirm = document.getElementById('legal-confirm');
        const statusMsg = document.getElementById('status-msg');

        function toggleSignButton() {
            if (legalConfirm.checked) {
                signBtn.disabled = false;
                signBtn.style.opacity = "1";
                signBtn.style.cursor = "pointer";
            } else {
                signBtn.disabled = true;
                signBtn.style.opacity = "0.5";
                signBtn.style.cursor = "not-allowed";
            }
        }

        function toggleSignButton() {
            if (legalConfirm.checked) {
                signBtn.disabled = false;
                signBtn.style.opacity = "1";
                signBtn.style.cursor = "pointer";
            } else {
                signBtn.disabled = true;
                signBtn.style.opacity = "0.5";
                signBtn.style.cursor = "not-allowed";
            }
        }

        signBtn.addEventListener('click', async () => {
            if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
                alert('Для использования Passkey (FaceID) необходимо защищенное соединение (HTTPS).');
                return;
            }

            const optionsRaw = @json($signingOptions);
            let options = typeof optionsRaw === 'string' ? JSON.parse(optionsRaw) : optionsRaw;
            
            // 🛡️ Ensure RP ID stability
            options.rpId = window.location.hostname;

            signBtn.disabled = true;
            signBtn.innerText = "Подписание... 🛡️";
            statusMsg.style.display = 'block';
            statusMsg.innerText = "Формирование криптографического интента...";

            try {
                // 🛠️ Capture high-precision entropy package for the signature
                const intentEntropy = {
                    ts: new Date().toISOString(),
                    ua: navigator.userAgent,
                    context: 'institutional_agreement_signing'
                };

                // 🔑 CRYPTOGRAPHIC SIGNATURE (Assertion)
                const assertionResponse = await startAuthentication({ optionsJSON: options });
                console.log("Assertion received:", assertionResponse);

                // 📡 Final Institutional Anchoring
                const signRes = await fetch("{{ route('partner.register.agreement.sign') }}", {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json', 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                    },
                    body: JSON.stringify({
                        assertion: assertionResponse,
                        intent_entropy: intentEntropy
                    })
                });

                const result = await signRes.json();
                if (result.success) {
                    statusMsg.innerText = "Оферта успешно подписана! Входим в периметр...";
                    setTimeout(() => window.location.href = result.redirect, 1000);
                } else {
                    throw new Error(result.error || 'Ошибка при финализации подписи');
                }

            } catch (error) {
                console.error("Signature Error:", error);
                signBtn.disabled = false;
                signBtn.innerText = "Попробовать снова ✍️";
                statusMsg.innerText = "Ошибка: " + error.message;
            }
        });
    </script>
</body>
</html>
