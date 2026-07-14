<?php

namespace App\Enum;

enum ProdutoClassificacao: string
{
    case Revenda = 'revenda';
    case Fabricado = 'fabricado';

    public function label(): string
    {
        return match ($this) {
            self::Revenda => 'Revenda',
            self::Fabricado => 'Fabricado',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
