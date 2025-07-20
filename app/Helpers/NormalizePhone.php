<?php

namespace App\Helpers;

class NormalizePhone
{
    public static function normalize(string $phone): string
    {
        $phone = trim($phone);
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return '+' . $phone;
    }
}
