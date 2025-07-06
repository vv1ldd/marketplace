<?php

namespace App\Helpers;

class GenerateSecureCode
{
    /**
     * @return string
     * @throws \Random\RandomException
     */
    public static function generate(): string
    {
        return '1GROS-' . self::generateUniqueSegment() . '-' . self::generateUniqueSegment() . '-' . self::generateUniqueSegment();

    }

    /**
     * @param int $length
     * @return string
     * @throws \Random\RandomException
     */
    private static function generateUniqueSegment(int $length = 4): string
    {
        $bytes = random_bytes(ceil($length / 2));
        return strtoupper(substr(bin2hex($bytes), 0, $length));
    }
}
