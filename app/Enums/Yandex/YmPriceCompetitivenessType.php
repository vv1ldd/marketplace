<?php

namespace App\Enums\Yandex;

/**
 * Generated from Yandex Market OpenAPI spec
 * Привлекательность цены:
 *
 * * `OPTIMAL` — привлекательная.
 * * `AVERAGE` — умеренная.
 * * `LOW` — непривлекательная.
 */
enum YmPriceCompetitivenessType: string
{
    case OPTIMAL = 'OPTIMAL';
    case AVERAGE = 'AVERAGE';
    case LOW = 'LOW';

    public function label(): string
    {
        return match ($this) {
            self::OPTIMAL => 'OPTIMAL',
            self::AVERAGE => 'AVERAGE',
            self::LOW => 'LOW',
        };
    }
}
