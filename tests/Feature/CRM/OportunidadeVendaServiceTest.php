<?php

namespace Tests\Feature\CRM;

use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeProduto;
use App\Models\Produto;
use App\Models\Status\StatusCliente;
use App\Services\CRM\OportunidadeVendaService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OportunidadeVendaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_an_opportunity_into_grouped_sale_and_deducts_stock(): void
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

        $this->assertSame('ativa', $pedido->status);
        $this->assertSame($oportunidade->id, $pedido->oportunidade_id);
        $this->assertSame(1, $pedido->itens_count);
        $this->assertSame(2.0, (float) $pedido->quantidade_total);
        $this->assertSame(24.0, (float) $pedido->receita_bruta_total);
        $this->assertSame(8.0, (float) $produto->fresh()->estoqueAtual());

        $linha = $pedido->vendasOperacao()->first();

        $this->assertNotNull($linha);
        $this->assertSame($pedido->id, $linha->venda_operacao_pedido_id);
        $this->assertSame($pedido->codigo, $linha->produtoMovimentacao?->documento_referencia);

        $oportunidade->refresh();

        $this->assertSame($pedido->id, $oportunidade->venda_operacao_pedido_id);
        $this->assertNotNull($oportunidade->convertida_em);
        $this->assertSame($ganho->id, $oportunidade->etapa_id);
        $this->assertSame('Venda confirmada', $oportunidade->motivo_fechamento);
    }

    public function test_it_blocks_conversion_when_any_product_has_insufficient_stock(): void
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

        $this->expectException(ValidationException::class);

        try {
            app(OportunidadeVendaService::class)->convert($oportunidade, $user);
        } finally {
            $this->assertDatabaseCount('venda_operacao_pedidos', 0);
            $this->assertDatabaseCount('vendas_operacao', 0);
            $this->assertSame(1.0, (float) $produto->fresh()->estoqueAtual());
        }
    }

    public function test_it_validates_aggregate_stock_when_same_product_appears_more_than_once(): void
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

        $this->expectException(ValidationException::class);

        app(OportunidadeVendaService::class)->convert($oportunidade, $user);
    }

    public function test_it_blocks_duplicate_conversion_for_the_same_opportunity(): void
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

        app(OportunidadeVendaService::class)->convert($oportunidade, $user);

        $this->expectException(ValidationException::class);

        app(OportunidadeVendaService::class)->convert($oportunidade->fresh(), $user);
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
        ]);

        $lead = Etapa::query()->create([
            'nome' => 'Lead',
            'slug' => 'lead',
            'ordem' => 1,
            'fechamento' => false,
        ]);

        $ganho = Etapa::query()->create([
            'nome' => 'Ganho',
            'slug' => 'ganho',
            'ordem' => 2,
            'fechamento' => true,
        ]);

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
