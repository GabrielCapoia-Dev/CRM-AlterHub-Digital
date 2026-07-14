<?php

namespace App\Enum;

enum VendaStatus: string
{
    case Rascunho = 'rascunho';
    case PendenteAprovacao = 'pendente_aprovacao';
    case Confirmada = 'confirmada';
    case ParcialmenteDespachada = 'parcialmente_despachada';
    case Despachada = 'despachada';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';
    case Recusada = 'recusada';
    case DevolvidaParcial = 'devolvida_parcial';
    case Devolvida = 'devolvida';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::Rascunho->value => 'Rascunho',
            self::PendenteAprovacao->value => 'Pendente de aprovacao',
            self::Confirmada->value => 'Confirmada',
            self::ParcialmenteDespachada->value => 'Parcialmente despachada',
            self::Despachada->value => 'Despachada',
            self::Concluida->value => 'Concluida',
            self::Cancelada->value => 'Cancelada',
            self::Recusada->value => 'Recusada',
            self::DevolvidaParcial->value => 'Devolvida parcialmente',
            self::Devolvida->value => 'Devolvida',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Concluida,
            self::Cancelada,
            self::Recusada,
            self::Devolvida,
        ], true);
    }

    public function allowsCommercialEditing(): bool
    {
        return in_array($this, [self::Rascunho, self::PendenteAprovacao], true);
    }

    public function hasActiveReservation(): bool
    {
        return in_array($this, [
            self::Confirmada,
            self::ParcialmenteDespachada,
        ], true);
    }
}
