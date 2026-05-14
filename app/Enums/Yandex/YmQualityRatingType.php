<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Составляющие индекса качества.
 *
 * **Для модели DBS:**
 * * `DBS_CANCELLATION_RATE` — доля отмененных товаров.
 * * `DBS_LATE_DELIVERY_RATE` — доля заказов, доставленных после плановой даты.
 *
 * **Для моделей FBS и Экспресс:**
 * * `FBS_CANCELLATION_RATE` — доля отмененных товаров.
 * * `FBS_LATE_SHIP_RATE` — доля не вовремя отгруженных заказов.
 *
 * **Для модели FBY:**
 * * `FBY_LATE_DELIVERY_RATE` — доля товаров, которые приехали на склад с опозданием.
 * * `FBY_CANCELLATION_RATE` — доля отмененных или недоставленных товаров.
 * * `FBY_DELIVERY_DIFF_RATE` — доля товаров, которые не прибыли вместе с поставкой или которые не приняли.
 * * `FBY_LATE_EDITING_RATE` — доля товаров, которые поздно убрали из заявки.
 */
enum YmQualityRatingType: string
{
    case DBS_CANCELLATION_RATE = 'DBS_CANCELLATION_RATE';
    case DBS_LATE_DELIVERY_RATE = 'DBS_LATE_DELIVERY_RATE';
    case FBS_CANCELLATION_RATE = 'FBS_CANCELLATION_RATE';
    case FBS_LATE_SHIP_RATE = 'FBS_LATE_SHIP_RATE';
    case FBY_LATE_DELIVERY_RATE = 'FBY_LATE_DELIVERY_RATE';
    case FBY_CANCELLATION_RATE = 'FBY_CANCELLATION_RATE';
    case FBY_DELIVERY_DIFF_RATE = 'FBY_DELIVERY_DIFF_RATE';
    case FBY_LATE_EDITING_RATE = 'FBY_LATE_EDITING_RATE';

    public function label(): string
    {
        return match ($this) {
            self::DBS_CANCELLATION_RATE => 'DBS_CANCELLATION_RATE',
            self::DBS_LATE_DELIVERY_RATE => 'DBS_LATE_DELIVERY_RATE',
            self::FBS_CANCELLATION_RATE => 'FBS_CANCELLATION_RATE',
            self::FBS_LATE_SHIP_RATE => 'FBS_LATE_SHIP_RATE',
            self::FBY_LATE_DELIVERY_RATE => 'FBY_LATE_DELIVERY_RATE',
            self::FBY_CANCELLATION_RATE => 'FBY_CANCELLATION_RATE',
            self::FBY_DELIVERY_DIFF_RATE => 'FBY_DELIVERY_DIFF_RATE',
            self::FBY_LATE_EDITING_RATE => 'FBY_LATE_EDITING_RATE',
        };
    }
}
