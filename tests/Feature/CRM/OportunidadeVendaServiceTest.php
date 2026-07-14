<?php

namespace Tests\Feature\CRM;

use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeProduto;
use App\Models\Produto;
use App\Models\RegraTributaria;
use App\Models\Status\StatusCliente;
use App\Services\CRM\OportunidadeVendaService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OportunidadeVendaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_an_opportunity_into_confirmed_sale_and_deducts_stock(): void
    {
        [$user, $cliente, $lead, $ganho] = $this->baseData();
        $produto = $this->produtoComEstoque(10, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_negociado' => 12,
        ]);

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);

        $this->assertSame('confirmada', $pedido->status);
        $this->assertSame($oportunidade->id, $pedido->oportunidade_id);
        $this->assertSame(1, $pedido->itens_count);
        $this->assertSame(2.0, (float) $pedido->quantidade_total);
        $this->assertSame(24.0, (float) $pedido->receita_bruta_total);
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);

        $linha = $pedido->vendasOperacao()->first();

        $this->assertNotNull($linha);
        $this->assertSame($pedido->id, $linha->venda_operacao_pedido_id);
        $this->assertNotNull($linha->produto_movimentacao_id);

        $oportunidade->refresh();

        $this->assertSame($pedido->id, $oportunidade->venda_operacao_pedido_id);
        $this->assertNotNull($oportunidade->convertida_em);
        $this->assertSame($ganho->id, $oportunidade->etapa_id);
        $this->assertSame('Venda confirmada', $oportunidade->motivo_fechamento);
    }

    public function test_it_propagates_approved_crm_discount_to_the_confirmed_sale(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(10, precoTabela: 20);
        $produto->update(['preco_minimo' => 15]);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);
        $aprovadoEm = now()->startOfSecond();

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_negociado' => 12,
            'desconto_aprovado_por' => $user->id,
            'desconto_aprovado_em' => $aprovadoEm,
        ]);

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);
        $linha = $pedido->vendasOperacao->first();

        $this->assertSame('confirmada', $pedido->status);
        $this->assertTrue((bool) $linha->desconto_requer_aprovacao);
        $this->assertSame($user->id, $linha->desconto_aprovado_por);
        $this->assertTrue($aprovadoEm->equalTo($linha->desconto_aprovado_em));
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_insufficient_stock_creates_a_pending_sale_from_the_opportunity(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(1, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_negociado' => 12,
        ]);

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);

        $this->assertSame('pendente_aprovacao', $pedido->status);
        $this->assertArrayHasKey('estoque', $pedido->motivos_aprovacao);
        $this->assertSame(1.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertNull($pedido->vendasOperacao->first()->produto_movimentacao_id);
        $this->assertSame($pedido->id, $oportunidade->fresh()->venda_operacao_pedido_id);
        $this->assertNull($oportunidade->fresh()->convertida_em);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 1,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ], $user);

        $pedido = app(VendaOperacaoService::class)->approve($pedido->fresh(), $user);

        $this->assertSame('confirmada', $pedido->status);
        $this->assertSame(0.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertNotNull($oportunidade->fresh()->convertida_em);
        $this->assertSame(Etapa::query()->where('slug', 'ganho')->value('id'), $oportunidade->fresh()->etapa_id);
    }

    public function test_it_marks_aggregate_stock_shortage_for_repeated_products(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(3, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        foreach ([2, 2] as $quantidade) {
            OportunidadeProduto::query()->create([
                'oportunidade_id' => $oportunidade->id,
                'produto_id' => $produto->id,
                'quantidade' => $quantidade,
                'preco_negociado' => 12,
            ]);
        }

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);

        $this->assertSame('pendente_aprovacao', $pedido->status);
        $this->assertSame(4.0, (float) $pedido->motivos_aprovacao['estoque'][0]['solicitado']);
        $this->assertSame(1.0, (float) $pedido->motivos_aprovacao['estoque'][0]['deficit']);
        $this->assertSame(3.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_canceling_a_pending_sale_releases_the_opportunity_for_recovery(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(1, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_negociado' => 20,
        ]);

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);
        $cancelada = app(VendaWorkflowService::class)->cancelar(
            $pedido,
            $user,
            'Cliente solicitou revisar a oportunidade antes de prosseguir.',
        );
        $recuperada = $oportunidade->fresh();

        $this->assertSame('cancelada', $cancelada->status);
        $this->assertNull($cancelada->oportunidade_id);
        $this->assertNull($recuperada->venda_operacao_pedido_id);
        $this->assertNull($recuperada->convertida_em);
        $this->assertTrue($recuperada->canEditCommercially());

        $recuperada->update(['titulo' => 'Oportunidade recuperada apos cancelamento']);
        $novoPedido = app(OportunidadeVendaService::class)->convert($recuperada->fresh(), $user);

        $this->assertNotSame($pedido->id, $novoPedido->id);
        $this->assertSame('pendente_aprovacao', $novoPedido->status);
        $this->assertSame($novoPedido->id, $recuperada->fresh()->venda_operacao_pedido_id);
    }

    public function test_rejecting_a_pending_sale_releases_the_opportunity_for_recovery(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(1, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 2,
            'preco_negociado' => 20,
        ]);

        $pedido = app(OportunidadeVendaService::class)->convert($oportunidade, $user);
        $recusada = app(VendaOperacaoService::class)->reject(
            $pedido,
            $user,
            'Venda recusada para renegociacao comercial.',
        );
        $recuperada = $oportunidade->fresh();

        $this->assertSame('recusada', $recusada->status);
        $this->assertNull($recusada->oportunidade_id);
        $this->assertNull($recuperada->venda_operacao_pedido_id);
        $this->assertNull($recuperada->convertida_em);
        $this->assertTrue($recuperada->canEditCommercially());

        $recuperada->update(['titulo' => 'Oportunidade recuperada apos recusa']);
        $novoPedido = app(OportunidadeVendaService::class)->convert($recuperada->fresh(), $user);

        $this->assertNotSame($pedido->id, $novoPedido->id);
        $this->assertSame('pendente_aprovacao', $novoPedido->status);
        $this->assertSame($novoPedido->id, $recuperada->fresh()->venda_operacao_pedido_id);
    }

    public function test_duplicate_conversion_is_idempotent(): void
    {
        [$user, $cliente, $lead] = $this->baseData();
        $produto = $this->produtoComEstoque(10, precoTabela: 20);
        $oportunidade = $this->oportunidade($user, $cliente, $lead);

        OportunidadeProduto::query()->create([
            'oportunidade_id' => $oportunidade->id,
            'produto_id' => $produto->id,
            'quantidade' => 1,
            'preco_negociado' => 12,
        ]);

        $primeira = app(OportunidadeVendaService::class)->convert($oportunidade, $user);
        $segunda = app(OportunidadeVendaService::class)->convert($oportunidade->fresh(), $user);

        $this->assertSame($primeira->id, $segunda->id);
        $this->assertDatabaseCount('venda_operacao_pedidos', 1);
        $this->assertSame(0.0, (float) $produto->fresh()->estoque_reservado);
        $this->assertSame(9.0, (float) $produto->fresh()->estoqueAtual());
        $this->assertDatabaseHas('produto_movimentacoes', [
            'origem_tipo' => 'venda',
            'origem_id' => $primeira->vendasOperacao->first()->id,
        ]);
    }

    private function baseData(): array
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Vendedor CRM',
            'email' => 'vendedor.'.Str::random(8).'@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $status = StatusCliente::query()->create(['nome' => 'Ativo']);
        $segmento = CategoriaSegmento::query()->create(['nome' => 'Laboratorio']);

        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente Venda Ltda',
            'nome_fantasia' => 'Cliente Venda',
            'cnpj' => '12.345.678/0001-90',
            'id_status_cliente' => $status->id,
            'id_categoria_segmento' => $segmento->id,
            'nome_completo' => 'Contato Venda',
            'email' => 'contato@venda.test',
            'uf' => 'SP',
        ]);

        RegraTributaria::query()->firstOrCreate(
            ['uf_destino' => 'SP', 'ncm' => null, 'versao' => 1],
            [
                'nome' => 'Regra geral SP',
                'vigencia_inicio' => now()->subYear()->toDateString(),
                'aliquota_icms' => 0,
                'ativo' => true,
            ],
        );

        $suffix = Str::lower(Str::random(6));

        $lead = Etapa::query()->create([
            'nome' => 'Lead',
            'slug' => 'lead-'.$suffix,
            'ordem' => 1,
            'fechamento' => false,
        ]);

        $ganho = Etapa::query()->firstOrCreate(
            ['slug' => 'ganho'],
            [
                'nome' => 'Ganho',
                'ordem' => 2,
                'fechamento' => true,
            ],
        );

        return [$user, $cliente, $lead, $ganho];
    }

    private function oportunidade(User $user, Cliente $cliente, Etapa $etapa): Oportunidade
    {
        return Oportunidade::query()->create([
            'titulo' => 'Oportunidade para venda',
            'cliente_id' => $cliente->id,
            'etapa_id' => $etapa->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
            'valor_estimado' => 100,
        ]);
    }

    private function produtoComEstoque(float $estoque, float $precoTabela): Produto
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-'.Str::upper(Str::random(6)),
            'nome' => 'Produto Venda',
            'status' => 'ativo',
            'ativo' => true,
            'unidade_medida' => 'un',
            'custo_base_formacao' => 5.0000,
            'preco_tabela' => $precoTabela,
        ]);

        app(MovimentacaoEstoqueService::class)->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => $estoque,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ]);

        return $produto;
    }
}
