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

        <!-- 🎭 Signer Authorization Section -->
        <div class="signer-selection">
            <div style="font-weight: 800; font-size: 0.9rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Полномочия подписанта
            </div>
            
            <label class="signer-option">
                <input type="radio" name="signer_role" value="ceo" checked onchange="togglePoA(false)">
                <div>
                    <strong>Я — Руководитель компании</strong>
                    <div style="font-size: 0.75rem; color: var(--muted);">Действую на основании Устава (первое лицо)</div>
                </div>
            </label>

            <label class="signer-option">
                <input type="radio" name="signer_role" value="representative" onchange="togglePoA(true)">
                <div>
                    <strong>Действую по доверенности</strong>
                    <div style="font-size: 0.75rem; color: var(--muted);">Уполномоченный представитель организации</div>
                </div>
            </label>

            <div id="poa-form" class="poa-fields">
                <div class="input-group">
                    <label>ФИО представителя</label>
                    <input type="text" id="signer_name" class="input-field" placeholder="Иванов Иван Иванович">
                </div>
                <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="input-group">
                        <label>Номер доверенности</label>
                        <input type="text" id="poa_number" class="input-field" placeholder="№ 123/2026">
                    </div>
                    <div class="input-group">
                        <label>Дата выдачи</label>
                        <input type="date" id="poa_date" class="input-field">
                    </div>
                </div>
                <div class="input-group">
                    <label>Контактный телефон</label>
                    <input type="tel" id="signer_phone" class="input-field" placeholder="+7 (999) 000-00-00">
                </div>
            </div>
        </div>

        <!-- 🛡️ KYC & Identity Verification Section -->
        <div class="signer-selection" style="border-color: rgba(16, 185, 129, 0.2); background: rgba(16, 185, 129, 0.02);">
            <div style="font-weight: 800; font-size: 0.9rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 8px; color: #10b981;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                KYC: Верификация личности
            </div>
            <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1rem;">
                Для обеспечения юридической значимости подписи требуется подтверждение личности через скан паспорта и селфи-проверку (Liveness).
            </p>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-submit" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white; height: 40px; font-size: 0.75rem;">
                    Загрузить паспорт 📄
                </button>
                <button type="button" class="btn-submit" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; height: 40px; font-size: 0.75rem;">
                    Пройти Face-ID 🤳
                </button>
            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@7.2.0/dist/bundle/index.umd.min.js"></script>
    <script>
        const { startRegistration } = SimpleWebAuthnBrowser;
        const signBtn = document.getElementById('sign-offer-btn');
        const statusMsg = document.getElementById('status-msg');
        const legalConfirm = document.getElementById('legal-confirm');

        function togglePoA(show) {
            document.getElementById('poa-form').style.display = show ? 'flex' : 'none';
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
            const optionsRaw = @json($passkeyOptions);
            
            let options = optionsRaw;
            if (typeof options === 'string') {
                options = JSON.parse(options);
            }
            
            if (!options || !options.user) {
                alert('Ошибка: Данные для регистрации ключа не получены.');
                return;
            }

            // Gather Signer Info
            const role = document.querySelector('input[name="signer_role"]:checked').value;
            const signerInfo = {
                role: role,
                name: document.getElementById('signer_name').value,
                phone: document.getElementById('signer_phone').value,
                poa_number: document.getElementById('poa_number').value,
                poa_date: document.getElementById('poa_date').value
            };

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
                    body: JSON.stringify({
                        ...attestationResponse,
                        signer_info: signerInfo
                    })
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
