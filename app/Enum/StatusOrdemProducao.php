<?php

namespace App\Enum;

enum StatusOrdemProducao: string
{
    case Planejada = 'planejada';
    case Reservada = 'reservada';
    case EmProducao = 'em_producao';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Planejada => 'Planejada',
            self::Reservada => 'Insumos reservados',
            self::EmProducao => 'Em producao',
            self::Concluida => 'Concluida',
            self::Cancelada => 'Cancelada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Planejada => 'gray',
            self::Reservada => 'info',
            self::EmProducao => 'warning',
            self::Concluida => 'success',
            self::Cancelada => 'danger',
        };
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Concluida, self::Cancelada], true);
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
