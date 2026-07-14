<?php

namespace Tests\Unit;

use App\Filament\Exports\Support\SpreadsheetSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpreadsheetSanitizerTest extends TestCase
{
    #[DataProvider('dangerousValues')]
    public function test_it_prefixes_formula_like_text(string $value): void
    {
        $this->assertSame("'{$value}", SpreadsheetSanitizer::sanitize($value));
    }

    public static function dangerousValues(): array
    {
        return [
            'equals' => ['=HYPERLINK("https://example.test")'],
            'plus' => ['+SUM(1,1)'],
            'minus' => ['-2+3'],
            'at' => ['@SUM(1,1)'],
            'leading spaces' => ['  =1+1'],
            'leading tab' => ["\t=1+1"],
            'leading unicode format marker' => ["\u{FEFF}=1+1"],
        ];
    }

    public function test_it_preserves_safe_strings_and_non_strings(): void
    {
        $this->assertSame('Produto seguro', SpreadsheetSanitizer::sanitize('Produto seguro'));
        $this->assertSame(12.5, SpreadsheetSanitizer::sanitize(12.5));
        $this->assertSame(-12.5, SpreadsheetSanitizer::sanitize(-12.5));
        $this->assertNull(SpreadsheetSanitizer::sanitize(null));
    }
}
