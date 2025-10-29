<?php

namespace App\Helpers;

class NormalizePhone
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone));

        // 11 цифр, начинается с 8 или 7 → Россия
        if (strlen($phone) === 11 && preg_match('/^[78]/', $phone)) {
            $phone = '7' . substr($phone, 1);
            return '+' . $phone;
        }

        // 10 цифр → добавляем +7
        if (strlen($phone) === 10) {
            return '+7' . $phone;
        }

        // Уже начинается с 7 и длина 11 → просто добавляем +
        if (strlen($phone) === 11 && str_starts_with($phone, '7')) {
            return '+' . $phone;
        }

        // Все остальные — просто добавляем +
        return '+' . $phone;
    }
}
