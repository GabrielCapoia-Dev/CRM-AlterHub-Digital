<?php

namespace App\Rules;

use App\Support\Fiscal\TaxIdentifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueNormalizedTaxIdentifierRule implements ValidationRule
{
    public function __construct(
        protected string $table,
        protected string $column = 'cnpj',
        protected int|string|null $ignoreValue = null,
        protected string $ignoreColumn = 'id',
        protected string $message = 'Este documento fiscal ja esta cadastrado.',
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalized = TaxIdentifier::normalizeForLookup($value);

        if ($normalized === null) {
            return;
        }

        $query = DB::table($this->table)
            ->whereRaw(TaxIdentifier::comparableExpression($this->column) . ' = ?', [$normalized]);

        if ($this->ignoreValue !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreValue);
        }

        if ($query->exists()) {
            $fail($this->message);
        }
    }
}
