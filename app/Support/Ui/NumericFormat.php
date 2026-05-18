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

    public static function parse(mixed $value, int $precision = 4): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, $precision);
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(['R$', 'r$', '%'], '', $normalized);
        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';

        if ($normalized === '') {
            return null;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if (($lastComma !== false) && ($lastDot !== false)) {
            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif ($lastComma !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $parts = explode('.', $normalized);

            if (count($parts) > 2) {
                $decimal = array_pop($parts);
                $normalized = implode('', $parts) . '.' . $decimal;
            }

            $normalized = str_replace(',', '', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, $precision);
    }
}
