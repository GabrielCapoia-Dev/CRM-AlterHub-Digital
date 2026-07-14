<?php

namespace App\Enum;

enum ProdutoOrigem: string
{
    case Nacional = 'nacional';
    case Importado = 'importado';

    public function label(): string
    {
        return match ($this) {
            self::Nacional => 'Nacional',
            self::Importado => 'Importado',
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
