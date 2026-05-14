<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Статус заказа:
 *
 * * `PLACING` — оформляется, подготовка к резервированию.
 *
 * * `RESERVED` — зарезервирован, но недооформлен (только для LaaS).
 *
 * * `UNPAID` — оформлен, но еще не оплачен (если выбрана оплата при оформлении).
 *
 * * `PROCESSING` — находится в обработке.
 *
 * * `DELIVERY` — передан в службу доставки.
 *
 * * `PICKUP` — доставлен в пункт выдачи.
 *
 * * `DELIVERED` — получен покупателем.
 *
 * * `CANCELLED` — отменен.
 *
 * * `PENDING` — ожидает обработки со стороны продавца.
 *
 * * `PARTIALLY_RETURNED` — возвращен частично.
 *
 * * `RETURNED` — возвращен полностью.
 *
 * * `UNKNOWN` — неизвестный статус.
 *
 * Также могут возвращаться другие значения. Обрабатывать их не нужно.
 */
enum YmOrderStatus: string
{
    case PLACING = 'PLACING';
    case RESERVED = 'RESERVED';
    case UNPAID = 'UNPAID';
    case PROCESSING = 'PROCESSING';
    case DELIVERY = 'DELIVERY';
    case PICKUP = 'PICKUP';
    case DELIVERED = 'DELIVERED';
    case CANCELLED = 'CANCELLED';
    case PENDING = 'PENDING';
    case PARTIALLY_RETURNED = 'PARTIALLY_RETURNED';
    case RETURNED = 'RETURNED';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::PLACING => 'PLACING',
            self::RESERVED => 'RESERVED',
            self::UNPAID => 'UNPAID',
            self::PROCESSING => 'PROCESSING',
            self::DELIVERY => 'DELIVERY',
            self::PICKUP => 'PICKUP',
            self::DELIVERED => 'DELIVERED',
            self::CANCELLED => 'CANCELLED',
            self::PENDING => 'PENDING',
            self::PARTIALLY_RETURNED => 'PARTIALLY_RETURNED',
            self::RETURNED => 'RETURNED',
            self::UNKNOWN => 'UNKNOWN',
        };
    }
}
