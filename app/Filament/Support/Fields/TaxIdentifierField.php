<?php

namespace App\Filament\Support\Fields;

use App\Rules\FlexibleTaxIdentifierRule;
use App\Support\Fiscal\TaxIdentifier;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;

class TaxIdentifierField
{
    public static function make(string $name = 'cnpj', ?string $label = null): TextInput
    {
        return TextInput::make($name)
            ->label($label ?? 'CNPJ / identificacao fiscal')
            ->maxLength(40)
            ->placeholder('00.000.000/0001-00 ou VAT/Tax ID')
            ->helperText('Aceita CNPJ brasileiro com ou sem mascara, ou identificacao fiscal internacional com letras, numeros, pontos, barras, hifens e espacos.')
            ->live(onBlur: true)
            ->rule(new FlexibleTaxIdentifierRule())
            ->formatStateUsing(fn ($state): ?string => TaxIdentifier::formatForDisplay($state))
            ->dehydrateStateUsing(fn ($state): ?string => TaxIdentifier::normalizeForStorage($state))
            ->afterStateUpdated(function (?string $state, Set $set) use ($name): void {
                $formatted = TaxIdentifier::formatForDisplay($state);

                if ($formatted !== $state) {
                    $set($name, $formatted);
                }
            });
    }
}
