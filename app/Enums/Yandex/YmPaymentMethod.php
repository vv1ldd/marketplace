<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Способ оплаты заказа:
 *
 * * Значения, если выбрана оплата при оформлении заказа (`"paymentType": "PREPAID"`):
 *
 *   * `YANDEX` — банковской картой.
 *
 *   * `APPLE_PAY` — Apple Pay (не используется).
 *
 *   * `GOOGLE_PAY` — Google Pay (не используется).
 *
 *   * `CREDIT` — в кредит.
 *
 *   * `TINKOFF_CREDIT` — в кредит в Тинькофф Банке.
 *
 *   * `TINKOFF_INSTALLMENTS` — рассрочка в Тинькофф Банке.
 *
 *   * `EXTERNAL_CERTIFICATE` — подарочным сертификатом (например, из приложения «Сбербанк Онлайн»).
 *
 *   * `SBP` — через систему быстрых платежей.
 *
 *   * `B2B_ACCOUNT_PREPAYMENT` — заказ оплачивает организация.
 *
 *   * `MICROCREDIT` - Сплит на основе МКК (Микрокредитной компании).
 *
 *   * `BNPL_TBYB` - Оплата после доставки на основе Сплита.
 *
 * * Значения, если выбрана оплата при получении заказа (`"paymentType": "POSTPAID"`):
 *
 *   * `CARD_ON_DELIVERY` — банковской картой.
 *
 *   * `BOUND_CARD_ON_DELIVERY` — привязанной картой при получении.
 *
 *   * `BNPL_BANK_ON_DELIVERY` — супер Сплитом.
 *
 *   * `BNPL_ON_DELIVERY` — Сплитом.
 *
 *   * `CASH_ON_DELIVERY` — наличными.
 *
 *   * `B2B_ACCOUNT_POSTPAYMENT` — заказ оплачивает организация после доставки.
 * * `UNKNOWN` — неизвестный тип.
 *
 * Значение по умолчанию: `CASH_ON_DELIVERY`.
 */
enum YmPaymentMethod: string
{
    case CASH_ON_DELIVERY = 'CASH_ON_DELIVERY';
    case CARD_ON_DELIVERY = 'CARD_ON_DELIVERY';
    case BOUND_CARD_ON_DELIVERY = 'BOUND_CARD_ON_DELIVERY';
    case BNPL_BANK_ON_DELIVERY = 'BNPL_BANK_ON_DELIVERY';
    case BNPL_ON_DELIVERY = 'BNPL_ON_DELIVERY';
    case YANDEX = 'YANDEX';
    case APPLE_PAY = 'APPLE_PAY';
    case EXTERNAL_CERTIFICATE = 'EXTERNAL_CERTIFICATE';
    case CREDIT = 'CREDIT';
    case GOOGLE_PAY = 'GOOGLE_PAY';
    case TINKOFF_CREDIT = 'TINKOFF_CREDIT';
    case SBP = 'SBP';
    case TINKOFF_INSTALLMENTS = 'TINKOFF_INSTALLMENTS';
    case B2B_ACCOUNT_PREPAYMENT = 'B2B_ACCOUNT_PREPAYMENT';
    case B2B_ACCOUNT_POSTPAYMENT = 'B2B_ACCOUNT_POSTPAYMENT';
    case MICROCREDIT = 'MICROCREDIT';
    case BNPL_TBYB = 'BNPL_TBYB';
    case UNKNOWN = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::CASH_ON_DELIVERY => 'CASH_ON_DELIVERY',
            self::CARD_ON_DELIVERY => 'CARD_ON_DELIVERY',
            self::BOUND_CARD_ON_DELIVERY => 'BOUND_CARD_ON_DELIVERY',
            self::BNPL_BANK_ON_DELIVERY => 'BNPL_BANK_ON_DELIVERY',
            self::BNPL_ON_DELIVERY => 'BNPL_ON_DELIVERY',
            self::YANDEX => 'YANDEX',
            self::APPLE_PAY => 'APPLE_PAY',
            self::EXTERNAL_CERTIFICATE => 'EXTERNAL_CERTIFICATE',
            self::CREDIT => 'CREDIT',
            self::GOOGLE_PAY => 'GOOGLE_PAY',
            self::TINKOFF_CREDIT => 'TINKOFF_CREDIT',
            self::SBP => 'SBP',
            self::TINKOFF_INSTALLMENTS => 'TINKOFF_INSTALLMENTS',
            self::B2B_ACCOUNT_PREPAYMENT => 'B2B_ACCOUNT_PREPAYMENT',
            self::B2B_ACCOUNT_POSTPAYMENT => 'B2B_ACCOUNT_POSTPAYMENT',
            self::MICROCREDIT => 'MICROCREDIT',
            self::BNPL_TBYB => 'BNPL_TBYB',
            self::UNKNOWN => 'UNKNOWN',
        };
    }
}
