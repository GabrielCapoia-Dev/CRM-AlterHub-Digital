<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Enum\SeparacaoStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\DocumentoConfiguracao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Romaneio;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Models\VendaPedidoFoto;
use App\Services\Operacao\RomaneioService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RomaneioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_query_exige_pedido_confirmado_movimentado_com_peso_e_sem_estorno(): void
    {
        $actor = $this->createActor([PermissoesEnum::GerarRomaneio]);
        $pedido = $this->createEligiblePedido($actor);
        $service = app(RomaneioService::class);

        $this->assertTrue((clone $service->elegiveisQuery($actor))->whereKey($pedido->id)->exists());

        $item = $pedido->vendasOperacao->firstOrFail();
        $peso = $item->peso_unitario_kg_snapshot;
        $item->forceFill(['peso_unitario_kg_snapshot' => 0])->save();
        $this->assertFalse((clone $service->elegiveisQuery($actor))->whereKey($pedido->id)->exists());

        $item->forceFill(['peso_unitario_kg_snapshot' => $peso])->save();
        $movimentacao = $item->produtoMovimentacao()->firstOrFail();
        $movimentacao->forceFill(['estornada_em' => now()])->save();
        $this->assertFalse((clone $service->elegiveisQuery($actor))->whereKey($pedido->id)->exists());

        $movimentacao->forceFill(['estornada_em' => null])->save();
        $this->assertTrue((clone $service->elegiveisQuery($actor))->whereKey($pedido->id)->exists());

        $pedido->forceFill(['quantidade_volumes' => null])->save();
        $this->assertTrue((clone $service->elegiveisQuery($actor))->whereKey($pedido->id)->exists());
    }

    public function test_criar_persiste_snapshots_lotes_totais_e_nao_movimenta_estoque(): void
    {
        $this->configurarEmpresa();
        $actor = $this->createActor([PermissoesEnum::GerarRomaneio]);
        $pedido = $this->createEligiblePedido($actor, quantidade: 2, peso: 1.5, volumes: 3);
        $item = $pedido->vendasOperacao->firstOrFail();
        VendaOperacaoLote::query()->create([
            'venda_operacao_id' => $item->id,
            'user_id' => $actor->id,
            'numero_lote' => 'LOTE-2026-A',
            'quantidade' => 2,
            'ano_fabricacao' => 2026,
            'data_fabricacao' => '2026-07-01',
            'data_validade' => '2027-07-01',
            'observacao' => 'Separacao conferida',
        ]);
        $produto = $item->produto()->firstOrFail();
        $estoqueAntes = (float) $produto->estoque_fisico;
        $movimentacoesAntes = ProdutoMovimentacao::query()->count();
        $pedido->forceFill(['quantidade_volumes' => null])->save();

        $romaneio = app(RomaneioService::class)->criar(
            [$pedido->id],
            $actor,
            'Carga da rota norte',
            [$pedido->id => 3],
        );

        $this->assertSame(Romaneio::STATUS_ATIVO, $romaneio->status);
        $this->assertSame('ROM-'.str_pad((string) $romaneio->id, 6, '0', STR_PAD_LEFT), $romaneio->codigo);
        $this->assertSame(1, $romaneio->total_pedidos);
        $this->assertSame(1, $romaneio->total_clientes);
        $this->assertSame(1, $romaneio->total_itens);
        $this->assertSame(2.0, (float) $romaneio->quantidade_total);
        $this->assertSame(3, $romaneio->quantidade_volumes_total);
        $this->assertNull($pedido->fresh()->quantidade_volumes);
        $this->assertSame(3.0, (float) $romaneio->peso_total_kg);
        $this->assertSame(40.0, (float) $romaneio->valor_total);
        $this->assertStringStartsWith('data:image/png;base64,', $romaneio->empresa_snapshot['logo_data_uri']);
        $this->assertSame('Empresa de Testes Ltda', $romaneio->empresa_snapshot['razao_social']);

        $pedidoSnapshot = $romaneio->pedidos->firstOrFail();
        $itemSnapshot = $pedidoSnapshot->itens->firstOrFail();
        $this->assertSame($pedido->id, $pedidoSnapshot->pedido_ativo_id);
        $this->assertSame($pedido->cliente_nome_snapshot, $pedidoSnapshot->cliente_nome_snapshot);
        $this->assertSame(3, $pedidoSnapshot->quantidade_volumes);
        $this->assertSame('LOTE-2026-A', $itemSnapshot->lotes_snapshot[0]['numero_lote']);
        $this->assertSame('2027-07-01', $itemSnapshot->lotes_snapshot[0]['data_validade']);
        $this->assertSame('criado', $romaneio->historicos->firstOrFail()->evento);
        $this->assertSame($estoqueAntes, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame($movimentacoesAntes, ProdutoMovimentacao::query()->count());

        $item->forceFill(['produto_nome_snapshot' => 'Nome alterado depois'])->save();
        VendaOperacaoLote::query()->where('venda_operacao_id', $item->id)->update(['numero_lote' => 'LOTE-NOVO']);
        $this->assertNotSame('Nome alterado depois', $itemSnapshot->fresh()->produto_nome_snapshot);
        $this->assertSame('LOTE-2026-A', $itemSnapshot->fresh()->lotes_snapshot[0]['numero_lote']);

        try {
            app(RomaneioService::class)->criar([$pedido->id], $actor);
            $this->fail('O mesmo pedido nao poderia participar de dois romaneios ativos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pedido_ids', $exception->errors());
        }

        $this->assertSame(1, Romaneio::query()->count());
    }

    public function test_cancelar_exige_permissao_e_justificativa_libera_pedido_sem_tocar_estoque(): void
    {
        $this->configurarEmpresa();
        $actor = $this->createActor([
            PermissoesEnum::GerarRomaneio,
            PermissoesEnum::CancelarRomaneio,
        ]);
        $pedido = $this->createEligiblePedido($actor);
        $romaneio = app(RomaneioService::class)->criar([$pedido->id], $actor);
        $item = $pedido->vendasOperacao->firstOrFail();
        $produto = $item->produto()->firstOrFail();
        $estoqueAntes = (float) $produto->estoque_fisico;
        $movimentacoesAntes = ProdutoMovimentacao::query()->count();

        try {
            app(RomaneioService::class)->cancelar($romaneio, $actor, 'curta');
            $this->fail('A justificativa curta deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('justificativa', $exception->errors());
        }

        $semPermissao = $this->createActor();

        try {
            app(RomaneioService::class)->cancelar($romaneio, $semPermissao, 'Cancelamento sem permissao');
            $this->fail('Um usuario sem permissao nao deveria cancelar o romaneio.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $cancelado = app(RomaneioService::class)->cancelar(
            $romaneio,
            $actor,
            'Carga cancelada por mudanca de rota.',
        );

        $this->assertSame(Romaneio::STATUS_CANCELADO, $cancelado->status);
        $this->assertSame($actor->id, $cancelado->cancelado_por);
        $this->assertNotNull($cancelado->cancelado_em);
        $this->assertSame('Carga cancelada por mudanca de rota.', $cancelado->justificativa_cancelamento);
        $this->assertDatabaseHas('romaneio_pedidos', [
            'romaneio_id' => $romaneio->id,
            'venda_operacao_pedido_id' => $pedido->id,
            'pedido_ativo_id' => null,
        ]);
        $this->assertSame(
            ['criado', 'cancelado'],
            $cancelado->historicos()->reorder('id')->pluck('evento')->all(),
        );
        $pedido = $pedido->fresh();
        $this->assertSame(SeparacaoStatus::RetornadoRomaneio->value, $pedido->separacao_status);
        $this->assertStringContainsString('Carga cancelada por mudanca de rota.', $pedido->retorno_romaneio_descricao);
        $this->assertFalse(app(RomaneioService::class)->elegiveisQuery($actor)->whereKey($pedido->id)->exists());
        $this->assertSame($estoqueAntes, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame($movimentacoesAntes, ProdutoMovimentacao::query()->count());
    }

    public function test_despachar_romaneio_atualiza_carga_e_pedidos(): void
    {
        $this->configurarEmpresa();
        $actor = $this->createActor([PermissoesEnum::GerarRomaneio]);
        $pedido = $this->createEligiblePedido($actor);
        $romaneio = app(RomaneioService::class)->criar([$pedido->id], $actor);

        $despachado = app(RomaneioService::class)->despachar($romaneio, $actor);

        $this->assertSame(Romaneio::STATUS_DESPACHADO, $despachado->status);
        $this->assertSame($actor->id, $despachado->despachado_por);
        $this->assertNotNull($despachado->despachado_em);
        $this->assertSame('despachada', $pedido->fresh()->status);
        $this->assertSame(SeparacaoStatus::Despachado->value, $pedido->fresh()->separacao_status);
        $this->assertSame(
            ['criado', 'despachado'],
            $despachado->historicos()->reorder('id')->pluck('evento')->all(),
        );
    }

    public function test_query_e_criacao_respeitam_o_escopo_do_vendedor(): void
    {
        $this->configurarEmpresa();
        $actor = $this->createActor([PermissoesEnum::GerarRomaneio]);
        $outroActor = $this->createActor([PermissoesEnum::GerarRomaneio]);
        $pedidoProprio = $this->createEligiblePedido($actor);
        $pedidoAlheio = $this->createEligiblePedido($outroActor);
        $service = app(RomaneioService::class);

        $this->assertTrue((clone $service->elegiveisQuery($actor))->whereKey($pedidoProprio->id)->exists());
        $this->assertFalse((clone $service->elegiveisQuery($actor))->whereKey($pedidoAlheio->id)->exists());

        try {
            $service->criar([$pedidoAlheio->id], $actor);
            $this->fail('O vendedor nao poderia gerar romaneio com um pedido fora do seu escopo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pedido_ids', $exception->errors());
        }

        $this->assertDatabaseCount('romaneios', 0);
    }

    public function test_romaneio_ativo_bloqueia_cancelamento_e_reabertura_da_venda_e_historico_bloqueia_reabertura(): void
    {
        $this->configurarEmpresa();
        $actor = $this->createActor([
            PermissoesEnum::GerarRomaneio,
            PermissoesEnum::CancelarRomaneio,
        ]);
        $pedido = $this->createEligiblePedido($actor);
        $item = $pedido->vendasOperacao->firstOrFail();
        $produto = $item->produto()->firstOrFail();
        $estoqueConfirmado = (float) $produto->estoque_fisico;
        $romaneio = app(RomaneioService::class)->criar([$pedido->id], $actor);
        $workflow = app(VendaWorkflowService::class);

        foreach (['cancelar', 'reabrir'] as $operacao) {
            try {
                $workflow->{$operacao}($pedido->fresh(), $actor, 'Justificativa valida para o teste.');
                $this->fail('Uma venda em romaneio ativo nao poderia ser cancelada nem reaberta.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }

        $this->assertSame(VendaOperacaoPedido::STATUS_CONFIRMADA, $pedido->fresh()->status);
        $this->assertSame($estoqueConfirmado, (float) $produto->fresh()->estoque_fisico);

        app(RomaneioService::class)->cancelar(
            $romaneio,
            $actor,
            'Carga cancelada para permitir nova decisao comercial.',
        );

        try {
            $workflow->reabrir($pedido->fresh(), $actor, 'Correcao comercial solicitada pelo gestor.');
            $this->fail('O historico de romaneio deveria impedir a reabertura da venda.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $cancelado = $workflow->cancelar(
            $pedido->fresh(),
            $actor,
            'Cliente desistiu depois do cancelamento da carga.',
        );

        $this->assertSame(VendaOperacaoPedido::STATUS_CANCELADA, $cancelado->status);
    }

    public function test_lotes_e_fotos_bloqueiam_reabertura_para_preservar_rastreabilidade(): void
    {
        $actor = $this->createActor();
        $workflow = app(VendaWorkflowService::class);
        $pedidoComLote = $this->createEligiblePedido($actor);
        $itemComLote = $pedidoComLote->vendasOperacao->firstOrFail();
        VendaOperacaoLote::query()->create([
            'venda_operacao_id' => $itemComLote->id,
            'user_id' => $actor->id,
            'numero_lote' => 'LOTE-BLOQUEIO',
            'quantidade' => 2,
            'ano_fabricacao' => 2026,
        ]);

        try {
            $workflow->reabrir($pedidoComLote, $actor, 'Tentativa de alterar pedido com lote.');
            $this->fail('Um pedido com lote nao poderia ser reaberto.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }

        $pedidoComFoto = $this->createEligiblePedido($actor);
        $itemComFoto = $pedidoComFoto->vendasOperacao->firstOrFail();
        VendaPedidoFoto::query()->create([
            'venda_operacao_pedido_id' => $pedidoComFoto->id,
            'venda_operacao_id' => $itemComFoto->id,
            'user_id' => $actor->id,
            'disk' => 'local',
            'path' => 'pedidos/teste/foto.png',
            'nome_original' => 'foto.png',
            'mime_type' => 'image/png',
            'tamanho_bytes' => 10,
        ]);

        try {
            $workflow->reabrir($pedidoComFoto, $actor, 'Tentativa de alterar pedido com foto.');
            $this->fail('Um pedido com foto nao poderia ser reaberto.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
    }

    /** @param list<PermissoesEnum> $permissoes */
    private function createActor(array $permissoes = []): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! in_array(PermissoesEnum::ListarVendasOperacao, $permissoes, true)) {
            $permissoes[] = PermissoesEnum::ListarVendasOperacao;
        }

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
        }

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operador '.Str::random(5),
            'email' => Str::lower(Str::random(10)).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);

        if ($permissoes !== []) {
            $user->givePermissionTo(array_map(
                fn (PermissoesEnum $permissao): string => $permissao->value,
                $permissoes,
            ));
        }

        return $user;
    }

    private function configurarEmpresa(): void
    {
        $path = 'documentos/test-logo.png';
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));

        DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
            'logo_disk' => 'local',
            'logo_path' => $path,
            'razao_social' => 'Empresa de Testes Ltda',
            'cnpj' => '04.252.011/0001-10',
            'nome_comercial' => 'Empresa Teste',
            'texto_complementar' => 'Produtos para testes',
            'exibir_valores_romaneio' => true,
        ]);
    }

    private function createEligiblePedido(
        User $actor,
        float $quantidade = 2,
        float $peso = 1.5,
        int $volumes = 2,
    ): VendaOperacaoPedido {
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente Romaneio '.Str::random(4),
            'cnpj' => sprintf(
                '11.444.%03d/0001-61',
                Cliente::query()->count() + 1,
            ),
            'email' => 'cliente@example.com',
            'telefone' => '(11) 99999-9999',
            'cep' => '01001-000',
            'logradouro' => 'Praca da Se',
            'numero' => '100',
            'bairro' => 'Se',
            'cidade' => 'Sao Paulo',
            'uf' => 'SP',
        ]);
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-ROM-'.Str::upper(Str::random(6)),
            'nome' => 'Produto para romaneio',
            'status' => 'ativo',
            'ativo' => true,
            'unidade_medida' => 'UN',
            'peso_unitario_kg' => $peso,
            'custo_base_formacao' => 5,
            'preco_tabela' => 20,
            'preco_minimo' => 10,
        ]);
        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 20,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ], $actor);
        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'quantidade_volumes' => $volumes,
            'condicao_pagamento_snapshot' => 'Boleto em 30 dias',
            'condicoes_comerciais' => 'Entrega conforme agendamento',
            'observacao' => 'Separar com cuidado',
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'preco_unitario' => 20,
            ]],
        ], $actor);

        $pedido = app(VendaWorkflowService::class)
            ->confirmar($pedido, $actor, 'teste-romaneio-'.$pedido->id)
            ->fresh(['vendasOperacao.produtoMovimentacao', 'vendasOperacao.produto']);

        $pedido->forceFill([
            'separacao_status' => SeparacaoStatus::Separado->value,
            'separado_por' => $actor->id,
            'separado_em' => now(),
        ])->save();

        return $pedido->fresh(['vendasOperacao.produtoMovimentacao', 'vendasOperacao.produto']);
    }
}
