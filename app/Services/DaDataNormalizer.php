<?php

namespace App\Services;

class DaDataNormalizer
{
    /**
     * Normalize raw DaData party response into a clean structure.
     */
    public static function normalize(array $raw): array
    {
        $data = $raw['data'] ?? [];
        $isIP = ($data['type'] ?? '') === 'INDIVIDUAL';
        
        // 💰 Tax System Discovery Logic
        $rawTax = $data['tax_system'] ?? ($data['finance']['tax_system'] ?? null);
        $taxSystem = self::mapTaxSystem($rawTax, $isIP);

        return [
            'name' => $raw['value'] ?? 'Неизвестная организация',
            'inn' => $data['inn'] ?? null,
            'ogrn' => $data['ogrn'] ?? ($data['ogrnip'] ?? null),
            'kpp' => $data['kpp'] ?? null,
            'address' => $data['address']['value'] ?? ($data['address']['unrestricted_value'] ?? null),
            'management' => $data['management']['name'] ?? self::fio($data),
            'fio' => self::fio($data),
            'status' => $data['state']['status'] ?? 'UNKNOWN',
            'is_active' => ($data['state']['status'] ?? '') === 'ACTIVE',
            'is_ip' => $isIP,
            'tax_system' => $taxSystem,
            'raw_type' => $data['type'] ?? null,
        ];
    }

    private static function fio(array $data): ?string
    {
        $name = trim(implode(' ', array_filter([
            $data['fio']['surname'] ?? null,
            $data['fio']['name'] ?? null,
            $data['fio']['patronymic'] ?? null,
        ])));

        return $name !== '' ? $name : null;
    }

    /**
     * Map DaData tax system strings to internal enums.
     */
    private static function mapTaxSystem(?string $tax, bool $isIP): string
    {
        if (!$tax) return $isIP ? 'USN' : 'OSN';

        $tax = mb_strtoupper($tax);
        
        if (str_contains($tax, 'ОСН')) return 'OSN';
        if (str_contains($tax, 'АУСН')) return 'AUSN';
        if (str_contains($tax, 'УСН')) return 'USN';
        if (str_contains($tax, 'НПД')) return 'NPD';
        if (str_contains($tax, 'ЕСХН')) return 'USN';
        if (str_contains($tax, 'ПСН')) return 'USN';
        
        return $isIP ? 'USN' : 'OSN';
    }
}
