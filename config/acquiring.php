<?php

return [
    'company' => [
        'brand' => env('ACQUIRING_COMPANY_BRAND', 'Meanly'),
        'legal_name' => env('ACQUIRING_COMPANY_LEGAL_NAME', 'Meanly Systems'),
        'registered_country' => env('ACQUIRING_COMPANY_COUNTRY', 'Российская Федерация'),
        'legal_address' => env('ACQUIRING_COMPANY_LEGAL_ADDRESS', 'Укажите юридический адрес компании перед отправкой заявки в банк.'),
        'actual_address' => env('ACQUIRING_COMPANY_ACTUAL_ADDRESS', 'Укажите фактический адрес компании перед отправкой заявки в банк.'),
        'phone' => env('ACQUIRING_COMPANY_PHONE', '+7 (000) 000-00-00'),
        'email' => env('ACQUIRING_COMPANY_EMAIL', 'support@meanly.ru'),
        'inn' => env('ACQUIRING_COMPANY_INN', 'Укажите ИНН'),
        'kpp' => env('ACQUIRING_COMPANY_KPP', 'Укажите КПП при наличии'),
        'ogrn' => env('ACQUIRING_COMPANY_OGRN', 'Укажите ОГРН/ОГРНИП'),
    ],

    'bank' => [
        'name' => env('ACQUIRING_BANK_NAME', 'банк-эквайер, согласованный при подключении'),
        'ssl_level' => env('ACQUIRING_SSL_LEVEL', 'SSL123 или выше'),
        'payment_systems' => array_values(array_filter(array_map('trim', explode(',', (string) env('ACQUIRING_PAYMENT_SYSTEMS', 'МИР,Visa,Mastercard'))))),
    ],
];
