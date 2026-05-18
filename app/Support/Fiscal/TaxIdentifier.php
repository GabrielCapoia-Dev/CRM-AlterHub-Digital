<?php

namespace App\Support\Fiscal;

class TaxIdentifier
{
    public static function formatForDisplay(mixed $value): ?string
    {
        return static::normalizeForStorage($value);
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        if (static::shouldTreatAsBrazilianCnpj($trimmed)) {
            $digits = static::digitsOnly($trimmed);

            if (static::isValidBrazilianCnpj($digits)) {
                return static::formatBrazilianCnpj($digits);
            }
        }

        return static::normalizeInternationalIdentifier($trimmed);
    }

    public static function normalizeForLookup(mixed $value): ?string
    {
        $normalized = static::normalizeForStorage($value);

        if ($normalized === null) {
            return null;
        }

        $comparable = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($normalized));

        return $comparable === '' ? null : $comparable;
    }

    public static function isValid(mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $trimmed = trim((string) $value);

        if (static::shouldTreatAsBrazilianCnpj($trimmed)) {
            return static::isValidBrazilianCnpj(static::digitsOnly($trimmed));
        }

        return static::isValidInternationalIdentifier($trimmed);
    }

    public static function validationMessage(mixed $value): string
    {
        if (static::shouldTreatAsBrazilianCnpj((string) $value)) {
            return 'Informe um CNPJ brasileiro valido.';
        }

        return 'Informe uma identificacao fiscal valida usando apenas letras, numeros, pontos, barras, hifens e espacos.';
    }

    public static function comparableExpression(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(UPPER({$column}), '.', ''), '/', ''), '-', ''), ' ', '')";
    }

    protected static function normalizeInternationalIdentifier(string $value): ?string
    {
        $normalized = preg_replace('/\s+/', ' ', mb_strtoupper(trim($value)));

        return $normalized === '' ? null : $normalized;
    }

    protected static function isValidInternationalIdentifier(string $value): bool
    {
        $normalized = static::normalizeInternationalIdentifier($value);

        if ($normalized === null) {
            return false;
        }

        $comparable = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';

        if (strlen($comparable) < 3) {
            return false;
        }

        return preg_match('/^[A-Z0-9][A-Z0-9 .\/-]{0,39}$/', $normalized) === 1;
    }

    protected static function shouldTreatAsBrazilianCnpj(string $value): bool
    {
        if (preg_match('/^[0-9.\-\/\s]+$/', $value) !== 1) {
            return false;
        }

        return strlen(static::digitsOnly($value)) === 14;
    }

    protected static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    protected static function formatBrazilianCnpj(string $digits): string
    {
        return vsprintf('%s%s.%s%s%s.%s%s%s/%s%s%s%s-%s%s', str_split($digits));
    }

    protected static function isValidBrazilianCnpj(string $digits): bool
    {
        if (strlen($digits) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1+$/', $digits) === 1) {
            return false;
        }

        $calculateDigit = static function (string $number, int $length): int {
            $sum = 0;
            $position = $length - 7;

            for ($index = $length; $index >= 1; $index--) {
                $sum += (int) $number[$length - $index] * $position--;

                if ($position < 2) {
                    $position = 9;
                }
            }

            $result = $sum % 11;

            return $result < 2 ? 0 : 11 - $result;
        };

        return ((int) $digits[12] === $calculateDigit($digits, 12))
            && ((int) $digits[13] === $calculateDigit($digits, 13));
    }
}
