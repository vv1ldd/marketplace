<?php

namespace App\Enums;

enum TaxSystemEnum: string
{
    case OSNO = 'osno';
    case USN_INCOME = 'usn_income';
    case USN_PROFIT = 'usn_profit';
    case NPD = 'npd';
    case PSN = 'psn';
    case AUSN_INCOME = 'ausn_income';
    case AUSN_PROFIT = 'ausn_profit';

    public function getLabel(): string
    {
        return match ($this) {
            self::OSNO => 'ОСНО (Общая система)',
            self::USN_INCOME => 'УСН Доходы (6%)',
            self::USN_PROFIT => 'УСН Доходы минус Расходы (15%)',
            self::AUSN_INCOME => 'АУСН Доходы (8%)',
            self::AUSN_PROFIT => 'АУСН Доходы минус Расходы (20%)',
            self::NPD => 'НПД (Самозанятый)',
            self::PSN => 'ПСН (Патент)',
        };
    }
    
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray();
    }
}
