<?php

namespace App\Console\Commands;

use App\Services\Produtos\EstoqueService;
use Illuminate\Console\Command;

class AuditarEstoque extends Command
{
    protected $signature = 'estoque:auditar {--corrigir : Reconciliar os saldos indexados com o razao e as reservas}';

    protected $description = 'Compara os saldos fisicos e reservados indexados com suas fontes imutaveis';

    public function handle(EstoqueService $estoqueService): int
    {
        $corrigir = (bool) $this->option('corrigir');
        $resultado = $estoqueService->auditar($corrigir);
        $total = count($resultado['produtos']) + count($resultado['insumos']);

        if ($total === 0) {
            $this->info('Estoque reconciliado: nenhuma divergencia encontrada.');

            return self::SUCCESS;
        }

        foreach (['produtos', 'insumos'] as $tipo) {
            if ($resultado[$tipo] === []) {
                continue;
            }

            $this->warn(ucfirst($tipo).':');
            $this->table(array_keys($resultado[$tipo][0]), $resultado[$tipo]);
        }

        if ($corrigir) {
            $this->info("{$total} divergencia(s) reconciliada(s).");

            return self::SUCCESS;
        }

        $this->error("{$total} divergencia(s) encontrada(s). Execute novamente com --corrigir apos revisar o relatorio.");

        return self::FAILURE;
    }
}
