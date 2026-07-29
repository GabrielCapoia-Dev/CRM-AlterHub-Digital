<?php

namespace App\Enum;

enum SeparacaoStatus: string
{
    case Aguardando = 'aguardando';
    case Separado = 'separado';
    case EmRomaneio = 'em_romaneio';
    case RetornadoRomaneio = 'retornado_romaneio';
    case Despachado = 'despachado';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Aguardando => 'Aguardando separação',
            self::Separado => 'Separado',
            self::EmRomaneio => 'Em romaneio',
            self::RetornadoRomaneio => 'Retornado do romaneio',
            self::Despachado => 'Despachado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Aguardando => 'warning',
            self::Separado => 'success',
            self::EmRomaneio => 'info',
            self::RetornadoRomaneio => 'danger',
            self::Despachado => 'gray',
        };
    }
}
