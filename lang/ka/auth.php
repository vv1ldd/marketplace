<?php

declare(strict_types=1);

return [
    'failed' => 'ეს მონაცემები ჩვენს ჩანაწერებს არ ემთხვევა.',
    'password' => 'მითითებული პაროლი არასწორია.',
    'throttle' => 'შესვლის ძალიან ბევრი მცდელობაა. სცადეთ თავიდან :seconds წამში.',
    'simple_l1' => [
        'page_title' => 'Meanly One-ზე გადასვლა',
        'countdown_prefix' => 'გადამისამართება',
        'countdown_suffix' => 'წამში...',
        'inline' => [
            'title' => 'Meanly One ახლავე გაიხსნება',
            'body' => 'დაადასტურეთ identity მოთხოვნა Meanly One-ში და შემდეგ Meanly-ში დაბრუნდებით.',
            'cta' => 'გაგრძელება ახლა',
            'countdown' => 'გადამისამართება :seconds წამში...',
            'redirecting' => 'გადამისამართება...',
        ],
        'vault_open' => [
            'title' => 'გავხსნათ თქვენი სეიფი?',
            'body' => 'ახლა გაიხსნება Meanly One. დაადასტურეთ identity მოთხოვნა და დაგაბრუნებთ თქვენი შენაძენების სეიფში.',
            'facts' => [
                'owner_only' => 'კოდებს მხოლოდ ანგარიშის მფლობელი ნახავს.',
                'no_keys' => 'Meanly თქვენს გასაღებებს არასოდეს იღებს.',
            ],
            'cta' => 'დადასტურება Meanly One-ში',
        ],
        'wallet_pay' => [
            'title' => 'გადავიხადოთ wallet-ით?',
            'body' => 'ახლა გაიხსნება Meanly One. დაადასტურეთ გადახდა იქ და დაბრუნდით checkout-ზე.',
            'facts' => [
                'wallet_stays_private' => 'ბალანსი და ოპერაციები Meanly One-ში რჩება.',
                'result_only' => 'Meanly იღებს მხოლოდ გადახდის შედეგს.',
            ],
            'cta' => 'გადახდაზე გადასვლა',
        ],
        'identity_create' => [
            'title' => 'გავაგრძელოთ Meanly One-ით?',
            'body' => 'ახლა გაიხსნება Meanly One. იქ შექმნით ან დაადასტურებთ SL1 identity-ს და შემდეგ Meanly-ში დაბრუნდებით.',
            'facts' => [
                'passkey_device' => 'თქვენი credential identity layer-ში რჩება.',
                'return_after' => 'ანგარიშის შექმნის შემდეგ Meanly-ში დაბრუნდებით.',
            ],
            'cta' => 'გაგრძელება Meanly One-ში',
        ],
        'identity_confirm' => [
            'title' => 'შევიდეთ Meanly One-ით?',
            'body' => 'ახლა გაიხსნება Meanly One. დაადასტურეთ SL1 identity და შემდეგ Meanly-ში დაბრუნდებით.',
            'facts' => [
                'no_password' => 'პაროლი არ არის საჭირო.',
                'passkey_device' => 'Meanly იღებს მხოლოდ დადასტურებულ identity შედეგს.',
            ],
            'cta' => 'Meanly One-ით შესვლა',
        ],
    ],
];
