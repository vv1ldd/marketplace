<?php

declare(strict_types=1);

return [
    'failed'   => 'Неверное имя пользователя или пароль.',
    'password' => 'Некорректный пароль.',
    'throttle' => 'Слишком много попыток входа. Пожалуйста, попробуйте ещё раз через :seconds секунд.',
    'simple_l1' => [
        'page_title' => 'Переход в Maestrooo Identity',
        'countdown_prefix' => 'Переходим через',
        'countdown_suffix' => 'секунд...',
        'inline' => [
            'title' => 'Сейчас откроется Maestrooo Identity',
            'body' => 'Подтвердите вход в Maestrooo Identity, затем вернетесь обратно в Meanly.',
            'cta' => 'Продолжить сейчас',
            'countdown' => 'Переходим через :seconds секунд...',
            'redirecting' => 'Переходим...',
            'popup_body' => 'Завершите создание Сейфа в открывшемся окне. Если окно не появилось, отключите блокировку всплывающих окон или нажмите кнопку ниже.',
            'popup_reopen' => 'Открыть снова',
        ],
        'vault_open' => [
            'title' => 'Открываете сейф?',
            'body' => 'Сейчас откроется Maestrooo Identity. Подтвердите Vault, и мы вернем вас обратно к сейфу с покупками.',
            'facts' => [
                'owner_only' => 'Так коды увидит только владелец аккаунта.',
                'no_keys' => 'Meanly не получает ваши ключи и не видит лишнего.',
            ],
            'cta' => 'Подтвердить в Maestrooo Identity',
        ],
        'wallet_pay' => [
            'title' => 'Хотите оплатить кошельком?',
            'body' => 'Сейчас откроется Maestrooo Identity. Подтвердите оплату там, а мы вернем вас обратно к покупке.',
            'facts' => [
                'wallet_stays_private' => 'Баланс и операции остаются в Maestrooo Identity.',
                'result_only' => 'Meanly получит только результат оплаты.',
            ],
            'cta' => 'Перейти к оплате',
        ],
        'identity_create' => [
            'title' => 'Продолжить через Maestrooo Identity?',
            'body' => 'Сейчас откроется Maestrooo Identity. Там вы создадите или подтвердите аккаунт Meanly, затем вернетесь в Meanly.',
            'facts' => [
                'passkey_device' => 'Защищенный ключ остается на вашем устройстве.',
                'return_after' => 'После создания аккаунта вы вернетесь обратно в Meanly.',
            ],
            'cta' => 'Продолжить в Maestrooo Identity',
        ],
        'identity_confirm' => [
            'title' => 'Продолжить через Maestrooo Identity?',
            'body' => 'Сейчас откроется Maestrooo Identity. Подтвердите вход или создайте аккаунт, а затем вернетесь в Meanly.',
            'facts' => [
                'no_password' => 'Пароль не нужен.',
                'passkey_device' => 'Защищенный ключ остается на вашем устройстве.',
            ],
            'cta' => 'Продолжить в Maestrooo Identity',
        ],
    ],
];
