<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Migration Pill | MEANLY</title>
    <script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #050505;
            color: #fff;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .card {
            width: min(440px, calc(100vw - 32px));
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 16px;
            background: #0b0b0b;
            padding: 32px;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.45);
            text-align: center;
        }
        .mark {
            width: 14px;
            height: 14px;
            margin: 0 auto 28px;
            border-radius: 4px;
            background: #f53003;
            box-shadow: 0 0 24px rgba(245, 48, 3, 0.5);
        }
        h1 {
            margin: 0 0 12px;
            font-size: 24px;
        }
        p {
            color: #9a9a9a;
            line-height: 1.5;
        }
        button {
            width: 100%;
            height: 48px;
            margin-top: 24px;
            border: 0;
            border-radius: 10px;
            background: #f53003;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .error {
            color: #ff9f9f;
        }
        .status {
            min-height: 20px;
            margin-top: 18px;
            font-size: 13px;
            color: #aaa;
        }
        .form-group {
            margin-top: 24px;
            text-align: left;
        }
        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #8e8e93;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .form-input {
            width: 100%;
            height: 48px;
            background: #020202;
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: #fff;
            padding: 0 16px;
            border-radius: 8px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: #f53003;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="mark"></div>

        @if ($error)
            <h1>Ссылка недоступна</h1>
            <p class="error">{{ $error }}</p>
        @else
            @php
                $email = $pill?->user?->email;
                $firstName = $pill?->user?->first_name;
                $showEmailForm = empty($email) 
                    || str_contains($email, '@migration.meanly.local') 
                    || $email === 'partner@meanly.ru'
                    || $firstName === 'Владелец' 
                    || empty($firstName) 
                    || $firstName === 'Legal';
            @endphp

            <h1>Создайте production Passkey</h1>
            <p>
                Таблетка выпущена для {{ $legalEntity?->short_name ?: $legalEntity?->name ?: 'юридического лица' }}.
                После создания ключа ссылка будет погашена навсегда.
            </p>

            @if ($showEmailForm)
                <div class="form-group">
                    <label for="email" class="form-label">Рабочий Email</label>
                    <input type="email" id="email" class="form-input"
                           value="{{ (empty($email) || str_contains($email, '@migration.meanly.local') || $email === 'partner@meanly.ru') ? '' : $email }}" 
                           placeholder="partner@example.com" required>
                </div>
            @endif

            <button id="enroll-button" type="button">Создать ключ доступа</button>
            <div id="status" class="status"></div>
        @endif
    </main>

    @if (! $error)
        <script>
            const button = document.getElementById('enroll-button');
            const statusEl = document.getElementById('status');
            const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            function setStatus(message) {
                statusEl.textContent = message;
            }

            button.addEventListener('click', async () => {
                const emailInput = document.getElementById('email');
                const requestPayload = {};
                
                if (emailInput) {
                    const emailVal = emailInput.value.trim();
                    if (!emailVal) {
                        setStatus('Пожалуйста, введите рабочий Email.');
                        return;
                    }
                    requestPayload.email = emailVal;
                }

                button.disabled = true;
                setStatus('Запрашиваем параметры Passkey...');

                try {
                    const optionsResponse = await fetch(@json(route('migration-pill.options', ['token' => $token])), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(requestPayload),
                    });
                    const optionsPayload = await optionsResponse.json();

                    if (!optionsResponse.ok || optionsPayload.error) {
                        throw new Error(optionsPayload.error || 'Не удалось получить параметры Passkey.');
                    }

                    setStatus('Подтвердите создание ключа в браузере...');
                    const attestation = await SimpleWebAuthnBrowser.startRegistration(optionsPayload.options);

                    setStatus('Сохраняем ключ и погашаем таблетку...');
                    const acceptResponse = await fetch(@json(route('migration-pill.accept', ['token' => $token])), {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': optionsPayload.new_csrf || csrf,
                        },
                        body: JSON.stringify({ passkey_attestation: JSON.stringify(attestation) }),
                    });
                    const acceptPayload = await acceptResponse.json();

                    if (!acceptResponse.ok || acceptPayload.error) {
                        throw new Error(acceptPayload.error || 'Не удалось сохранить ключ.');
                    }

                    setStatus('Готово. Перенаправляем в кабинет...');
                    window.location.href = acceptPayload.redirect;
                } catch (error) {
                    button.disabled = false;
                    setStatus(error.message);
                }
            });
        </script>
    @endif
</body>
</html>
