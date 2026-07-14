<?php

namespace Tests\Feature\Operacao;

use App\Enum\RemessaStatus;
use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\RegraTributaria;
use App\Models\Transportadora;
use App\Services\Operacao\RemessaService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendaWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_shipments_consume_only_their_reservations_and_finish_after_delivery(): void
    {
        [$pedido, $produto, $actor] = $this->createConfirmedSale(6);
        $remessaService = app(RemessaService::class);
        $linha = $pedido->vendasOperacao->first();

        $this->assertSame(10.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(6.0, (float) $produto->fresh()->estoque_reservado);

        $primeira = $remessaService->criar($pedido, [
            ['venda_operacao_id' => $linha->id, 'quantidade' => 2],
        ], $actor, ['modalidade_entrega' => 'retirada', 'idempotency_key' => 'remessa-parcial-1']);
        $remessaService->iniciarSeparacao($primeira, $actor);
        $remessaService->marcarPronta($primeira, $actor);
        $remessaService->despachar($primeira, $actor, 'despacho-parcial-1');

        $this->assertSame(VendaStatus::ParcialmenteDespachada->value, $pedido->fresh()->status);
        $this->assertSame(8.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(4.0, (float) $produto->fresh()->estoque_reservado);

        $remessaService->entregar($primeira, $actor);
        $segunda = $remessaService->criar($pedido->fresh(), [
            ['venda_operacao_id' => $linha->id, 'quantidade' => 4],
        ], $actor, ['modalidade_entrega' => 'retirada', 'idempotency_key' => 'remessa-parcial-2']);
        $remessaService->iniciarSeparacao($segunda, $actor);
        $remessaService->marcarPronta($segunda, $actor);
        $remessaService->despachar($segunda, $actor, 'despacho-parcial-2');

        $this->assertSame(VendaStatus::Despachada->value, $pedido->fresh()->status);
        $this->assertSame(4.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);

        $remessaService->entregar($segunda, $actor);

        $this->assertSame(VendaStatus::Concluida->value, $pedido->fresh()->status);
        $this->assertNotNull($pedido->fresh()->concluida_em);
    }

    public function test_canceling_a_confirmed_sale_cancels_pending_shipments_and_releases_stock(): void
    {
        [$pedido, $produto, $actor] = $this->createConfirmedSale(4);
        $transportadora = Transportadora::query()->create([
            'razao_social' => 'Transportadora teste',
            'ativo' => true,
        ]);
        $linha = $pedido->vendasOperacao->first();
        $remessa = app(RemessaService::class)->criar($pedido, [
            ['venda_operacao_id' => $linha->id, 'quantidade' => 4],
        ], $actor, [
            'modalidade_entrega' => 'transportadora',
            'transportadora_id' => $transportadora->id,
            'valor_frete_custo' => 10,
            'valor_frete_cobrado' => 15,
            'idempotency_key' => 'remessa-cancelamento',
        ]);

        $this->assertSame(15.0, (float) $pedido->fresh()->valor_frete_cobrado);

        app(VendaWorkflowService::class)->cancelar($pedido, $actor, 'Cliente desistiu antes da expedicao.');

        $this->assertSame(VendaStatus::Cancelada->value, $pedido->fresh()->status);
        $this->assertSame(RemessaStatus::Cancelada, $remessa->fresh()->status);
        $this->assertSame(10.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);
        $this->assertSame(0.0, (float) $pedido->fresh()->valor_frete_custo);
        $this->assertSame(0.0, (float) $pedido->fresh()->valor_frete_cobrado);
    }

    public function test_return_is_idempotent_and_prevents_delivery_after_stock_has_come_back(): void
    {
        [$pedido, $produto, $actor] = $this->createConfirmedSale(4);
        $linha = $pedido->vendasOperacao->first();
        $service = app(RemessaService::class);
        $remessa = $service->criar($pedido, [
            ['venda_operacao_id' => $linha->id, 'quantidade' => 4],
        ], $actor, ['modalidade_entrega' => 'retirada', 'idempotency_key' => 'remessa-devolucao']);
        $service->iniciarSeparacao($remessa, $actor);
        $service->marcarPronta($remessa, $actor);
        $remessa = $service->despachar($remessa, $actor, 'despacho-devolucao');
        $item = $remessa->itens->first();

        $primeira = $service->devolver($pedido->fresh(), [
            ['remessa_item_id' => $item->id, 'quantidade' => 2],
        ], $actor, 'Cliente devolveu parte do pedido.', 'devolucao-idempotente');
        $repetida = $service->devolver($pedido->fresh(), [
            ['remessa_item_id' => $item->id, 'quantidade' => 2],
        ], $actor, 'Cliente devolveu parte do pedido.', 'devolucao-idempotente');

        $this->assertSame($primeira->id, $repetida->id);
        $this->assertSame(VendaStatus::DevolvidaParcial->value, $pedido->fresh()->status);
        $this->assertSame(8.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(1, $primeira->itens()->count());

        $this->expectException(ValidationException::class);
        $service->entregar($remessa, $actor);
    }

    public function test_movement_metadata_cannot_be_edited_after_creation(): void
    {
        [, $produto, $actor] = $this->createConfirmedSale(1);
        $movimentacao = $produto->produtoMovimentacoes()->first();
        $movimentacao->observacao = 'Tentativa de alterar o razao';

        $this->expectException(\LogicException::class);
        $movimentacao->save();
    }

    /** @return array{0:\App\Models\VendaOperacaoPedido,1:Produto,2:User} */
    private function createConfirmedSale(float $quantidade): array
    {
        $actor = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operador logistico',
            'email' => Str::lower(Str::random(10)).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente do workflow',
            'uf' => 'SP',
        ]);
        RegraTributaria::query()->create([
            'nome' => 'Regra geral SP',
            'uf_destino' => 'SP',
            'versao' => 1,
            'vigencia_inicio' => now()->subYear()->toDateString(),
            'aliquota_icms' => 12,
            'ativo' => true,
        ]);
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-WORKFLOW-'.Str::upper(Str::random(5)),
            'nome' => 'Produto workflow',
            'status' => 'ativo',
            'ativo' => true,
            'unidade_medida' => 'un',
            'custo_base_formacao' => 5,
            'preco_tabela' => 20,
            'preco_minimo' => 10,
        ]);
        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ], $actor);
        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'preco_unitario' => 20,
            ]],
        ], $actor);
        $pedido = app(VendaWorkflowService::class)->confirmar(
            $pedido,
            $actor,
            'confirmar-teste-'.$pedido->id,
        );

        return [$pedido, $produto, $actor];
    }
}
