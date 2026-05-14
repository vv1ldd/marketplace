<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Резервный Wildflow (второй провайдер в БД)
    |--------------------------------------------------------------------------
    |
    | ID записи providers с type=wildflow и своим credentials.api_key.
    | Если null — после неудачи основного job только логирует и мягкий UX для клиента.
    |
    */
    'fallback_wildflow_provider_id' => env('REDEEM_FALLBACK_WILDFLOW_PROVIDER_ID'),

];
