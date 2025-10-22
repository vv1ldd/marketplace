<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Ваши данные PSN</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8f9fb;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 540px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4e54c8, #8f94fb);
            color: #fff;
            padding: 24px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .content {
            padding: 24px;
            color: #333;
        }

        .content p {
            font-size: 16px;
            margin: 12px 0;
        }

        .data-block {
            background-color: #f1f3f7;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            font-size: 16px;
            line-height: 1.5;
            word-break: break-word;
        }

        .data-block strong {
            display: inline-block;
            width: 110px;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            font-size: 14px;
            color: #999;
            padding: 16px;
        }

        @media (max-width: 600px) {
            .data-block strong {
                width: 100%;
                margin-bottom: 6px;
            }
        }
    </style>
</head>
<body>
<div>
    <br></div>
<div class="container">
    <div class="header">
        <h1>Ваш аккаунт PSN готов</h1>
    </div>
    <div class="content">
        <p>Благодарим за покупку. Ниже вы найдёте все необходимые данные для входа:</p>

        <div class="data-block">
            <p><strong>Логин:</strong><span>{{$login}}</span></p>
            <p><strong>Пароль:</strong><span>{{$password}}</span></p>
            <p><strong>2FA-коды:</strong></p>
            <div>{{$codes}}</div>
        </div>

        <p style="margin-top: 24px;">Пожалуйста, сохраните эти данные в безопасном месте.</p>
    </div>
    <div class="footer">
        Это автоматическое письмо от сервиса 1GROS.RU. Если у вас есть вопросы — <br>
        <a href="https://1gros.ru/support" style="color: #4e54c8; text-decoration: none;">напишите в поддержку</a>.
    </div>
</div>
</body>
</html>


