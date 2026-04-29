<?php

namespace App\Support\Ui;

class NumericFormat
{
    public static function decimal(mixed $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    public static function money(mixed $value, string $prefix = 'R$ '): string
    {
        return $prefix . static::decimal($value);
    }

    public static function percent(mixed $value): string
    {
        return static::decimal($value) . '%';
    }

    public static function input(mixed $value, int $decimals = 2): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return number_format((float) $value, $decimals, '.', '');
    }
}
