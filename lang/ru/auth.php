<?php

declare(strict_types=1);

return [
    'failed'   => 'Неверное имя пользователя или пароль.',
    'password' => 'Некорректный пароль.',
    'throttle' => 'Слишком много попыток входа. Пожалуйста, попробуйте ещё раз через :seconds секунд.',
    'simple_l1' => [
        'page_title' => 'Переход в Simple Layer One',
        'countdown_prefix' => 'Переходим через',
        'countdown_suffix' => 'секунд...',
        'inline' => [
            'title' => 'Сейчас откроется Simple Layer One',
            'body' => 'Подтвердите действие через passkey, затем вернетесь обратно в Meanly.',
            'cta' => 'Продолжить сейчас',
            'countdown' => 'Переходим через :seconds секунд...',
            'redirecting' => 'Переходим...',
        ],
        'vault_open' => [
            'title' => 'Открываете сейф?',
            'body' => 'Сейчас откроется SL1 Connect. Подтвердите себя через passkey, и мы вернем вас обратно к сейфу с покупками.',
            'facts' => [
                'owner_only' => 'Так коды увидит только владелец аккаунта.',
                'no_keys' => 'Meanly не получает ваши ключи и не видит лишнего.',
            ],
            'cta' => 'Подтвердить в Simple Layer One',
        ],
        'wallet_pay' => [
            'title' => 'Хотите оплатить кошельком?',
            'body' => 'Сейчас откроется SL1 Wallet. Подтвердите оплату там, а мы вернем вас обратно к покупке.',
            'facts' => [
                'wallet_stays_private' => 'Баланс и операции остаются в вашем SL1 Wallet.',
                'result_only' => 'Meanly получит только результат оплаты.',
            ],
            'cta' => 'Перейти к оплате',
        ],
        'identity_create' => [
            'title' => 'Создаем аккаунт через SL1?',
            'body' => 'Сейчас откроется SL1 Connect. Там вы выберете имя и создадите passkey, чтобы потом входить в Meanly без пароля.',
            'facts' => [
                'passkey_device' => 'Passkey остается на вашем устройстве.',
                'return_after' => 'После создания аккаунта вы вернетесь обратно в Meanly.',
            ],
            'cta' => 'Создать в Simple Layer One',
        ],
        'identity_confirm' => [
            'title' => 'Входим через Simple Layer One?',
            'body' => 'Сейчас откроется SL1 Connect. Если аккаунт уже есть, войдите через passkey. Если нет - создайте его за пару шагов. После этого вернем вас в Meanly.',
            'facts' => [
                'no_password' => 'Пароль не нужен.',
                'passkey_device' => 'Passkey остается на вашем устройстве.',
            ],
            'cta' => 'Войти через Simple Layer One',
        ],
    ],
];
