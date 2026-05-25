<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Приглашение в {{ $partnerName }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background-color: #080808;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #e0e0e0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            max-width: 560px;
            margin: 0 auto;
            padding: 32px 16px;
        }
        .card {
            background: #0f0f0f;
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            overflow: hidden;
        }
        .header {
            padding: 32px 40px 28px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-mark {
            width: 12px;
            height: 12px;
            background: #f53003;
            border-radius: 3px;
            display: inline-block;
        }
        .logo-text {
            font-size: 13px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            text-transform: uppercase;
        }
        .body {
            padding: 40px 40px 32px;
        }
        .greeting {
            font-size: 22px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.03em;
            margin-bottom: 16px;
            line-height: 1.3;
        }
        .description {
            font-size: 15px;
            color: #888888;
            line-height: 1.65;
            margin-bottom: 32px;
        }
        .invite-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.07);
            border-radius: 10px;
            padding: 20px 24px;
            margin-bottom: 28px;
        }
        .invite-label {
            font-size: 10px;
            font-weight: 700;
            color: #555555;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 6px;
        }
        .invite-value {
            font-size: 15px;
            font-weight: 600;
            color: #ffffff;
        }
        .invite-row {
            display: flex;
            gap: 24px;
        }
        .invite-row > div {
            flex: 1;
        }
        .role-badge {
            display: inline-block;
            background: rgba(245, 48, 3, 0.12);
            border: 1px solid rgba(245, 48, 3, 0.25);
            color: #f53003;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 100px;
            letter-spacing: 0.02em;
        }
        .cta-btn {
            display: block;
            background: #f53003;
            color: #ffffff;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            padding: 14px 24px;
            border-radius: 10px;
            letter-spacing: -0.01em;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(245, 48, 3, 0.35);
        }
        .link-fallback {
            font-size: 12px;
            color: #555555;
            line-height: 1.6;
            word-break: break-all;
            margin-bottom: 0;
        }
        .link-fallback a {
            color: #f53003;
            text-decoration: none;
        }
        .expiry-note {
            margin-top: 20px;
            font-size: 12px;
            color: #444444;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .footer {
            padding: 20px 40px 28px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }
        .footer-text {
            font-size: 11px;
            color: #333333;
            line-height: 1.6;
        }
        .footer-brand {
            font-size: 10px;
            font-weight: 800;
            color: #2a2a2a;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-top: 12px;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        {{-- Header --}}
        <div class="header">
            <span class="logo-mark"></span>
            <span class="logo-text">MEANLY</span>
        </div>

        {{-- Body --}}
        <div class="body">
            <div class="greeting">
                @if($inviteeName)
                    {{ $inviteeName }}, вас приглашают! 🎉
                @else
                    Вы получили приглашение! 🎉
                @endif
            </div>

            <p class="description">
                Компания <strong style="color: #e0e0e0;">{{ $partnerName }}</strong> приглашает вас
                присоединиться к своему рабочему пространству на платформе MEANLY.
                Чтобы принять приглашение, создайте защищённый аккаунт с Passkey — это займёт меньше минуты.
            </p>

            <div class="invite-card">
                <div class="invite-row">
                    <div>
                        <div class="invite-label">Компания</div>
                        <div class="invite-value">{{ $partnerName }}</div>
                    </div>
                    <div>
                        <div class="invite-label">Роль</div>
                        <div class="invite-value">
                            <span class="role-badge">{{ $roleLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <a href="{{ $inviteLink }}" class="cta-btn">
                🛡️ Принять приглашение
            </a>

            <p class="link-fallback">
                Если кнопка не работает, скопируйте ссылку:<br>
                <a href="{{ $inviteLink }}">{{ $inviteLink }}</a>
            </p>

            <div class="expiry-note">
                ⏳ Ссылка действительна в течение 7 дней.
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p class="footer-text">
                Если вы не ожидали этого письма или не запрашивали приглашение — просто проигнорируйте его.
                Ваши данные в безопасности.
            </p>
            <p class="footer-brand">MEANLY.SYSTEMS</p>
        </div>
    </div>
</div>
</body>
</html>
