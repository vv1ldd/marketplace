<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Статус товара:
 *
 * * `PUBLISHED` — Готов к продаже.
 * * `CHECKING` — На проверке.
 * * `DISABLED_BY_PARTNER` — Скрыт вами.
 * * `REJECTED_BY_MARKET` — Отклонен.
 * * `DISABLED_AUTOMATICALLY` — Исправьте ошибки.
 * * `CREATING_CARD` — Создается карточка.
 * * `NO_CARD` — Нужна карточка.
 * * `NO_STOCKS` — Нет на складе.
 * * `ARCHIVED` — В архиве.
 *
 * [Что обозначает каждый из статусов](https://yandex.ru/support/marketplace/assortment/add/statuses.html)
 */
enum YmOfferStatus: string
{
    case PUBLISHED = 'PUBLISHED';
    case CHECKING = 'CHECKING';
    case DISABLED_BY_PARTNER = 'DISABLED_BY_PARTNER';
    case DISABLED_AUTOMATICALLY = 'DISABLED_AUTOMATICALLY';
    case REJECTED_BY_MARKET = 'REJECTED_BY_MARKET';
    case CREATING_CARD = 'CREATING_CARD';
    case NO_CARD = 'NO_CARD';
    case NO_STOCKS = 'NO_STOCKS';
    case ARCHIVED = 'ARCHIVED';

    public function label(): string
    {
        return match ($this) {
            self::PUBLISHED => 'PUBLISHED',
            self::CHECKING => 'CHECKING',
            self::DISABLED_BY_PARTNER => 'DISABLED_BY_PARTNER',
            self::DISABLED_AUTOMATICALLY => 'DISABLED_AUTOMATICALLY',
            self::REJECTED_BY_MARKET => 'REJECTED_BY_MARKET',
            self::CREATING_CARD => 'CREATING_CARD',
            self::NO_CARD => 'NO_CARD',
            self::NO_STOCKS => 'NO_STOCKS',
            self::ARCHIVED => 'ARCHIVED',
        };
    }
}
