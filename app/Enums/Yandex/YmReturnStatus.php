<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Логистический статус конкретного товара:
 *
 * * `CREATED` — возврат создан.
 *
 * * `RECEIVED` — возврат принят у отправителя.
 *
 * * `IN_TRANSIT` — возврат в пути.
 *
 * * `READY_FOR_PICKUP` — возврат готов к выдаче магазину.
 *
 * * `PICKED` — возврат выдан магазину.
 *
 * * `RECEIVED_ON_FULFILLMENT` — возврат принят на складе Маркета.
 *
 * * `CANCELLED` — возврат отменен.
 *
 * * `LOST` — возврат утерян.
 *
 * * `UTILIZED` — возврат утилизирован.
 *
 * * `PREPARED_FOR_UTILIZATION` — возврат готов к утилизации.
 *
 * * `EXPROPRIATED` — товары в возврате направлены на перепродажу.
 *
 * * `NOT_IN_DEMAND` — возврат не забрали с почты.
 */
enum YmReturnStatus: string
{
    case CREATED = 'CREATED';
    case RECEIVED = 'RECEIVED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case READY_FOR_PICKUP = 'READY_FOR_PICKUP';
    case PICKED = 'PICKED';
    case RECEIVED_ON_FULFILLMENT = 'RECEIVED_ON_FULFILLMENT';
    case CANCELLED = 'CANCELLED';
    case LOST = 'LOST';
    case UTILIZED = 'UTILIZED';
    case PREPARED_FOR_UTILIZATION = 'PREPARED_FOR_UTILIZATION';
    case EXPROPRIATED = 'EXPROPRIATED';
    case NOT_IN_DEMAND = 'NOT_IN_DEMAND';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'CREATED',
            self::RECEIVED => 'RECEIVED',
            self::IN_TRANSIT => 'IN_TRANSIT',
            self::READY_FOR_PICKUP => 'READY_FOR_PICKUP',
            self::PICKED => 'PICKED',
            self::RECEIVED_ON_FULFILLMENT => 'RECEIVED_ON_FULFILLMENT',
            self::CANCELLED => 'CANCELLED',
            self::LOST => 'LOST',
            self::UTILIZED => 'UTILIZED',
            self::PREPARED_FOR_UTILIZATION => 'PREPARED_FOR_UTILIZATION',
            self::EXPROPRIATED => 'EXPROPRIATED',
            self::NOT_IN_DEMAND => 'NOT_IN_DEMAND',
        };
    }
}
