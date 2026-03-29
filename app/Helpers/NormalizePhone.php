<?php

namespace App\Helpers;

class NormalizePhone
{
    /**
     * Преобразует любой номер в формат +79000000000
     *
     * @param string $phone
     * @return string|null Возвращает нормализованный номер или null, если не удалось определить
     */
    public static function normalize(?string $phone): ?string
    {
        // Удаляем все нецифровые символы
        $digits = preg_replace('/\D+/', '', $phone);

        if (!$digits) {
            return null;
        }

        // Если номер длиннее 11, берём только последние 11 цифр (например, при вводе с кодом страны 007)
        if (strlen($digits) > 11) {
            $digits = substr($digits, -11);
        }

        // Если номер начинается с 8 и имеет длину 11 — заменяем 8 на 7
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits[0] = '7';
        }

        // Если номер из 10 цифр (например, 9000000000) — добавляем 7 в начало
        if (strlen($digits) === 10) {
            $digits = '7' . $digits;
        }

        // Проверяем, что номер теперь имеет корректный формат (11 цифр и начинается с 7)
        if (strlen($digits) === 11 && $digits[0] === '7') {
            return '+' . $digits;
        }

        // Если не удалось привести к корректному виду — возвращаем null
        return null;
    }
}
