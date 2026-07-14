<?php

namespace App\Filament\Exports\Support;

use Stringable;

final class SpreadsheetSanitizer
{
    /**
     * Prevent spreadsheet applications from interpreting untrusted text as a formula.
     */
    public static function sanitize(mixed $value): mixed
    {
        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (preg_match('/^[\p{C}\p{Z}\s]*[=+\-@]/u', $value) !== 1) {
            return $value;
        }

        return "'{$value}";
    }
}
