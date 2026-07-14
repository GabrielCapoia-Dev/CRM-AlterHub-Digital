<?php

namespace App\Enum;

enum EtapaTipo: string
{
    case Aberta = 'aberta';
    case Ganha = 'ganha';
    case Perdida = 'perdida';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Aberta->value => 'Aberta',
            self::Ganha->value => 'Ganha',
            self::Perdida->value => 'Perdida',
        ];
    }
}
