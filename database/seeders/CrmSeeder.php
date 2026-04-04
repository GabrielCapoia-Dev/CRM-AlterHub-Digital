<?php

namespace Database\Seeders;

use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeMovimentacao;
use App\Models\OportunidadeProduto;
use App\Models\OportunidadeTarefa;
use App\Models\Produto;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $etapas = $this->seedEtapas();

        /** @var Collection<int, Cliente> $clientes */
        $clientes = Cliente::query()
            ->get()
            ->keyBy('codigo_interno');

        /** @var Collection<int, Produto> $produtos */
        $produtos = Produto::query()
            ->get()
            ->keyBy('codigo_interno');

        /** @var Collection<int, User> $usuarios */
        $usuarios = User::query()
            ->orderBy('id')
            ->get()
            ->keyBy('email');

        $fallbackOwner = $usuarios->first();

        if (! $fallbackOwner) {
            throw new RuntimeException('CrmSeeder requer pelo menos um usuario ativo.');
        }

        $scenarios = $this->opportunityScenarios();

        foreach ($scenarios as $scenario) {
            $cliente = $clientes->get($scenario['cliente_codigo']);

            if (! $cliente) {
                throw new RuntimeException("Cliente {$scenario['cliente_codigo']} nao encontrado para o CRM.");
            }

            $owner = $usuarios->get($scenario['owner_email']) ?? $fallbackOwner;
            $inicio = CarbonImmutable::now()->subDays($scenario['days_ago'])->setTime(9, 0);

            $ultimaEtapa = end($scenario['stage_path']);
            $etapaAtual = $etapas[$ultimaEtapa['slug']] ?? null;

            if (! $etapaAtual) {
                throw new RuntimeException("Etapa {$ultimaEtapa['slug']} nao encontrada.");
            }

            $motivoFechamento = $etapaAtual->fechamento
                ? ($ultimaEtapa['reason'] ?? null)
                : null;

            $oportunidade = Oportunidade::query()->firstOrNew([
                'titulo' => $scenario['titulo'],
                'cliente_id' => $cliente->id,
            ]);

            $oportunidade->fill([
                'etapa_id' => $etapaAtual->id,
                'user_id' => $owner->id,
                'temperatura' => $scenario['temperatura'],
                'valor_estimado' => $scenario['valor_estimado'],
                'motivo_fechamento' => $motivoFechamento,
                'notas' => $scenario['notas'],
            ]);

            $oportunidade->saveQuietly();

            $ultimoEvento = $this->rebuildRelations(
                $oportunidade,
                $scenario,
                $inicio,
                $etapas,
                $produtos,
                $usuarios,
                $owner,
            );

            $this->syncTimestamps($oportunidade, $inicio, $ultimoEvento);
        }

        $this->command->info('CrmSeeder gerou ' . count($scenarios) . ' oportunidades com historico organico.');
    }

    /**
     * @return array<string, Etapa>
     */
    private function seedEtapas(): array
    {
        $etapas = [
            [
                'slug' => 'lead',
                'nome' => 'Lead',
                'ordem' => 1,
                'cor' => '#1d4ed8',
                'fechamento' => false,
            ],
            [
                'slug' => 'qualificado',
                'nome' => 'Qualificado',
                'ordem' => 2,
                'cor' => '#0f766e',
                'fechamento' => false,
            ],
            [
                'slug' => 'proposta',
                'nome' => 'Proposta',
                'ordem' => 3,
                'cor' => '#c2410c',
                'fechamento' => false,
            ],
            [
                'slug' => 'negociacao',
                'nome' => 'Negociacao',
                'ordem' => 4,
                'cor' => '#7c3aed',
                'fechamento' => false,
            ],
            [
                'slug' => 'ganho',
                'nome' => 'Ganho',
                'ordem' => 5,
                'cor' => '#059669',
                'fechamento' => true,
            ],
            [
                'slug' => 'perdido',
                'nome' => 'Perdido',
                'ordem' => 6,
                'cor' => '#be123c',
                'fechamento' => true,
            ],
        ];

        $map = [];

        foreach ($etapas as $index => $dados) {
            $etapa = Etapa::query()->updateOrCreate(
                ['slug' => $dados['slug']],
                [
                    'nome' => $dados['nome'],
                    'ordem' => $dados['ordem'],
                    'cor' => $dados['cor'],
                    'fechamento' => $dados['fechamento'],
                ],
            );

            $createdAt = CarbonImmutable::now()->subDays(95 - ($index * 3))->setTime(8, 0);
            $updatedAt = $createdAt->addDay();

            $this->syncTimestamps($etapa, $createdAt, $updatedAt);

            $map[$dados['slug']] = $etapa;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, Etapa> $etapas
     * @param Collection<int, Produto> $produtos
     * @param Collection<int, User> $usuarios
     */
    private function rebuildRelations(
        Oportunidade $oportunidade,
        array $scenario,
        CarbonImmutable $inicio,
        array $etapas,
        Collection $produtos,
        Collection $usuarios,
        User $owner,
    ): CarbonImmutable {
        $oportunidade->oportunidadeProdutos()->delete();
        $oportunidade->oportunidadeInteracoes()->delete();
        $oportunidade->oportunidadeTarefas()->delete();
        $oportunidade->oportunidadeMovimentacoes()->delete();

        $marcos = [$inicio];

        foreach ($scenario['products'] as $index => $dados) {
            $produto = $produtos->get($dados['codigo']);

            if (! $produto) {
                throw new RuntimeException("Produto {$dados['codigo']} nao encontrado para a oportunidade {$scenario['titulo']}.");
            }

            $instante = $inicio->addDays($dados['offset_days'] ?? ($index + 1))->setTime(11 + ($index % 3), 15);

            $registro = new OportunidadeProduto([
                'oportunidade_id' => $oportunidade->id,
                'produto_id' => $produto->id,
                'preco_negociado' => $dados['preco_negociado'],
                'observacao' => $dados['observacao'],
            ]);

            $this->saveWithTimestamps($registro, $instante, $instante);
            $marcos[] = $instante;
        }

        foreach ($scenario['interactions'] as $dados) {
            $responsavel = $this->resolveUser($usuarios, $dados['user_email'] ?? null, $owner);
            $instante = $inicio->addDays($dados['offset_days'])->setTime($dados['hour'] ?? 10, $dados['minute'] ?? 0);

            $registro = new OportunidadeInteracao([
                'oportunidade_id' => $oportunidade->id,
                'user_id' => $responsavel->id,
                'tipo' => $dados['tipo'],
                'nota' => $dados['nota'],
                'ocorreu_em' => $instante,
            ]);

            $this->saveWithTimestamps($registro, $instante, $instante);
            $marcos[] = $instante;
        }

        foreach ($scenario['tasks'] as $dados) {
            $responsavel = $this->resolveUser($usuarios, $dados['user_email'] ?? null, $owner);
            $instante = $inicio->addDays($dados['offset_days'])->setTime(9, 45);

            $registro = new OportunidadeTarefa([
                'oportunidade_id' => $oportunidade->id,
                'user_id' => $responsavel->id,
                'titulo' => $dados['titulo'],
                'status' => $dados['status'],
                'data_prevista' => $inicio->addDays($dados['due_offset_days'])->toDateString(),
            ]);

            $this->saveWithTimestamps($registro, $instante, $instante);
            $marcos[] = $instante;
        }

        for ($index = 1; $index < count($scenario['stage_path']); $index++) {
            $origem = $scenario['stage_path'][$index - 1];
            $destino = $scenario['stage_path'][$index];

            $etapaOrigem = $etapas[$origem['slug']] ?? null;
            $etapaDestino = $etapas[$destino['slug']] ?? null;

            if (! $etapaOrigem || ! $etapaDestino) {
                throw new RuntimeException("Sequencia de etapas invalida em {$scenario['titulo']}.");
            }

            $instante = $inicio->addDays($destino['offset_days'])->setTime(16, 0);

            $registro = new OportunidadeMovimentacao([
                'oportunidade_id' => $oportunidade->id,
                'user_id' => $owner->id,
                'etapa_origem_id' => $etapaOrigem->id,
                'etapa_destino_id' => $etapaDestino->id,
                'motivo' => $destino['reason'] ?? null,
                'movido_em' => $instante,
            ]);

            $this->saveWithTimestamps($registro, $instante, $instante);
            $marcos[] = $instante;
        }

        return collect($marcos)
            ->sort()
            ->last();
    }

    private function resolveUser(Collection $usuarios, ?string $email, User $fallback): User
    {
        if (! $email) {
            return $fallback;
        }

        return $usuarios->get($email) ?? $fallback;
    }

    private function syncTimestamps(Model $model, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): void
    {
        $model->timestamps = false;
        $payload = ['created_at' => $createdAt];

        if ($model::UPDATED_AT !== null) {
            $payload['updated_at'] = $updatedAt;
        }

        $model->forceFill($payload)->saveQuietly();
        $model->timestamps = true;
    }

    private function saveWithTimestamps(Model $model, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): void
    {
        $model->forceFill(['created_at' => $createdAt]);

        if ($model::UPDATED_AT !== null) {
            $model->forceFill(['updated_at' => $updatedAt]);
        }

        $model->saveQuietly();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opportunityScenarios(): array
    {
        return [
            [
                'titulo' => 'Contrato trimestral de kits RT-qPCR',
                'cliente_codigo' => 'CLI-2026-001',
                'owner_email' => 'admin1@unibiotech.com',
                'temperatura' => 'hot',
                'valor_estimado' => 48500.00,
                'notas' => 'Negociacao avancada para contrato trimestral com treinamento presencial para dois turnos.',
                'days_ago' => 34,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 3],
                    ['slug' => 'proposta', 'offset_days' => 8],
                    ['slug' => 'negociacao', 'offset_days' => 15],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-QPCR-RESP-96',
                        'preco_negociado' => 1210.00,
                        'observacao' => 'Cliente pediu lote inicial com treinamento incluso.',
                        'offset_days' => 4,
                    ],
                    [
                        'codigo' => 'UBT-CONTROL-POS',
                        'preco_negociado' => 730.00,
                        'observacao' => 'Painel de controle atrelado ao contrato trimestral.',
                        'offset_days' => 5,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 1,
                        'tipo' => 'ligacao',
                        'nota' => 'Contato inicial com a coordenacao tecnica para entender volume mensal de exames.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 5,
                        'tipo' => 'visita',
                        'nota' => 'Visita ao laboratorio central para revisar fluxo, TAT e necessidade de treinamento do turno noturno.',
                        'hour' => 14,
                    ],
                    [
                        'offset_days' => 10,
                        'tipo' => 'email',
                        'nota' => 'Enviado resumo tecnico com proposta preliminar de kit RT-qPCR e painel de controles positivos.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 20,
                        'tipo' => 'observacao',
                        'nota' => 'Cliente pediu revisao da franquia minima e condicao de reposicao emergencial.',
                        'hour' => 16,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 9,
                        'titulo' => 'Montar proposta comercial com treinamento presencial',
                        'status' => 'concluida',
                        'due_offset_days' => 12,
                    ],
                    [
                        'offset_days' => 21,
                        'titulo' => 'Enviar revisao com condicao para contrato trimestral',
                        'status' => 'em_andamento',
                        'due_offset_days' => 29,
                    ],
                    [
                        'offset_days' => 27,
                        'titulo' => 'Agendar reuniao final com compras hospitalares',
                        'status' => 'pendente',
                        'due_offset_days' => 35,
                    ],
                ],
            ],
            [
                'titulo' => 'Expansao do painel respiratorio',
                'cliente_codigo' => 'CLI-2026-002',
                'owner_email' => 'admin2@unibiotech.com',
                'temperatura' => 'warm',
                'valor_estimado' => 22400.00,
                'notas' => 'Rede regional buscando padronizacao de painel respiratorio para tres unidades.',
                'days_ago' => 26,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 4],
                    ['slug' => 'proposta', 'offset_days' => 11],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-VTM-100',
                        'preco_negociado' => 332.00,
                        'observacao' => 'Volume previsto para coleta diaria nas tres unidades.',
                        'offset_days' => 3,
                    ],
                    [
                        'codigo' => 'UBT-PCR-MIX-2X',
                        'preco_negociado' => 955.00,
                        'observacao' => 'Cliente sinalizou sensibilidade a custo de reposicao mensal.',
                        'offset_days' => 5,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 2,
                        'tipo' => 'email',
                        'nota' => 'Recebido briefing com numero de amostras por unidade e janela de coleta.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 6,
                        'tipo' => 'ligacao',
                        'nota' => 'Alinhado cronograma de rollout em ondas por filial.',
                        'hour' => 15,
                    ],
                    [
                        'offset_days' => 12,
                        'tipo' => 'observacao',
                        'nota' => 'Financeiro pediu comparativo entre painel compacto e mix tradicional.',
                        'hour' => 16,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 8,
                        'titulo' => 'Consolidar proposta por unidade e faixa de volume',
                        'status' => 'concluida',
                        'due_offset_days' => 10,
                    ],
                    [
                        'offset_days' => 14,
                        'titulo' => 'Enviar comparativo financeiro do painel respiratorio',
                        'status' => 'pendente',
                        'due_offset_days' => 27,
                    ],
                ],
            ],
            [
                'titulo' => 'Implantacao de extracao automatizada em P&D',
                'cliente_codigo' => 'CLI-2026-004',
                'owner_email' => 'usuario1@unibiotech.com',
                'temperatura' => 'hot',
                'valor_estimado' => 81200.00,
                'notas' => 'Projeto ganho com pacote de equipamento, rastreabilidade e treinamento de laboratorio.',
                'days_ago' => 62,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 6],
                    ['slug' => 'proposta', 'offset_days' => 14],
                    ['slug' => 'negociacao', 'offset_days' => 22],
                    [
                        'slug' => 'ganho',
                        'offset_days' => 28,
                        'reason' => 'Projeto aprovado apos validacao tecnica e pacote de treinamento onsite.',
                    ],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-AUTO-X8',
                        'preco_negociado' => 37200.00,
                        'observacao' => 'Equipamento fechado com validacao de processo.',
                        'offset_days' => 9,
                    ],
                    [
                        'codigo' => 'UBT-LIMS-TRACK',
                        'preco_negociado' => 11800.00,
                        'observacao' => 'Modulo vinculado ao rastreio de lote e etapa de liberacao.',
                        'offset_days' => 10,
                    ],
                    [
                        'codigo' => 'UBT-CRYO-81',
                        'preco_negociado' => 198.00,
                        'observacao' => 'Volume adicional aprovado para biobanco interno.',
                        'offset_days' => 11,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 3,
                        'tipo' => 'ligacao',
                        'nota' => 'Reuniao inicial com time de P&D para mapear gargalos de extracao manual.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 11,
                        'tipo' => 'visita',
                        'nota' => 'Demonstracao do Auto X8 com amostras reais e checagem de throughput.',
                        'hour' => 14,
                    ],
                    [
                        'offset_days' => 18,
                        'tipo' => 'email',
                        'nota' => 'Enviado memorial tecnico com escopo de treinamento e matriz de riscos.',
                        'hour' => 9,
                    ],
                    [
                        'offset_days' => 29,
                        'tipo' => 'observacao',
                        'nota' => 'Pedido liberado; cliente solicitou cronograma detalhado de implantacao em tres fases.',
                        'hour' => 17,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 15,
                        'titulo' => 'Enviar proposta final do pacote Auto X8',
                        'status' => 'concluida',
                        'due_offset_days' => 18,
                    ],
                    [
                        'offset_days' => 24,
                        'titulo' => 'Ajustar cronograma de treinamento com operacao',
                        'status' => 'concluida',
                        'due_offset_days' => 27,
                    ],
                    [
                        'offset_days' => 31,
                        'titulo' => 'Confirmar kick-off do onboarding tecnico',
                        'status' => 'concluida',
                        'due_offset_days' => 34,
                    ],
                ],
            ],
            [
                'titulo' => 'Distribuicao de kits ELISA para rede parceira',
                'cliente_codigo' => 'CLI-2026-008',
                'owner_email' => 'usuario2@unibiotech.com',
                'temperatura' => 'cool',
                'valor_estimado' => 18600.00,
                'notas' => 'Conta perdida por vantagem logistica de concorrente com estoque local.',
                'days_ago' => 49,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 3],
                    ['slug' => 'proposta', 'offset_days' => 10],
                    [
                        'slug' => 'perdido',
                        'offset_days' => 17,
                        'reason' => 'Distribuidor escolheu fabricante com prazo de entrega menor para pronta reposicao.',
                    ],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-ELISA-IL6',
                        'preco_negociado' => 1820.00,
                        'observacao' => 'Volume estimado para distribuicao mensal no Sudeste.',
                        'offset_days' => 4,
                    ],
                    [
                        'codigo' => 'UBT-CONTROL-POS',
                        'preco_negociado' => 720.00,
                        'observacao' => 'Cliente queria travar preco por semestre.',
                        'offset_days' => 5,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 2,
                        'tipo' => 'ligacao',
                        'nota' => 'Distribuidor sinalizou boa aderencia tecnica, mas dependencia de janela curta de entrega.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 9,
                        'tipo' => 'email',
                        'nota' => 'Enviada proposta com politica de rebate por volume trimestral.',
                        'hour' => 13,
                    ],
                    [
                        'offset_days' => 18,
                        'tipo' => 'observacao',
                        'nota' => 'Concorrente entrou com consignacao local e reduziu risco logistico percebido.',
                        'hour' => 16,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 11,
                        'titulo' => 'Negociar faixa de rebate para distribuicao regional',
                        'status' => 'concluida',
                        'due_offset_days' => 14,
                    ],
                    [
                        'offset_days' => 19,
                        'titulo' => 'Registrar motivos de perda e plano de retomada',
                        'status' => 'concluida',
                        'due_offset_days' => 21,
                    ],
                ],
            ],
            [
                'titulo' => 'Piloto de painel veterinario multiplex',
                'cliente_codigo' => 'CLI-2026-005',
                'owner_email' => 'usuario3@unibiotech.com',
                'temperatura' => 'warm',
                'valor_estimado' => 14200.00,
                'notas' => 'Oportunidade reaberta apos retomada de verba e nova rodada de testes clinicos.',
                'days_ago' => 54,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 4],
                    ['slug' => 'proposta', 'offset_days' => 10],
                    [
                        'slug' => 'perdido',
                        'offset_days' => 16,
                        'reason' => 'Projeto congelado por falta de verba no trimestre.',
                    ],
                    ['slug' => 'qualificado', 'offset_days' => 26],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-VET-MPX',
                        'preco_negociado' => 1580.00,
                        'observacao' => 'Piloto retomado com lote menor e avaliacao clinica ampliada.',
                        'offset_days' => 7,
                    ],
                    [
                        'codigo' => 'UBT-MICROTUBE-2ML',
                        'preco_negociado' => 149.00,
                        'observacao' => 'Cliente pediu conjunto de coleta para envio interno.',
                        'offset_days' => 8,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 3,
                        'tipo' => 'ligacao',
                        'nota' => 'Diretoria tecnica demonstrou interesse em piloto para triagem molecular veterinaria.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 12,
                        'tipo' => 'email',
                        'nota' => 'Enviada proposta do piloto com cronograma reduzido e lote inicial compacto.',
                        'hour' => 9,
                    ],
                    [
                        'offset_days' => 19,
                        'tipo' => 'observacao',
                        'nota' => 'Conta pausada internamente por contingenciamento orcamentario.',
                        'hour' => 15,
                    ],
                    [
                        'offset_days' => 28,
                        'tipo' => 'ligacao',
                        'nota' => 'Cliente confirmou reabertura da oportunidade apos aprovacao de verba complementar.',
                        'hour' => 11,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 13,
                        'titulo' => 'Revisar escopo do piloto com equipe clinica',
                        'status' => 'concluida',
                        'due_offset_days' => 15,
                    ],
                    [
                        'offset_days' => 20,
                        'titulo' => 'Registrar congelamento orcamentario do trimestre',
                        'status' => 'concluida',
                        'due_offset_days' => 21,
                    ],
                    [
                        'offset_days' => 29,
                        'titulo' => 'Atualizar proposta do piloto com novo volume',
                        'status' => 'pendente',
                        'due_offset_days' => 34,
                    ],
                ],
            ],
            [
                'titulo' => 'Lote emergencial de controles positivos',
                'cliente_codigo' => 'CLI-2026-006',
                'owner_email' => 'usuario4@unibiotech.com',
                'temperatura' => 'hot',
                'valor_estimado' => 6800.00,
                'notas' => 'Lead recem-aberto para atender auditoria interna do cliente.',
                'days_ago' => 12,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-CONTROL-POS',
                        'preco_negociado' => 745.00,
                        'observacao' => 'Demanda imediata com foco em pronta entrega.',
                        'offset_days' => 1,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 1,
                        'tipo' => 'ligacao',
                        'nota' => 'Cliente abriu contato emergencial por conta de auditoria agendada para a proxima semana.',
                        'hour' => 8,
                    ],
                    [
                        'offset_days' => 3,
                        'tipo' => 'email',
                        'nota' => 'Enviado resumo tecnico com disponibilidade de estoque e lead time de expedicao.',
                        'hour' => 10,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 2,
                        'titulo' => 'Validar janela de coleta e expedir amostra de avaliacao',
                        'status' => 'em_andamento',
                        'due_offset_days' => 6,
                    ],
                    [
                        'offset_days' => 4,
                        'titulo' => 'Enviar proposta rapida com opcao de frete prioritario',
                        'status' => 'pendente',
                        'due_offset_days' => 8,
                    ],
                ],
            ],
            [
                'titulo' => 'Escopo revisado para laboratorio universitario',
                'cliente_codigo' => 'CLI-2026-009',
                'owner_email' => 'usuario5@unibiotech.com',
                'temperatura' => 'warm',
                'valor_estimado' => 29800.00,
                'notas' => 'Projeto retornou de negociacao para proposta apos revisao do edital e corte de escopo.',
                'days_ago' => 38,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 5],
                    ['slug' => 'proposta', 'offset_days' => 12],
                    ['slug' => 'negociacao', 'offset_days' => 18],
                    ['slug' => 'proposta', 'offset_days' => 25],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-AUTO-X8',
                        'preco_negociado' => 37900.00,
                        'observacao' => 'Cliente estuda versao completa, mas pode optar por escopo reduzido.',
                        'offset_days' => 8,
                    ],
                    [
                        'codigo' => 'UBT-PCR-MIX-2X',
                        'preco_negociado' => 945.00,
                        'observacao' => 'Mix entrou como opcao para fase inicial do projeto.',
                        'offset_days' => 9,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 4,
                        'tipo' => 'ligacao',
                        'nota' => 'Professor responsavel confirmou interesse, condicionado ao escopo do edital vigente.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 14,
                        'tipo' => 'email',
                        'nota' => 'Enviado pacote de proposta com equipamento, consumiveis e plano de treinamento.',
                        'hour' => 9,
                    ],
                    [
                        'offset_days' => 21,
                        'tipo' => 'visita',
                        'nota' => 'Reuniao presencial mostrou necessidade de reduzir CAPEX e fatiar implantacao.',
                        'hour' => 15,
                    ],
                    [
                        'offset_days' => 26,
                        'tipo' => 'observacao',
                        'nota' => 'Escopo voltou para proposta com foco em fase 1 e opcao de upgrade posterior.',
                        'hour' => 16,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 15,
                        'titulo' => 'Preparar memorial tecnico do edital',
                        'status' => 'concluida',
                        'due_offset_days' => 17,
                    ],
                    [
                        'offset_days' => 22,
                        'titulo' => 'Revisar escopo em formato faseado',
                        'status' => 'em_andamento',
                        'due_offset_days' => 29,
                    ],
                    [
                        'offset_days' => 27,
                        'titulo' => 'Reenviar proposta enxuta para comite academico',
                        'status' => 'pendente',
                        'due_offset_days' => 33,
                    ],
                ],
            ],
            [
                'titulo' => 'Homologacao de criobox para biobanco',
                'cliente_codigo' => 'CLI-2026-010',
                'owner_email' => 'admin1@unibiotech.com',
                'temperatura' => 'warm',
                'valor_estimado' => 11800.00,
                'notas' => 'Oportunidade fechada apos piloto bem-sucedido e validacao de rastreio por lote.',
                'days_ago' => 43,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 4],
                    ['slug' => 'proposta', 'offset_days' => 10],
                    [
                        'slug' => 'ganho',
                        'offset_days' => 20,
                        'reason' => 'Piloto aprovado com boa aderencia ao rastreio por lote e armazenagem segura.',
                    ],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-CRYO-81',
                        'preco_negociado' => 199.00,
                        'observacao' => 'Pedido inclui lote inicial para organizacao de freezer.',
                        'offset_days' => 6,
                    ],
                    [
                        'codigo' => 'UBT-MICROTUBE-2ML',
                        'preco_negociado' => 150.00,
                        'observacao' => 'Cliente combinou compra recorrente para reposicao semestral.',
                        'offset_days' => 7,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 3,
                        'tipo' => 'ligacao',
                        'nota' => 'Primeira conversa sobre reorganizacao do biobanco e necessidade de rastreio por grade.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 9,
                        'tipo' => 'visita',
                        'nota' => 'Visitado ambiente de armazenamento e validado fluxo de identificacao de amostras.',
                        'hour' => 14,
                    ],
                    [
                        'offset_days' => 18,
                        'tipo' => 'email',
                        'nota' => 'Comite aprovou piloto e pediu fechamento com cronograma de entrega.',
                        'hour' => 11,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 11,
                        'titulo' => 'Enviar proposta com lote piloto e grade de rastreio',
                        'status' => 'concluida',
                        'due_offset_days' => 13,
                    ],
                    [
                        'offset_days' => 21,
                        'titulo' => 'Confirmar cronograma de onboarding do biobanco',
                        'status' => 'concluida',
                        'due_offset_days' => 24,
                    ],
                ],
            ],
            [
                'titulo' => 'Padronizacao de coleta para hemocentro',
                'cliente_codigo' => 'CLI-2026-007',
                'owner_email' => 'admin2@unibiotech.com',
                'temperatura' => 'cool',
                'valor_estimado' => 16400.00,
                'notas' => 'Oportunidade em qualificacao para padronizar coleta e rastreabilidade com saude publica.',
                'days_ago' => 21,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 4],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-VTM-100',
                        'preco_negociado' => 336.00,
                        'observacao' => 'Coleta padronizada para polos regionais.',
                        'offset_days' => 3,
                    ],
                    [
                        'codigo' => 'UBT-LIMS-TRACK',
                        'preco_negociado' => 12050.00,
                        'observacao' => 'Modulo cogitado para rastrear cadeia de coleta e transporte.',
                        'offset_days' => 5,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 2,
                        'tipo' => 'ligacao',
                        'nota' => 'Time do hemocentro levantou necessidade de rastrear coleta em polos externos.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 6,
                        'tipo' => 'observacao',
                        'nota' => 'Projeto entrou em analise de viabilidade junto a equipe de TI e logistica.',
                        'hour' => 15,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 5,
                        'titulo' => 'Mapear requisitos de TI para rastreabilidade de coleta',
                        'status' => 'em_andamento',
                        'due_offset_days' => 12,
                    ],
                    [
                        'offset_days' => 8,
                        'titulo' => 'Preparar proposta preliminar para saude publica',
                        'status' => 'pendente',
                        'due_offset_days' => 18,
                    ],
                ],
            ],
            [
                'titulo' => 'Upgrade do laboratorio molecular regional',
                'cliente_codigo' => 'CLI-2026-011',
                'owner_email' => 'usuario1@unibiotech.com',
                'temperatura' => 'hot',
                'valor_estimado' => 33200.00,
                'notas' => 'Cliente avalia combinacao de painel multiplex e mix de coleta para rotina regional.',
                'days_ago' => 19,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 3],
                    ['slug' => 'proposta', 'offset_days' => 7],
                    ['slug' => 'negociacao', 'offset_days' => 12],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-MPX-RESP-24',
                        'preco_negociado' => 842.00,
                        'observacao' => 'Painel compacto pensado para o volume regional atual.',
                        'offset_days' => 4,
                    ],
                    [
                        'codigo' => 'UBT-VTM-100',
                        'preco_negociado' => 334.00,
                        'observacao' => 'Cliente quer condicao diferenciada para reposicao mensal.',
                        'offset_days' => 5,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 1,
                        'tipo' => 'ligacao',
                        'nota' => 'Diretor operacional pediu benchmarking com laboratorios de porte semelhante.',
                        'hour' => 10,
                    ],
                    [
                        'offset_days' => 8,
                        'tipo' => 'email',
                        'nota' => 'Enviada proposta com mix entre painel compacto e meios de transporte.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 13,
                        'tipo' => 'observacao',
                        'nota' => 'Cliente quer validar impacto financeiro em contrato semestral.',
                        'hour' => 16,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 9,
                        'titulo' => 'Revisar proposta com volume semestral',
                        'status' => 'em_andamento',
                        'due_offset_days' => 15,
                    ],
                    [
                        'offset_days' => 14,
                        'titulo' => 'Agendar call com diretoria financeira do laboratorio',
                        'status' => 'pendente',
                        'due_offset_days' => 20,
                    ],
                ],
            ],
            [
                'titulo' => 'Renovacao anual de microtubos e meio de transporte',
                'cliente_codigo' => 'CLI-2026-012',
                'owner_email' => 'usuario2@unibiotech.com',
                'temperatura' => 'cool',
                'valor_estimado' => 9600.00,
                'notas' => 'Conta perdida na renovacao anual, mas com janela de retomada no proximo semestre.',
                'days_ago' => 45,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                    ['slug' => 'qualificado', 'offset_days' => 4],
                    ['slug' => 'proposta', 'offset_days' => 9],
                    [
                        'slug' => 'perdido',
                        'offset_days' => 18,
                        'reason' => 'Comite decidiu manter o fornecedor atual ate o proximo ciclo anual.',
                    ],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-MICROTUBE-2ML',
                        'preco_negociado' => 147.00,
                        'observacao' => 'Comparativo de caixa anual com gatilho de reposicao.',
                        'offset_days' => 5,
                    ],
                    [
                        'codigo' => 'UBT-VTM-100',
                        'preco_negociado' => 338.00,
                        'observacao' => 'Cliente queria congelamento de preco por doze meses.',
                        'offset_days' => 6,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 3,
                        'tipo' => 'ligacao',
                        'nota' => 'Compras confirmou abertura da renovacao anual com foco em previsibilidade.',
                        'hour' => 11,
                    ],
                    [
                        'offset_days' => 10,
                        'tipo' => 'email',
                        'nota' => 'Enviado comparativo financeiro com proposta de reajuste minimo.',
                        'hour' => 9,
                    ],
                    [
                        'offset_days' => 19,
                        'tipo' => 'observacao',
                        'nota' => 'Cliente optou por manter fornecedor atual ate proxima janela contratual.',
                        'hour' => 15,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 11,
                        'titulo' => 'Negociar congelamento de preco anual',
                        'status' => 'concluida',
                        'due_offset_days' => 13,
                    ],
                    [
                        'offset_days' => 20,
                        'titulo' => 'Registrar plano de retomada para proximo ciclo',
                        'status' => 'concluida',
                        'due_offset_days' => 22,
                    ],
                ],
            ],
            [
                'titulo' => 'Estudo piloto de biomarcadores inflamatorios',
                'cliente_codigo' => 'CLI-2026-003',
                'owner_email' => 'usuario4@unibiotech.com',
                'temperatura' => 'warm',
                'valor_estimado' => 12400.00,
                'notas' => 'Lead inicial para projeto academico com dependencia de edital e comite etico.',
                'days_ago' => 9,
                'stage_path' => [
                    ['slug' => 'lead', 'offset_days' => 0],
                ],
                'products' => [
                    [
                        'codigo' => 'UBT-ELISA-IL6',
                        'preco_negociado' => 1850.00,
                        'observacao' => 'Projeto piloto com potencial de expansao para outros biomarcadores.',
                        'offset_days' => 2,
                    ],
                ],
                'interactions' => [
                    [
                        'offset_days' => 1,
                        'tipo' => 'email',
                        'nota' => 'Pesquisadora enviou resumo do estudo e lista preliminar de marcadores.',
                        'hour' => 9,
                    ],
                    [
                        'offset_days' => 4,
                        'tipo' => 'observacao',
                        'nota' => 'Projeto depende de aprovacao de edital e comite etico institucional.',
                        'hour' => 14,
                    ],
                ],
                'tasks' => [
                    [
                        'offset_days' => 3,
                        'titulo' => 'Enviar material tecnico de apoio ao edital',
                        'status' => 'pendente',
                        'due_offset_days' => 10,
                    ],
                ],
            ],
        ];
    }
}
