<div
    style="background:#ffffff;color:#000000;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:22px;margin:0;padding:0">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tbody>
        <tr>
            <td align="center" style="padding:0 30px">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:600px;width:100%">
                    <tbody>
                    <!-- Заголовок -->
                    <tr>
                        <td style="color:#212121;font-family:Helvetica,Arial,sans-serif;font-size:32px;line-height:40px;padding-bottom:24px;text-align:left">
                            <strong>Активация цифрового товара</strong>
                        </td>
                    </tr>

                    <!-- Приветствие -->
                    <tr>
                        <td style="color:#212121;font-family:Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;padding-bottom:24px;text-align:left">
                            Здравствуйте, {{$first_name}}!
                        </td>
                    </tr>

                    <!-- Код заказа -->
                    <tr>
                        <td style="color:#212121;font-family:Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;text-align:left">
                            Вы оформили заказ <strong>{{$order_id}}</strong> с цифровым товаром — вот ваш код активации и
                            инструкция.
                        </td>
                    </tr>

                    <!-- Название товара -->
                    @foreach($keys_data as $key_data)
                        <tr>
                            <td style="color:#212121;font-family:Helvetica,Arial,sans-serif;font-size:20px;line-height:28px;padding-top:40px;text-align:left">
                                <strong>Игра {{$keys_data['name']}}, электронный ключ активации, TR</strong>
                            </td>
                        </tr>

                        <!-- Срок действия -->
                        <tr>
                            <td style="color:#000000;font-family:Helvetica,Arial,sans-serif;font-size:16px;line-height:24px;padding-top:16px;text-align:left">
                                Активируйте код до <strong>{{now()->addYear()->format('d.m.Y')}}</strong>
                            </td>
                        </tr>

                        <!-- Код активации -->
                        <tr>
                            <td style="color:#212121;font-family:Helvetica,Arial,sans-serif;font-size:20px;line-height:28px;padding-top:8px;text-align:left">
                                <strong>{{$key_data['key']}}</strong>
                            </td>
                        </tr>

                    @endforeach

                    <!-- Инструкция -->
                    <tr>
                        <td style="padding-top:24px;text-align:left">
                            <div
                                style="background:#fff5f5;border-radius:12px;color:#333;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.6;padding:20px">
                                <h1 style="color:red;font-size:20px;margin:0 0 12px">Как активировать ваш код?</h1>
                                <p style="margin:0">
                                    <strong style="color:red">Инструкция:</strong><br>
                                    1. Перейдите по ссылке
                                    <a href="https://1gros.ru/redeem" style="color:#d32f2f;font-weight:bold"
                                       target="_blank">1gros.ru/redeem</a><br>
                                    2. Скопируйте код из письма.<br>
                                    3. Вставьте его в поле и следуйте инструкциям.<br><br>
                                    💡 <em>Нет аккаунта? Мы создадим его для вас во время активации.</em><br><br>
                                    <strong style="color:red">Нужна помощь?</strong>
                                    <a href="https://1gros.ru/support" style="color:#d32f2f;font-weight:bold"
                                       target="_blank">Свяжитесь с нашей поддержкой</a>.
                                </p>
                            </div>
                        </td>
                    </tr>

                    </tbody>
                </table>
            </td>
        </tr>
        </tbody>
    </table>
</div>
