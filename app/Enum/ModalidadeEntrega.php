<?php

namespace App\Enum;

enum ModalidadeEntrega: string
{
    case Retirada = 'retirada';
    case EntregaPropria = 'entrega_propria';
    case Transportadora = 'transportadora';
    case Correios = 'correios';
    case Digital = 'digital';

    public function label(): string
    {
        return match ($this) {
            self::Retirada => 'Retirada pelo cliente',
            self::EntregaPropria => 'Entrega propria',
            self::Transportadora => 'Transportadora',
            self::Correios => 'Correios',
            self::Digital => 'Entrega digital',
        };
    }

    public function requiresCarrier(): bool
    {
        return $this === self::Transportadora;
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
