<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Статус возврата денег:
 *
 * * `STARTED_BY_USER` — создан покупателем из личного кабинета.
 *
 * * `REFUND_IN_PROGRESS` — ждет решение о возврате денег (на рассмотрении).
 *
 * * `REFUNDED` — деньги возвращены.
 *
 * * `FAILED` — невозможно провести возврат покупателю.
 *
 * * `WAITING_FOR_DECISION` — ожидает решения (DBS).
 *
 * * `DECISION_MADE` — по возврату принято решение (DBS).
 *
 * * `REFUNDED_WITH_BONUSES` — возврат осуществлен баллами Плюса или промокодом.
 *
 * * `REFUNDED_BY_SHOP` — магазин сделал самостоятельно возврат денег.
 *
 * * `COMPLETE_WITHOUT_REFUND` — возврат денег не требуется.
 *
 * * `CANCELLED` — возврат отменен.
 *
 * * `REJECTED` — возврат отклонен модерацией или в ПВЗ.
 *
 * * `PREMODERATION_DISPUTE` — по возврату открыт спор (FBY, FBS и Экспресс).
 *
 * * `PREMODERATION_DECISION_WAITING` — ожидает решения (FBY, FBS и Экспресс).
 *
 * * `PREMODERATION_DECISION_MADE` — по возврату принято решение (FBY, FBS и Экспресс).
 *
 * * `PREMODERATION_SELECT_DELIVERY` — пользователь выбирает способ доставки (FBY, FBS и Экспресс).
 *
 * * `UNKNOWN` — неизвестный статус, обратитесь в поддержку.
 */
enum YmRefundStatus: string
{
    case STARTED_BY_USER = 'STARTED_BY_USER';
    case REFUND_IN_PROGRESS = 'REFUND_IN_PROGRESS';
    case REFUNDED = 'REFUNDED';
    case FAILED = 'FAILED';
    case WAITING_FOR_DECISION = 'WAITING_FOR_DECISION';
    case DECISION_MADE = 'DECISION_MADE';
    case REFUNDED_WITH_BONUSES = 'REFUNDED_WITH_BONUSES';
    case REFUNDED_BY_SHOP = 'REFUNDED_BY_SHOP';
    case CANCELLED = 'CANCELLED';
    case REJECTED = 'REJECTED';
    case COMPLETE_WITHOUT_REFUND = 'COMPLETE_WITHOUT_REFUND';
    case PREMODERATION_DISPUTE = 'PREMODERATION_DISPUTE';
    case PREMODERATION_DECISION_WAITING = 'PREMODERATION_DECISION_WAITING';
    case PREMODERATION_DECISION_MADE = 'PREMODERATION_DECISION_MADE';
    case PREMODERATION_SELECT_DELIVERY = 'PREMODERATION_SELECT_DELIVERY';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::STARTED_BY_USER => 'STARTED_BY_USER',
            self::REFUND_IN_PROGRESS => 'REFUND_IN_PROGRESS',
            self::REFUNDED => 'REFUNDED',
            self::FAILED => 'FAILED',
            self::WAITING_FOR_DECISION => 'WAITING_FOR_DECISION',
            self::DECISION_MADE => 'DECISION_MADE',
            self::REFUNDED_WITH_BONUSES => 'REFUNDED_WITH_BONUSES',
            self::REFUNDED_BY_SHOP => 'REFUNDED_BY_SHOP',
            self::CANCELLED => 'CANCELLED',
            self::REJECTED => 'REJECTED',
            self::COMPLETE_WITHOUT_REFUND => 'COMPLETE_WITHOUT_REFUND',
            self::PREMODERATION_DISPUTE => 'PREMODERATION_DISPUTE',
            self::PREMODERATION_DECISION_WAITING => 'PREMODERATION_DECISION_WAITING',
            self::PREMODERATION_DECISION_MADE => 'PREMODERATION_DECISION_MADE',
            self::PREMODERATION_SELECT_DELIVERY => 'PREMODERATION_SELECT_DELIVERY',
            self::UNKNOWN => 'UNKNOWN',
        };
    }
}
