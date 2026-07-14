<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\RegraTributaria;
use App\Models\Status\StatusCliente;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendaOperacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_draft_line_without_stock_output_and_with_financial_snapshots(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-01',
            'nome' => 'Produto operacional',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 5.0000,
            'preco_tabela' => 20.00,
            'preco_minimo' => 10.00,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 10,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ]);

        $venda = app(VendaOperacaoService::class)->create([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 12,
            'icms_aliquota' => 10,
            'outros_impostos_aliquota' => 5,
            'cliente_nome' => 'Cliente Teste',
            'vendedor_nome' => 'Vendedor Teste',
        ]);

        $this->assertSame(24.0, (float) $venda->receita_bruta);
        $this->assertSame(2.4, (float) $venda->icms_valor);
        $this->assertSame(1.2, (float) $venda->outros_impostos_valor);
        $this->assertSame(20.4, (float) $venda->receita_liquida);
        $this->assertSame(10.0, (float) $venda->custo_total_snapshot);
        $this->assertSame(10.4, (float) $venda->lucro_apos_impostos);
        $this->assertNull($venda->produto_movimentacao_id);
        $this->assertSame(10.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_draft_line_does_not_reserve_or_deduct_stock(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-02',
            'nome' => 'Produto sem saldo suficiente',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 3.0000,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 1,
            'valor_unitario' => 3,
            'realizado_em' => now(),
        ]);

        $linha = app(VendaOperacaoService::class)->create([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 15,
        ]);

        $this->assertNull($linha->produto_movimentacao_id);
        $this->assertSame(1.0, (float) $produto->fresh()->estoque_fisico);
    }

    public function test_it_creates_a_grouped_sale_header_for_manual_sale(): void
    {
        $cliente = $this->createCliente();
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-VEN-03',
            'nome' => 'Produto manual agrupado',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 4.0000,
            'preco_tabela' => 18.00,
            'preco_minimo' => 8.00,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 5,
            'valor_unitario' => 4,
            'realizado_em' => now(),
        ]);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'produto_id' => $produto->id,
            'data_venda' => now()->toDateString(),
            'quantidade' => 2,
            'preco_unitario' => 10,
            'cliente_id' => $cliente->id,
        ]);

        $this->assertSame(VendaOperacaoPedido::STATUS_RASCUNHO, $pedido->status);
        $this->assertSame($cliente->id, $pedido->cliente_id);
        $this->assertSame($cliente->razao_social, $pedido->cliente_nome_snapshot);
        $this->assertSame(1, $pedido->itens_count);
        $this->assertSame(20.0, (float) $pedido->receita_bruta_total);
        $this->assertSame(5.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertDatabaseHas('vendas_operacao', [
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'cliente_nome' => $cliente->razao_social,
        ]);
    }

    public function test_it_creates_multi_item_draft_without_deducting_stock(): void
    {
        $cliente = $this->createCliente();
        $produtoA = $this->createProdutoComEstoque('PROD-MULTI-A', 20, 10, 20, 12);
        $produtoB = $this->createProdutoComEstoque('PROD-MULTI-B', 12, 8, 15, 10);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produtoA->id,
                    'quantidade' => 2,
                    'preco_unitario' => 16,
                ],
                [
                    'produto_id' => $produtoB->id,
                    'quantidade' => 3,
                    'preco_unitario' => 11,
                ],
            ],
        ]);

        $this->assertSame(VendaOperacaoPedido::STATUS_RASCUNHO, $pedido->status);
        $this->assertSame(2, $pedido->itens_count);
        $this->assertSame(65.0, (float) $pedido->receita_bruta_total);
        $this->assertSame(20.0, (float) $produtoA->fresh()->estoqueAtual());
        $this->assertSame(12.0, (float) $produtoB->fresh()->estoqueAtual());
        $this->assertTrue($pedido->vendasOperacao->every(fn ($linha) => $linha->produto_movimentacao_id === null));
    }

    public function test_price_below_minimum_creates_pending_sale_without_stock_movement(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-PEND-01', 10, 5, 20, 15);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco_unitario' => 12,
                ],
            ],
        ]);

        $linha = $pedido->vendasOperacao->first();

        $this->assertSame(VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO, $pedido->status);
        $this->assertTrue((bool) $linha->desconto_requer_aprovacao);
        $this->assertNull($linha->produto_movimentacao_id);
        $this->assertSame(10.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_insufficient_stock_creates_pending_sale_with_auditable_reason(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-PEND-STOCK', 1, 5, 20, 10);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 2,
                'preco_unitario' => 20,
            ]],
        ]);

        $this->assertSame(VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO, $pedido->status);
        $this->assertArrayHasKey('estoque', $pedido->motivos_aprovacao);
        $this->assertSame(1.0, (float) $pedido->motivos_aprovacao['estoque'][0]['deficit']);
        $this->assertNull($pedido->vendasOperacao->first()->produto_movimentacao_id);
        $this->assertSame(1.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_approve_pending_sale_deducts_stock_and_marks_confirmed(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-APR-01', 10, 5, 20, 15);
        $approver = $this->createUserWithPermission(PermissoesEnum::AprovarDesconto->value);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco_unitario' => 12,
                ],
            ],
        ], $this->createUserWithPermission(PermissoesEnum::CriarVendasOperacao->value));

        $approved = app(VendaOperacaoService::class)->approve($pedido, $approver);
        $linha = $approved->vendasOperacao->first();

        $this->assertSame(VendaOperacaoPedido::STATUS_ATIVA, $approved->status);
        $this->assertSame($approver->id, $approved->aprovado_por);
        $this->assertNotNull($linha->produto_movimentacao_id);
        $this->assertSame($approver->id, $linha->desconto_aprovado_por);
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);

        $historicos = $approved->historicos()->count();
        $repeated = app(VendaOperacaoService::class)->approve($approved, $approver);

        $this->assertSame($approved->id, $repeated->id);
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertSame(0, $linha->reserva()->count());
        $this->assertSame($historicos, $repeated->historicos()->count());
    }

    public function test_reject_pending_sale_does_not_touch_stock(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-REJ-01', 10, 5, 20, 15);
        $approver = $this->createUserWithPermission(PermissoesEnum::AprovarDesconto->value);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 1,
                    'preco_unitario' => 10,
                ],
            ],
        ]);

        $rejected = app(VendaOperacaoService::class)->reject($pedido, $approver, 'Margem insuficiente');

        $this->assertSame(VendaOperacaoPedido::STATUS_RECUSADA, $rejected->status);
        $this->assertSame('Margem insuficiente', $rejected->motivo_recusa);
        $this->assertNull($rejected->vendasOperacao->first()->produto_movimentacao_id);
        $this->assertSame(10.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_approve_fails_when_stock_is_insufficient(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-NOSTOCK', 2, 5, 20, 15);
        $approver = $this->createUserWithPermission(PermissoesEnum::AprovarDesconto->value);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 2,
                    'preco_unitario' => 10,
                ],
            ],
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'saida',
            'quantidade' => 2,
            'motivo' => 'Consumo de teste',
            'realizado_em' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(VendaOperacaoService::class)->approve($pedido, $approver);
    }

    public function test_copy_payload_resets_prices_to_table_without_discount(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-COPY-01', 20, 8, 30, 25);

        $pedido = app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                [
                    'produto_id' => $produto->id,
                    'quantidade' => 4,
                    'preco_unitario' => 26,
                    'icms_aliquota' => 5,
                ],
            ],
        ]);

        $produto->update(['preco_tabela' => 32]);

        $payload = app(VendaOperacaoService::class)->buildCopyPayload($pedido);

        $this->assertSame($cliente->id, $payload['cliente_id']);
        $this->assertSame($pedido->id, $payload['origem_pedido_id']);
        $this->assertCount(1, $payload['itens']);
        $this->assertSame($produto->id, $payload['itens'][0]['produto_id']);
        $this->assertSame(4.0, $payload['itens'][0]['quantidade']);
        $this->assertSame(32.0, $payload['itens'][0]['preco_unitario']);
        $this->assertSame(0.0, (float) $payload['itens'][0]['desconto_percentual']);
        $this->assertSame(0.0, (float) $payload['itens'][0]['icms_aliquota']);
    }

    public function test_vendedor_query_scope_only_own_sales(): void
    {
        $cliente = $this->createCliente();
        $produto = $this->createProdutoComEstoque('PROD-SCOPE', 50, 5, 20, 12);

        $vendedorA = $this->createUserWithRole(RolesEnum::Vendedor);
        $vendedorB = $this->createUserWithRole(RolesEnum::Vendedor);

        app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 1, 'preco_unitario' => 15],
            ],
        ], $vendedorA);

        app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [
                ['produto_id' => $produto->id, 'quantidade' => 1, 'preco_unitario' => 15],
            ],
        ], $vendedorB);

        $idsA = app(VendaOperacaoService::class)->queryPorPerfil($vendedorA)->pluck('user_id')->unique()->all();
        $idsB = app(VendaOperacaoService::class)->queryPorPerfil($vendedorB)->pluck('user_id')->unique()->all();

        $this->assertSame([$vendedorA->id], $idsA);
        $this->assertSame([$vendedorB->id], $idsB);
        $this->assertFalse(app(VendaOperacaoService::class)->podeVerTodasVendas($vendedorA));
    }

    private function createCliente(): Cliente
    {
        $status = StatusCliente::query()->create(['nome' => 'Ativo '.Str::random(4)]);
        $segmento = CategoriaSegmento::query()->create(['nome' => 'Industrial '.Str::random(4)]);

        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente Manual '.Str::random(6),
            'nome_fantasia' => 'Cliente Manual',
            'cnpj' => '12.345.678/0001-90',
            'id_status_cliente' => $status->id,
            'id_categoria_segmento' => $segmento->id,
            'nome_completo' => 'Contato Manual',
            'email' => 'manual@example.com',
            'uf' => 'SP',
        ]);

        RegraTributaria::query()->create([
            'nome' => 'Regra geral SP',
            'uf_destino' => 'SP',
            'versao' => 1,
            'vigencia_inicio' => now()->subYear()->toDateString(),
            'aliquota_icms' => 0,
            'ativo' => true,
        ]);

        return $cliente;
    }

    private function createProdutoComEstoque(
        string $codigo,
        float $estoque,
        float $custo,
        float $precoTabela,
        float $precoMinimo,
    ): Produto {
        $produto = Produto::query()->create([
            'codigo_interno' => $codigo,
            'nome' => 'Produto '.$codigo,
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => $custo,
            'preco_tabela' => $precoTabela,
            'preco_minimo' => $precoMinimo,
            'ativo' => true,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => $estoque,
            'valor_unitario' => $custo,
            'realizado_em' => now(),
        ]);

        return $produto->fresh();
    }

    private function createUserWithPermission(string $permission): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate($permission, 'web');

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'User '.Str::random(5),
            'email' => Str::lower(Str::random(8)).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);

        $user->givePermissionTo($permission);

        return $user;
    }

    private function createUserWithRole(RolesEnum $role): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate($role->value, 'web');
        Permission::findOrCreate(PermissoesEnum::ListarVendasOperacao->value, 'web');
        Permission::findOrCreate(PermissoesEnum::CriarVendasOperacao->value, 'web');

        $roleModel = Role::findByName($role->value, 'web');
        $roleModel->syncPermissions([
            PermissoesEnum::ListarVendasOperacao->value,
            PermissoesEnum::CriarVendasOperacao->value,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $role->value.' '.Str::random(4),
            'email' => Str::lower(Str::random(8)).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);

        $user->assignRole($role->value);

        return $user->fresh();
    }
}
