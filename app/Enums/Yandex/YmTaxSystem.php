<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Система налогообложения (СНО) магазина на момент оформления заказа:
 *
 * * `ECHN` — единый сельскохозяйственный налог (ЕСХН).
 *
 * * `ENVD` — единый налог на вмененный доход (ЕНВД).
 *
 * * `OSN` — общая система налогообложения (ОСН).
 *
 * * `PSN` — патентная система налогообложения (ПСН).
 *
 * * `USN` — упрощенная система налогообложения (УСН).
 *
 * * `USN_MINUS_COST` — упрощенная система налогообложения, доходы, уменьшенные на величину расходов (УСН «Доходы минус расходы»).
 *
 * * `NPD` — налог на профессиональный доход (НПД).
 *
 * * `AUSN` — автоматизированная упрощенная система налогообложения (АУСН).
 *
 * * `AUSN_MINUS_COST` — автоматизированная упрощенная система налогообложения, доходы, уменьшенные на величину расходов (АУСН «Доходы минус расходы»).
 *
 * * `UNKNOWN_VALUE` — неизвестное значение.
 */
enum YmTaxSystem: string
{
    case OSN = 'OSN';
    case USN = 'USN';
    case USN_MINUS_COST = 'USN_MINUS_COST';
    case ENVD = 'ENVD';
    case ECHN = 'ECHN';
    case PSN = 'PSN';
    case NPD = 'NPD';
    case AUSN = 'AUSN';
    case AUSN_MINUS_COST = 'AUSN_MINUS_COST';
    case UNKNOWN_VALUE = 'UNKNOWN_VALUE';

    public function label(): string
    {
        return match ($this) {
            self::OSN => 'OSN',
            self::USN => 'USN',
            self::USN_MINUS_COST => 'USN_MINUS_COST',
            self::ENVD => 'ENVD',
            self::ECHN => 'ECHN',
            self::PSN => 'PSN',
            self::NPD => 'NPD',
            self::AUSN => 'AUSN',
            self::AUSN_MINUS_COST => 'AUSN_MINUS_COST',
            self::UNKNOWN_VALUE => 'UNKNOWN_VALUE',
        };
    }
}
