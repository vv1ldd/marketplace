<?php

declare(strict_types=1);

return [
    'failed'   => 'Неверное имя пользователя или пароль.',
    'password' => 'Некорректный пароль.',
    'throttle' => 'Слишком много попыток входа. Пожалуйста, попробуйте ещё раз через :seconds секунд.',
    'simple_l1' => [
        'page_title' => 'Переход в Meanly One',
        'countdown_prefix' => 'Переходим через',
        'countdown_suffix' => 'секунд...',
        'inline' => [
            'title' => 'Сейчас откроется Meanly One',
            'body' => 'Подтвердите identity-запрос в Meanly One, затем вернетесь обратно в Meanly.',
            'cta' => 'Продолжить сейчас',
            'countdown' => 'Переходим через :seconds секунд...',
            'redirecting' => 'Переходим...',
        ],
        'vault_open' => [
            'title' => 'Открываете сейф?',
            'body' => 'Сейчас откроется Meanly One. Подтвердите identity-запрос, и мы вернем вас обратно к сейфу с покупками.',
            'facts' => [
                'owner_only' => 'Так коды увидит только владелец аккаунта.',
                'no_keys' => 'Meanly не получает ваши ключи и не видит лишнего.',
            ],
            'cta' => 'Подтвердить в Meanly One',
        ],
        'wallet_pay' => [
            'title' => 'Хотите оплатить кошельком?',
            'body' => 'Сейчас откроется Meanly One. Подтвердите оплату там, а мы вернем вас обратно к покупке.',
            'facts' => [
                'wallet_stays_private' => 'Баланс и операции остаются в Meanly One.',
                'result_only' => 'Meanly получит только результат оплаты.',
            ],
            'cta' => 'Перейти к оплате',
        ],
        'identity_create' => [
            'title' => 'Продолжить через Meanly One?',
            'body' => 'Сейчас откроется Meanly One. Там вы создадите или подтвердите SL1 identity, затем вернетесь в Meanly.',
            'facts' => [
                'passkey_device' => 'Credential остается внутри identity layer.',
                'return_after' => 'После создания аккаунта вы вернетесь обратно в Meanly.',
            ],
            'cta' => 'Продолжить в Meanly One',
        ],
        'identity_confirm' => [
            'title' => 'Входим через Meanly One?',
            'body' => 'Сейчас откроется Meanly One. Подтвердите SL1 identity, и после этого мы вернем вас в Meanly.',
            'facts' => [
                'no_password' => 'Пароль не нужен.',
                'passkey_device' => 'Meanly получает только проверенный результат identity.',
            ],
            'cta' => 'Войти через Meanly One',
        ],
    ],
];
