<?php

namespace Database\Seeders;

use App\Models\Produto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

class ProdutoSeeder extends Seeder
{
    public function run(): void
    {
        $produtos = [
            [
                'codigo_interno' => 'UBT-QPCR-RESP-96',
                'nome' => 'Kit RT-qPCR Respiratorio 96 reacoes',
                'descricao' => 'Painel para triagem molecular respiratoria com controles dedicados.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 1280.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-VTM-100',
                'nome' => 'Meio de Transporte Viral 100 mL',
                'descricao' => 'Meio estabilizante para coleta e transporte de amostras.',
                'unidade_medida' => 'cx',
                'preco_tabela' => 345.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-ELISA-IL6',
                'nome' => 'Kit ELISA IL-6',
                'descricao' => 'Kit quantitativo para biomarcadores inflamatorios.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 1890.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-AUTO-X8',
                'nome' => 'Sistema de Extracao Auto X8',
                'descricao' => 'Equipamento automatizado para extracao de acidos nucleicos.',
                'unidade_medida' => 'un',
                'preco_tabela' => 38500.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-PCR-MIX-2X',
                'nome' => 'Master Mix qPCR 2X',
                'descricao' => 'Master mix pronta para preparo rapido de ensaios.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 980.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-CRYO-81',
                'nome' => 'Criobox 81 posicoes',
                'descricao' => 'Caixa de armazenamento para biobanco com identificacao por grade.',
                'unidade_medida' => 'un',
                'preco_tabela' => 210.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-VET-MPX',
                'nome' => 'Kit Multiplex Veterinario',
                'descricao' => 'Painel multiplex para triagem molecular veterinaria.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 1640.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-CONTROL-POS',
                'nome' => 'Painel de Controles Positivos',
                'descricao' => 'Conjunto de controles positivos para liberacao e rotina.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 760.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-MICROTUBE-2ML',
                'nome' => 'Microtubos Esteril 2 mL',
                'descricao' => 'Caixa de microtubos esteril para coleta e armazenamento.',
                'unidade_medida' => 'cx',
                'preco_tabela' => 155.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-LIMS-TRACK',
                'nome' => 'Modulo de Rastreabilidade de Coleta',
                'descricao' => 'Licenca para rastrear lote, coleta e validacao de processo.',
                'unidade_medida' => 'licenca',
                'preco_tabela' => 12400.00,
                'ativo' => true,
            ],
            [
                'codigo_interno' => 'UBT-MPX-RESP-24',
                'nome' => 'Painel Multiplex Respiratorio 24 amostras',
                'descricao' => 'Painel compacto para laboratorios regionais e rotina agil.',
                'unidade_medida' => 'kit',
                'preco_tabela' => 870.00,
                'ativo' => true,
            ],
        ];

        foreach ($produtos as $index => $dados) {
            $produto = Produto::query()->firstOrNew([
                'codigo_interno' => $dados['codigo_interno'],
            ]);

            $produto->fill([
                'nome' => $dados['nome'],
                'descricao' => $dados['descricao'],
                'unidade_medida' => $dados['unidade_medida'],
                'preco_tabela' => $dados['preco_tabela'],
                'ativo' => $dados['ativo'],
            ]);

            $produto->saveQuietly();

            $createdAt = CarbonImmutable::now()->subDays(110 - ($index * 4))->setTime(11, 30);
            $updatedAt = $createdAt->addDays(1);

            $this->syncTimestamps($produto, $createdAt, $updatedAt);
        }

        $this->command->info('ProdutoSeeder cadastrou ' . count($produtos) . ' produtos CRM.');
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
