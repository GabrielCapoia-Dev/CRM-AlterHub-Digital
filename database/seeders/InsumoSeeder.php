<?php

namespace Database\Seeders;

use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Produtos\Insumo;
use App\Models\Status\StatusInsumo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class InsumoSeeder extends Seeder
{
    public function run(): void
    {
        $tipoMap = collect([
            'Oligonucleotideo',
            'Enzima',
            'Reagente',
            'Consumivel',
            'Plastico laboratorial',
            'Controle',
            'Solvente',
            'Meio de transporte',
        ])->mapWithKeys(fn (string $nome) => [
            $nome => TipoInsumo::query()->firstOrCreate(['nome' => $nome]),
        ]);

        $armazenamentoMap = collect([
            'Ambiente controlado',
            'Refrigerado 2-8C',
            'Congelado -20C',
            'Ultrafreezer -80C',
        ])->mapWithKeys(fn (string $nome) => [
            $nome => TipoArmazenamento::query()->firstOrCreate(['nome' => $nome]),
        ]);

        $unidadeMap = collect([
            ['nome' => 'Unidade', 'sigla' => 'un'],
            ['nome' => 'Caixa', 'sigla' => 'cx'],
            ['nome' => 'Kit', 'sigla' => 'kit'],
            ['nome' => 'Mililitro', 'sigla' => 'mL'],
            ['nome' => 'Litro', 'sigla' => 'L'],
            ['nome' => 'Grama', 'sigla' => 'g'],
        ])->mapWithKeys(function (array $dados): array {
            $unidade = TipoUnidadeMedida::query()->updateOrCreate(
                ['nome' => $dados['nome']],
                ['sigla' => $dados['sigla']],
            );

            return [$dados['nome'] => $unidade];
        });

        $statusMap = collect([
            'Ativo',
            'Em homologacao',
            'Bloqueado',
            'Descontinuado',
        ])->mapWithKeys(fn (string $nome) => [
            $nome => StatusInsumo::query()->firstOrCreate(['nome' => $nome]),
        ]);

        $insumos = [
            [
                'codigo_interno' => 'INS-2026-001',
                'nome' => 'Primers sinteticos para qPCR',
                'descricao' => 'Pool de primers validado para alvos respiratorios.',
                'tipo' => 'Oligonucleotideo',
                'armazenamento' => 'Congelado -20C',
                'unidade' => 'Kit',
                'status' => 'Ativo',
                'ncm' => '38229090',
                'custo_referencia' => 420.5000,
                'estoque_minimo' => 8.0000,
                'observacao' => 'Consumo crescente em campanhas sazonais.',
            ],
            [
                'codigo_interno' => 'INS-2026-002',
                'nome' => 'Probes hidrolise FAM/BHQ1',
                'descricao' => 'Sondas para deteccao em paineis multiplex.',
                'tipo' => 'Oligonucleotideo',
                'armazenamento' => 'Congelado -20C',
                'unidade' => 'Kit',
                'status' => 'Ativo',
                'ncm' => '38229090',
                'custo_referencia' => 510.0000,
                'estoque_minimo' => 6.0000,
                'observacao' => 'Material critico para entregas de alto giro.',
            ],
            [
                'codigo_interno' => 'INS-2026-003',
                'nome' => 'Master buffer PCR 5X',
                'descricao' => 'Buffer pronto para rotinas de amplificacao.',
                'tipo' => 'Reagente',
                'armazenamento' => 'Refrigerado 2-8C',
                'unidade' => 'Litro',
                'status' => 'Ativo',
                'ncm' => '38229090',
                'custo_referencia' => 185.4000,
                'estoque_minimo' => 12.0000,
                'observacao' => 'Usado em kits proprios e validacoes internas.',
            ],
            [
                'codigo_interno' => 'INS-2026-004',
                'nome' => 'Ponteiras com filtro 200 uL',
                'descricao' => 'Consumivel esteril para manipulacao de amostras sensiveis.',
                'tipo' => 'Consumivel',
                'armazenamento' => 'Ambiente controlado',
                'unidade' => 'Caixa',
                'status' => 'Ativo',
                'ncm' => '39269090',
                'custo_referencia' => 78.9000,
                'estoque_minimo' => 40.0000,
                'observacao' => 'Lote com maior giro entre molecular e rotina.',
            ],
            [
                'codigo_interno' => 'INS-2026-005',
                'nome' => 'Placa PCR 96 wells optica',
                'descricao' => 'Placa de reacao com filme optico compativel.',
                'tipo' => 'Plastico laboratorial',
                'armazenamento' => 'Ambiente controlado',
                'unidade' => 'Caixa',
                'status' => 'Ativo',
                'ncm' => '39269090',
                'custo_referencia' => 132.7500,
                'estoque_minimo' => 20.0000,
                'observacao' => 'Mantida com cobertura para duas semanas de producao.',
            ],
            [
                'codigo_interno' => 'INS-2026-006',
                'nome' => 'Microtubo criogenico 2 mL',
                'descricao' => 'Tubo para armazenamento em baixa temperatura.',
                'tipo' => 'Plastico laboratorial',
                'armazenamento' => 'Ambiente controlado',
                'unidade' => 'Caixa',
                'status' => 'Ativo',
                'ncm' => '39269090',
                'custo_referencia' => 96.2000,
                'estoque_minimo' => 24.0000,
                'observacao' => 'Apoia biobanco e kits de coleta especial.',
            ],
            [
                'codigo_interno' => 'INS-2026-007',
                'nome' => 'Enzima transcriptase reversa',
                'descricao' => 'Enzima para preparo de cDNA em paineis RT-qPCR.',
                'tipo' => 'Enzima',
                'armazenamento' => 'Congelado -20C',
                'unidade' => 'Kit',
                'status' => 'Ativo',
                'ncm' => '35079039',
                'custo_referencia' => 689.0000,
                'estoque_minimo' => 5.0000,
                'observacao' => 'Item de maior sensibilidade a atraso logistico.',
            ],
            [
                'codigo_interno' => 'INS-2026-008',
                'nome' => 'Controle RNA sintetico',
                'descricao' => 'Controle positivo para validacao de lote.',
                'tipo' => 'Controle',
                'armazenamento' => 'Ultrafreezer -80C',
                'unidade' => 'Kit',
                'status' => 'Ativo',
                'ncm' => '38229090',
                'custo_referencia' => 355.6000,
                'estoque_minimo' => 4.0000,
                'observacao' => 'Mantido em estoque seguro para liberacao de lotes.',
            ],
            [
                'codigo_interno' => 'INS-2026-009',
                'nome' => 'Solucao de lavagem ELISA',
                'descricao' => 'Solucao concentrada para rotina ELISA.',
                'tipo' => 'Reagente',
                'armazenamento' => 'Ambiente controlado',
                'unidade' => 'Litro',
                'status' => 'Ativo',
                'ncm' => '34029039',
                'custo_referencia' => 64.3000,
                'estoque_minimo' => 15.0000,
                'observacao' => 'Uso mais intenso em campanhas de inflamacoes sazonais.',
            ],
            [
                'codigo_interno' => 'INS-2026-010',
                'nome' => 'Tampao de lisis magnetica',
                'descricao' => 'Tampao usado no modulo automatizado de extracao.',
                'tipo' => 'Reagente',
                'armazenamento' => 'Refrigerado 2-8C',
                'unidade' => 'Litro',
                'status' => 'Ativo',
                'ncm' => '38229090',
                'custo_referencia' => 244.8000,
                'estoque_minimo' => 10.0000,
                'observacao' => 'Consumo indexado ao equipamento Auto X8.',
            ],
            [
                'codigo_interno' => 'INS-2026-011',
                'nome' => 'Beads magneticas de silica',
                'descricao' => 'Microparticulas para extracao automatizada.',
                'tipo' => 'Reagente',
                'armazenamento' => 'Refrigerado 2-8C',
                'unidade' => 'Kit',
                'status' => 'Em homologacao',
                'ncm' => '38229090',
                'custo_referencia' => 780.0000,
                'estoque_minimo' => 3.0000,
                'observacao' => 'Novo fornecedor em avaliacao para ganho de margem.',
            ],
            [
                'codigo_interno' => 'INS-2026-012',
                'nome' => 'Isopropanol grau molecular',
                'descricao' => 'Solvente de alta pureza para preparo de amostra.',
                'tipo' => 'Solvente',
                'armazenamento' => 'Ambiente controlado',
                'unidade' => 'Litro',
                'status' => 'Ativo',
                'ncm' => '29051220',
                'custo_referencia' => 58.9000,
                'estoque_minimo' => 18.0000,
                'observacao' => 'Mantido com dupla cobertura por risco de ruptura.',
            ],
            [
                'codigo_interno' => 'INS-2026-013',
                'nome' => 'Meio de transporte celular',
                'descricao' => 'Solucao de estabilizacao para coleta especial.',
                'tipo' => 'Meio de transporte',
                'armazenamento' => 'Refrigerado 2-8C',
                'unidade' => 'Mililitro',
                'status' => 'Bloqueado',
                'ncm' => '38229090',
                'custo_referencia' => 11.2500,
                'estoque_minimo' => 150.0000,
                'observacao' => 'Bloqueado temporariamente por revisao de estabilidade.',
            ],
            [
                'codigo_interno' => 'INS-2026-014',
                'nome' => 'Gelo reutilizavel logistico',
                'descricao' => 'Elemento refrigerante para envio controlado.',
                'tipo' => 'Consumivel',
                'armazenamento' => 'Congelado -20C',
                'unidade' => 'Unidade',
                'status' => 'Ativo',
                'ncm' => '38249989',
                'custo_referencia' => 14.5000,
                'estoque_minimo' => 80.0000,
                'observacao' => 'Apoia expedicao de kits com sensibilidade termica.',
            ],
        ];

        foreach ($insumos as $index => $dados) {
            $insumo = Insumo::query()->firstOrNew([
                'codigo_interno' => $dados['codigo_interno'],
            ]);

            $insumo->fill([
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'],
                'tipo_insumo_id' => $tipoMap[$dados['tipo']]->id,
                'tipo_armazenamento_id' => $armazenamentoMap[$dados['armazenamento']]->id,
                'tipo_unidade_medida_id' => $unidadeMap[$dados['unidade']]->id,
                'status_insumo_id' => $statusMap[$dados['status']]->id,
                'ncm' => $dados['ncm'],
                'custo_referencia' => $dados['custo_referencia'],
                'estoque_minimo' => $dados['estoque_minimo'],
                'observacao' => $dados['observacao'],
            ]);

            $insumo->saveQuietly();

            $createdAt = CarbonImmutable::now()->subDays(140 - ($index * 5))->setTime(10, 0);
            $updatedAt = $createdAt->addDays(2);

            $this->syncTimestamps($insumo, $createdAt, $updatedAt);
        }

        $this->command->info('InsumoSeeder concluiu ' . count($insumos) . ' insumos.');
    }

    private function syncTimestamps(Model $model, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): void
    {
        $model->timestamps = false;
        $model->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ])->saveQuietly();
        $model->timestamps = true;
    }
}
