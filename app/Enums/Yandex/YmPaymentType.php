<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Тип оплаты заказа:
 *
 * * `PREPAID` — оплата при оформлении заказа.
 *
 * * `POSTPAID` — оплата при получении заказа.
 *
 * * `UNKNOWN` — неизвестный тип.
 *
 * Если параметр отсутствует, заказ будет оплачен при получении.
 */
enum YmPaymentType: string
{
    case PREPAID = 'PREPAID';
    case POSTPAID = 'POSTPAID';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::PREPAID => 'PREPAID',
            self::POSTPAID => 'POSTPAID',
            self::UNKNOWN => 'UNKNOWN',
        };
    }
}
