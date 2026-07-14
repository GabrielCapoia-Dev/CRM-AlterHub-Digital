<?php

namespace Tests\Feature\Operacao;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendaWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_sale_deducts_stock_once_with_an_immutable_movement(): void
    {
        [$pedido, $produto, $actor] = $this->createDraftSale(4);

        $confirmed = app(VendaWorkflowService::class)->confirmar(
            $pedido,
            $actor,
            'confirmar-venda-direta',
        );

        $this->assertSame(VendaStatus::Confirmada->value, $confirmed->status);
        $this->assertSame(6.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);
        $this->assertNotNull($confirmed->vendasOperacao->first()->produto_movimentacao_id);
        $this->assertSame(1, ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->count());

        app(VendaWorkflowService::class)->confirmar(
            $confirmed,
            $actor,
            'confirmar-venda-direta',
        );

        $this->assertSame(6.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(1, ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->count());
    }

    public function test_sale_becomes_pending_when_stock_changes_before_confirmation(): void
    {
        [$pedido, $produto, $actor] = $this->createDraftSale(6);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'saida',
            'quantidade' => 6,
            'motivo' => 'Consumo concorrente',
            'realizado_em' => now(),
        ], $actor);

        $pending = app(VendaWorkflowService::class)->confirmar(
            $pedido,
            $actor,
            'confirmar-sem-saldo',
        );

        $this->assertSame(VendaStatus::PendenteAprovacao->value, $pending->status);
        $this->assertArrayHasKey('estoque', $pending->motivos_aprovacao);
        $this->assertSame(6.0, (float) $pending->motivos_aprovacao['estoque'][0]['solicitado']);
        $this->assertSame(4.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(0, ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->count());
    }

    public function test_stock_shortage_history_is_idempotent_for_the_same_confirmation_attempt(): void
    {
        [$pedido, , $actor] = $this->createDraftSale(6);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $pedido->vendasOperacao->firstOrFail()->produto_id,
            'tipo' => 'saida',
            'quantidade' => 6,
            'motivo' => 'Consumo concorrente',
            'realizado_em' => now(),
        ], $actor);

        $workflow = app(VendaWorkflowService::class);
        $pending = $workflow->confirmar($pedido, $actor, 'confirmar-sem-saldo-idempotente');

        $workflow->confirmar(
            $pending->fresh(),
            $actor,
            'confirmar-sem-saldo-idempotente',
        );

        $historicos = $pending->historicos()
            ->where('evento', 'aguardando_aprovacao_estoque');

        $this->assertSame(1, (clone $historicos)->count());

        $workflow->confirmar(
            $pending->fresh(),
            $actor,
            'confirmar-sem-saldo-nova-tentativa',
        );

        $this->assertSame(2, $historicos->count());
    }

    public function test_canceling_confirmed_sale_creates_a_compensating_entry_once(): void
    {
        [$pedido, $produto, $actor] = $this->createDraftSale(4);
        $pedido = app(VendaWorkflowService::class)->confirmar($pedido, $actor, 'confirmar-cancelamento');

        app(VendaWorkflowService::class)->cancelar(
            $pedido,
            $actor,
            'Cliente desistiu da compra confirmada.',
        );

        $this->assertSame(VendaStatus::Cancelada->value, $pedido->fresh()->status);
        $this->assertSame(10.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(1, ProdutoMovimentacao::query()->where('origem_tipo', 'estorno')->count());
        $this->assertNotNull(
            ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->firstOrFail()->estornada_em
        );

        app(VendaWorkflowService::class)->cancelar(
            $pedido->fresh(),
            $actor,
            'Cliente desistiu da compra confirmada.',
        );

        $this->assertSame(10.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(1, ProdutoMovimentacao::query()->where('origem_tipo', 'estorno')->count());
    }

    public function test_reopened_sale_can_be_confirmed_again_without_losing_audit_history(): void
    {
        [$pedido, $produto, $actor] = $this->createDraftSale(2);
        $pedido = app(VendaWorkflowService::class)->confirmar($pedido, $actor, 'confirmar-reabertura');

        $reopened = app(VendaWorkflowService::class)->reabrir(
            $pedido,
            $actor,
            'Correção comercial solicitada pelo gestor.',
        );

        $this->assertSame(VendaStatus::Rascunho->value, $reopened->status);
        $this->assertNull($reopened->vendasOperacao->first()->produto_movimentacao_id);
        $this->assertSame(10.0, (float) $produto->fresh()->estoque_fisico);

        app(VendaWorkflowService::class)->confirmar($reopened, $actor, 'reconfirmar-reabertura');

        $this->assertSame(8.0, (float) $produto->fresh()->estoque_fisico);
        $this->assertSame(2, ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->count());
        $this->assertSame(1, ProdutoMovimentacao::query()->where('origem_tipo', 'estorno')->count());
    }

    public function test_movement_metadata_cannot_be_edited_after_creation(): void
    {
        [$pedido, , $actor] = $this->createDraftSale(1);
        app(VendaWorkflowService::class)->confirmar($pedido, $actor, 'confirmar-imutavel');

        $movimentacao = ProdutoMovimentacao::query()->where('origem_tipo', 'venda')->firstOrFail();
        $movimentacao->observacao = 'Tentativa de alterar o razão';

        $this->expectException(\LogicException::class);
        $movimentacao->save();
    }

    /** @return array{0:VendaOperacaoPedido,1:Produto,2:User} */
    private function createDraftSale(float $quantidade): array
    {
        $actor = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operador de vendas',
            'email' => Str::lower(Str::random(10)).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente do workflow',
            'uf' => 'SP',
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

        return [$pedido, $produto, $actor];
    }
}
