<?php

namespace App\Services\CRM;

use App\Enum\EtapaTipo;
use App\Models\Acesso\User;
use App\Models\Etapa;
use App\Models\Oportunidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class OportunidadeWorkflowService
{
    public function reabrir(
        Oportunidade $oportunidade,
        Etapa $etapaDestino,
        User $actor,
        string $justificativa,
    ): Oportunidade {
        Gate::forUser($actor)->authorize('reopen', $oportunidade);

        return DB::transaction(function () use ($oportunidade, $etapaDestino, $actor, $justificativa): Oportunidade {
            $oportunidade = Oportunidade::query()->lockForUpdate()->findOrFail($oportunidade->id);
            $etapaDestino = Etapa::query()->lockForUpdate()->findOrFail($etapaDestino->id);

            if ($oportunidade->isConverted()) {
                throw ValidationException::withMessages([
                    'oportunidade' => 'Oportunidade convertida nao pode ser reaberta.',
                ]);
            }

            if (! $oportunidade->isLost() || $etapaDestino->tipo !== EtapaTipo::Aberta) {
                throw ValidationException::withMessages([
                    'etapa_id' => 'Selecione uma etapa aberta para reabrir uma oportunidade perdida.',
                ]);
            }

            if (trim($justificativa) === '') {
                throw ValidationException::withMessages(['justificativa' => 'Informe a justificativa da reabertura.']);
            }

            $oportunidade->forceFill([
                'etapa_id' => $etapaDestino->id,
                'motivo_fechamento' => null,
                'notas' => trim(implode("\n", array_filter([
                    $oportunidade->notas,
                    sprintf('[%s] Reaberta por %s: %s', now()->format('d/m/Y H:i'), $actor->name, trim($justificativa)),
                ]))),
            ])->save();

            return $oportunidade->fresh(['etapa', 'oportunidadeMovimentacoes']);
        });
    }
}
