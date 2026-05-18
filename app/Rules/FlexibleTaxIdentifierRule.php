<?php

namespace App\Rules;

use App\Support\Fiscal\TaxIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FlexibleTaxIdentifierRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        if (TaxIdentifier::isValid($value)) {
            return;
        }

        $fail(TaxIdentifier::validationMessage($value));
    }
}
