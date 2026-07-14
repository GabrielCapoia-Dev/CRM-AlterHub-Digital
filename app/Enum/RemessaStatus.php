<?php

namespace App\Enum;

enum RemessaStatus: string
{
    case Rascunho = 'rascunho';
    case EmSeparacao = 'em_separacao';
    case Pronta = 'pronta';
    case Despachada = 'despachada';
    case Entregue = 'entregue';
    case Cancelada = 'cancelada';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Rascunho->value => 'Rascunho',
            self::EmSeparacao->value => 'Em separacao',
            self::Pronta->value => 'Pronta para envio',
            self::Despachada->value => 'Despachada',
            self::Entregue->value => 'Entregue',
            self::Cancelada->value => 'Cancelada',
        ];
    }
}
