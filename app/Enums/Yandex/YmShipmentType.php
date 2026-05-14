<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Способ отгрузки заказов:
 *
 * * `IMPORT` — вы самостоятельно привозите заказы в выбранный сортировочный центр или пункт приема заказов.
 * * `WITHDRAW` — вы отгружаете заказы со своего склада курьерам Яндекс Маркета.
 */
enum YmShipmentType: string
{
    case IMPORT = 'IMPORT';
    case WITHDRAW = 'WITHDRAW';

    public function label(): string
    {
        return match ($this) {
            self::IMPORT => 'IMPORT',
            self::WITHDRAW => 'WITHDRAW',
        };
    }
}
