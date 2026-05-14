<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Тип остатков товаров на складе:
 *
 * * `AVAILABLE` (соответствует типу «Доступный к заказу» в отчете «Остатки на складе» в кабинете продавца на Маркете) — товар, доступный для продажи.
 *
 * * `DEFECT` (соответствует типу «Брак») — товар с браком.
 *
 * * `EXPIRED` (соответствует типу «Просрочен») — товар с истекшим сроком годности.
 *
 * * `FIT` (соответствует типу «Годный») — товар, который доступен для продажи или уже зарезервирован.
 *
 * * `FREEZE` — товар, который зарезервирован для заказов.
 *
 * * `QUARANTINE` (соответствует типу «Карантин») — товар, временно недоступный для продажи (например, товар перемещают из одного помещения склада в другое).
 *
 * * `UTILIZATION` — товар, который будет утилизирован.
 */
enum YmWarehouseStockType: string
{
    case FIT = 'FIT';
    case FREEZE = 'FREEZE';
    case AVAILABLE = 'AVAILABLE';
    case QUARANTINE = 'QUARANTINE';
    case UTILIZATION = 'UTILIZATION';
    case DEFECT = 'DEFECT';
    case EXPIRED = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::FIT => 'FIT',
            self::FREEZE => 'FREEZE',
            self::AVAILABLE => 'AVAILABLE',
            self::QUARANTINE => 'QUARANTINE',
            self::UTILIZATION => 'UTILIZATION',
            self::DEFECT => 'DEFECT',
            self::EXPIRED => 'EXPIRED',
        };
    }
}
