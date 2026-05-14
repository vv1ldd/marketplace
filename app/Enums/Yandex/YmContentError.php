<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Типы ошибок и предупреждений:
 *
 * * `OFFER_NOT_FOUND` — такого товара нет в каталоге.
 * * `UNKNOWN_CATEGORY` — указана неизвестная категория.
 * * `INVALID_CATEGORY` — указана нелистовая категория. Укажите ту, которая не имеет дочерних категорий.
 * * `UNKNOWN_PARAMETER` — передана характеристика, которой нет среди характеристик категории.
 * * `UNEXPECTED_BOOLEAN_VALUE` — вместо boolean-значения передано что-то другое.
 * * `NUMBER_FORMAT` — передана строка, не обозначающая число, вместо числа.
 * * `INVALID_UNIT_ID` — передана единица измерения, недопустимая для характеристики.
 * * `INVALID_GROUP_ID_LENGTH` — в названии превышено допустимое значение символов — 255.
 * * `INVALID_GROUP_ID_CHARACTERS` — переданы [недопустимые символы](*ascii-code).
 *
 * Проверить, какие категорийные характеристики доступны для заданной категории, и получить их настройки можно с помощью запроса [POST v2/category/{categoryId}/parameters](../../reference/content/getCategoryContentParameters).
 */
enum YmContentError: string
{
    case OFFER_NOT_FOUND = 'OFFER_NOT_FOUND';
    case UNKNOWN_CATEGORY = 'UNKNOWN_CATEGORY';
    case INVALID_CATEGORY = 'INVALID_CATEGORY';
    case UNKNOWN_PARAMETER = 'UNKNOWN_PARAMETER';
    case UNEXPECTED_BOOLEAN_VALUE = 'UNEXPECTED_BOOLEAN_VALUE';
    case NUMBER_FORMAT = 'NUMBER_FORMAT';
    case INVALID_UNIT_ID = 'INVALID_UNIT_ID';
    case INVALID_GROUP_ID_LENGTH = 'INVALID_GROUP_ID_LENGTH';
    case INVALID_GROUP_ID_CHARACTERS = 'INVALID_GROUP_ID_CHARACTERS';

    public function label(): string
    {
        return match ($this) {
            self::OFFER_NOT_FOUND => 'OFFER_NOT_FOUND',
            self::UNKNOWN_CATEGORY => 'UNKNOWN_CATEGORY',
            self::INVALID_CATEGORY => 'INVALID_CATEGORY',
            self::UNKNOWN_PARAMETER => 'UNKNOWN_PARAMETER',
            self::UNEXPECTED_BOOLEAN_VALUE => 'UNEXPECTED_BOOLEAN_VALUE',
            self::NUMBER_FORMAT => 'NUMBER_FORMAT',
            self::INVALID_UNIT_ID => 'INVALID_UNIT_ID',
            self::INVALID_GROUP_ID_LENGTH => 'INVALID_GROUP_ID_LENGTH',
            self::INVALID_GROUP_ID_CHARACTERS => 'INVALID_GROUP_ID_CHARACTERS',
        };
    }
}
