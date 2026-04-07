<?php

namespace Database\Seeders;

use App\Models\Categorias\CategoriaProduto;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\ProdutoPricingCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class ProdutoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = collect([
            'Diagnóstico Molecular',
            'Coleta e Transporte',
            'Imunoensaios',
            'Equipamentos',
            'Consumíveis e Laboratório',
            'Software e Rastreabilidade',
        ])->mapWithKeys(fn (string $nome) => [
            $nome => CategoriaProduto::query()->firstOrCreate(['nome' => $nome]),
        ]);

        $insumos = Insumo::query()->pluck('id', 'codigo_interno');
        $calculator = app(ProdutoPricingCalculator::class);

        $produtos = [
            [
                'codigo_interno' => 'UBT-QPCR-RESP-96',
                'nome' => 'Kit RT-qPCR Respiratorio 96 reacoes',
                'descricao' => 'Painel para triagem molecular respiratoria com controles dedicados.',
                'categoria' => 'Diagnóstico Molecular',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 6.0000,
                'preco_tabela' => 1280.00,
                'preco_minimo' => 1120.00,
                'observacao' => 'Linha com maior giro no canal hospitalar.',
                'componentes' => $this->componentes($calculator, margem: 28, frete: 24, despesasGerais: 18),
                'insumos' => [
                    ['codigo' => 'INS-2026-001', 'quantidade' => 1.0000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-002', 'quantidade' => 1.0000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-003', 'quantidade' => 0.5000, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-007', 'quantidade' => 0.2000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-008', 'quantidade' => 0.1000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-005', 'quantidade' => 1.0000, 'unidade_consumo' => 'cx'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-VTM-100',
                'nome' => 'Meio de Transporte Viral 100 mL',
                'descricao' => 'Meio estabilizante para coleta e transporte de amostras.',
                'categoria' => 'Coleta e Transporte',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'cx',
                'ncm' => '38220090',
                'estoque_minimo' => 12.0000,
                'preco_tabela' => 345.00,
                'preco_minimo' => 298.00,
                'observacao' => 'Usado em campanhas de coleta refrigerada.',
                'componentes' => $this->componentes($calculator, margem: 22, frete: 12, despesasGerais: 10),
                'insumos' => [
                    ['codigo' => 'INS-2026-013', 'quantidade' => 100.0000, 'unidade_consumo' => 'mL'],
                    ['codigo' => 'INS-2026-006', 'quantidade' => 1.0000, 'unidade_consumo' => 'cx'],
                    ['codigo' => 'INS-2026-014', 'quantidade' => 2.0000, 'unidade_consumo' => 'un'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-ELISA-IL6',
                'nome' => 'Kit ELISA IL-6',
                'descricao' => 'Kit quantitativo para biomarcadores inflamatorios.',
                'categoria' => 'Imunoensaios',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 4.0000,
                'preco_tabela' => 1890.00,
                'preco_minimo' => 1640.00,
                'observacao' => 'Ticket médio alto e ciclo de venda consultivo.',
                'componentes' => $this->componentes($calculator, margem: 30, frete: 22, despesasGerais: 20),
                'insumos' => [
                    ['codigo' => 'INS-2026-009', 'quantidade' => 0.6000, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-003', 'quantidade' => 0.2500, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-004', 'quantidade' => 0.3000, 'unidade_consumo' => 'cx'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-AUTO-X8',
                'nome' => 'Sistema de Extracao Auto X8',
                'descricao' => 'Equipamento automatizado para extracao de acidos nucleicos.',
                'categoria' => 'Equipamentos',
                'marca' => 'AlterHub Automation',
                'status' => 'ativo',
                'unidade_medida' => 'un',
                'ncm' => '90278099',
                'estoque_minimo' => 1.0000,
                'preco_tabela' => 38500.00,
                'preco_minimo' => 34900.00,
                'observacao' => 'Negociação normalmente envolve instalação e treinamento.',
                'componentes' => $this->componentes($calculator, margem: 18, frete: 780, despesasGerais: 620, comissao: 4),
                'insumos' => [],
            ],
            [
                'codigo_interno' => 'UBT-PCR-MIX-2X',
                'nome' => 'Master Mix qPCR 2X',
                'descricao' => 'Master mix pronta para preparo rapido de ensaios.',
                'categoria' => 'Diagnóstico Molecular',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 8.0000,
                'preco_tabela' => 980.00,
                'preco_minimo' => 860.00,
                'observacao' => 'Produto com recorrência em contas regionais.',
                'componentes' => $this->componentes($calculator, margem: 26, frete: 18, despesasGerais: 14),
                'insumos' => [
                    ['codigo' => 'INS-2026-003', 'quantidade' => 0.7000, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-007', 'quantidade' => 0.1500, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-001', 'quantidade' => 0.4000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-002', 'quantidade' => 0.2000, 'unidade_consumo' => 'kit'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-CRYO-81',
                'nome' => 'Criobox 81 posicoes',
                'descricao' => 'Caixa de armazenamento para biobanco com identificacao por grade.',
                'categoria' => 'Consumíveis e Laboratório',
                'marca' => 'AlterHub Labware',
                'status' => 'ativo',
                'unidade_medida' => 'un',
                'ncm' => '39269090',
                'estoque_minimo' => 10.0000,
                'preco_tabela' => 210.00,
                'preco_minimo' => 178.00,
                'observacao' => 'Produto complementar em contas com biobanco.',
                'componentes' => $this->componentes($calculator, margem: 24, frete: 8, despesasGerais: 6),
                'insumos' => [
                    ['codigo' => 'INS-2026-006', 'quantidade' => 1.0000, 'unidade_consumo' => 'cx'],
                    ['codigo' => 'INS-2026-014', 'quantidade' => 1.0000, 'unidade_consumo' => 'un'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-VET-MPX',
                'nome' => 'Kit Multiplex Veterinario',
                'descricao' => 'Painel multiplex para triagem molecular veterinaria.',
                'categoria' => 'Diagnóstico Molecular',
                'marca' => 'UniBiotech Vet',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 4.0000,
                'preco_tabela' => 1640.00,
                'preco_minimo' => 1420.00,
                'observacao' => 'Venda consultiva com lote menor e margem superior.',
                'componentes' => $this->componentes($calculator, margem: 30, frete: 26, despesasGerais: 18),
                'insumos' => [
                    ['codigo' => 'INS-2026-001', 'quantidade' => 0.8000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-002', 'quantidade' => 0.8000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-003', 'quantidade' => 0.5000, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-007', 'quantidade' => 0.1500, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-008', 'quantidade' => 0.1000, 'unidade_consumo' => 'kit'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-CONTROL-POS',
                'nome' => 'Painel de Controles Positivos',
                'descricao' => 'Conjunto de controles positivos para liberacao e rotina.',
                'categoria' => 'Diagnóstico Molecular',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 5.0000,
                'preco_tabela' => 760.00,
                'preco_minimo' => 660.00,
                'observacao' => 'Usado para apoio à liberação de lotes internos.',
                'componentes' => $this->componentes($calculator, margem: 25, frete: 15, despesasGerais: 10),
                'insumos' => [
                    ['codigo' => 'INS-2026-008', 'quantidade' => 0.5000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-007', 'quantidade' => 0.0500, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-005', 'quantidade' => 0.3000, 'unidade_consumo' => 'cx'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-MICROTUBE-2ML',
                'nome' => 'Microtubos Esteril 2 mL',
                'descricao' => 'Caixa de microtubos esteril para coleta e armazenamento.',
                'categoria' => 'Consumíveis e Laboratório',
                'marca' => 'AlterHub Labware',
                'status' => 'ativo',
                'unidade_medida' => 'cx',
                'ncm' => '39269090',
                'estoque_minimo' => 20.0000,
                'preco_tabela' => 155.00,
                'preco_minimo' => 134.00,
                'observacao' => 'Item de apoio para cross-sell em contas laboratoriais.',
                'componentes' => $this->componentes($calculator, margem: 20, frete: 6, despesasGerais: 5),
                'insumos' => [
                    ['codigo' => 'INS-2026-006', 'quantidade' => 1.0000, 'unidade_consumo' => 'cx'],
                ],
            ],
            [
                'codigo_interno' => 'UBT-LIMS-TRACK',
                'nome' => 'Modulo de Rastreabilidade de Coleta',
                'descricao' => 'Licenca para rastrear lote, coleta e validacao de processo.',
                'categoria' => 'Software e Rastreabilidade',
                'marca' => 'AlterHub Digital',
                'status' => 'em_registro',
                'unidade_medida' => 'licenca',
                'ncm' => '85234990',
                'estoque_minimo' => 1.0000,
                'preco_tabela' => 12400.00,
                'preco_minimo' => 11200.00,
                'observacao' => 'Produto comercializado com onboarding e parametrização.',
                'componentes' => $this->componentes($calculator, margem: 35, frete: 0, despesasGerais: 850, comissao: 6, cofins: 7.6, pis: 1.65, icms: 0),
                'insumos' => [],
            ],
            [
                'codigo_interno' => 'UBT-MPX-RESP-24',
                'nome' => 'Painel Multiplex Respiratorio 24 amostras',
                'descricao' => 'Painel compacto para laboratorios regionais e rotina agil.',
                'categoria' => 'Diagnóstico Molecular',
                'marca' => 'UniBiotech',
                'status' => 'ativo',
                'unidade_medida' => 'kit',
                'ncm' => '38220090',
                'estoque_minimo' => 8.0000,
                'preco_tabela' => 870.00,
                'preco_minimo' => 760.00,
                'observacao' => 'Versão enxuta para contas de menor volume.',
                'componentes' => $this->componentes($calculator, margem: 24, frete: 18, despesasGerais: 12),
                'insumos' => [
                    ['codigo' => 'INS-2026-001', 'quantidade' => 0.4000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-002', 'quantidade' => 0.4000, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-003', 'quantidade' => 0.2500, 'unidade_consumo' => 'L'],
                    ['codigo' => 'INS-2026-007', 'quantidade' => 0.0800, 'unidade_consumo' => 'kit'],
                    ['codigo' => 'INS-2026-008', 'quantidade' => 0.0500, 'unidade_consumo' => 'kit'],
                ],
            ],
        ];

        foreach ($produtos as $index => $dados) {
            $prepared = $calculator->prepareForPersistence([
                'codigo_interno' => $dados['codigo_interno'],
                'categoria_produto_id' => $categorias[$dados['categoria']]->id,
                'nome' => $dados['nome'],
                'marca' => $dados['marca'],
                'descricao' => $dados['descricao'],
                'observacao' => $dados['observacao'],
                'unidade_medida' => $dados['unidade_medida'],
                'status' => $dados['status'],
                'ncm' => $dados['ncm'],
                'estoque_minimo' => $dados['estoque_minimo'],
                'preco_tabela' => $dados['preco_tabela'],
                'preco_minimo' => $dados['preco_minimo'],
                'produtoComponentesCusto' => $dados['componentes'],
                'produtoInsumos' => collect($dados['insumos'])
                    ->map(fn (array $item): array => [
                        'insumo_id' => $insumos[$item['codigo']] ?? null,
                        'quantidade' => $item['quantidade'],
                        'unidade_consumo' => $item['unidade_consumo'],
                    ])
                    ->all(),
            ]);

            $produto = Produto::query()->firstOrNew([
                'codigo_interno' => $dados['codigo_interno'],
            ]);

            $produto->fill(Arr::except($prepared, ['produtoInsumos', 'produtoComponentesCusto']));
            $produto->saveQuietly();

            $produto->produtoInsumos()->delete();
            $produto->produtoInsumos()->createMany($prepared['produtoInsumos']);

            $produto->produtoComponentesCusto()->delete();
            $produto->produtoComponentesCusto()->createMany($prepared['produtoComponentesCusto']);

            $createdAt = CarbonImmutable::now()->subDays(110 - ($index * 4))->setTime(11, 30);
            $updatedAt = $createdAt->addDays(1);

            $this->syncTimestamps($produto, $createdAt, $updatedAt);
        }

        $this->command->info('ProdutoSeeder cadastrou ' . count($produtos) . ' produtos CRM.');
    }

    private function componentes(
        ProdutoPricingCalculator $calculator,
        float $margem,
        float $frete,
        float $despesasGerais,
        float $comissao = 5.0,
        float $cofins = 7.6,
        float $pis = 1.65,
        float $icms = 12.0,
    ): array {
        return collect($calculator->defaultComponentes())
            ->map(function (array $item) use ($margem, $frete, $despesasGerais, $comissao, $cofins, $pis, $icms): array {
                $item['valor'] = match ($item['nome']) {
                    'COFINS' => $cofins,
                    'PIS' => $pis,
                    'ICMS' => $icms,
                    'Comissão' => $comissao,
                    'Margem' => $margem,
                    'Frete' => $frete,
                    'Despesas gerais' => $despesasGerais,
                    default => 0,
                };

                return $item;
            })
            ->all();
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
