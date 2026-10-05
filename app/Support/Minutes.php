<?php

namespace App\Support;

/**
 * "12 мин", "3,5 ч" or "нет данных" for a duration in minutes.
 */
final class Minutes
{
    public static function human(?float $minutes): string
    {
        return match (true) {
            $minutes === null => 'нет данных',
            $minutes < 90 => self::number($minutes).' мин',
            default => self::number($minutes / 60).' ч',
        };
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', ' '), '0'), ',');
    }
}
