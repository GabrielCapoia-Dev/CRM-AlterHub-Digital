<?php

namespace Tests\Feature\Fiscal;

use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoPedido;
use App\Services\Fiscal\RegraTributariaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegraTributariaServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_versions_rules_and_prefers_destination_ncm_override(): void
    {
        $service = app(RegraTributariaService::class);
        $geralV1 = $service->criarVersao([
            'nome' => 'SP geral',
            'uf_destino' => 'SP',
            'vigencia_inicio' => '2026-01-01',
            'aliquota_icms' => 18,
        ]);
        $especifica = $service->criarVersao([
            'nome' => 'SP reagentes',
            'uf_destino' => 'SP',
            'ncm' => '3822.19.90',
            'vigencia_inicio' => '2026-01-01',
            'aliquota_icms' => 12,
            'aliquota_pis' => 1,
        ]);
        $geralV2 = $service->criarVersao([
            'nome' => 'SP geral 2026/2',
            'uf_destino' => 'sp',
            'vigencia_inicio' => '2026-07-01',
            'aliquota_icms' => 17,
        ]);

        $this->assertSame(1, $geralV1->versao);
        $this->assertSame('2026-06-30', $geralV1->fresh()->vigencia_fim?->toDateString());
        $this->assertSame(2, $geralV2->versao);
        $this->assertSame($especifica->id, $service->resolver('SP', '38221990', '2026-08-01')?->id);
        $this->assertSame($geralV1->id, $service->resolver('SP', '99999999', '2026-06-01')?->id);
        $this->assertSame($geralV2->id, $service->resolver('SP', '99999999', '2026-08-01')?->id);
    }

    public function test_it_applies_and_snapshots_tax_rule_on_each_sale_line(): void
    {
        $service = app(RegraTributariaService::class);
        $regra = $service->criarVersao([
            'nome' => 'SP NCM especifico',
            'uf_destino' => 'SP',
            'ncm' => '38221990',
            'vigencia_inicio' => '2026-01-01',
            'aliquota_icms' => 12,
            'aliquota_pis' => 1,
        ]);
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente Fiscal',
            'uf' => 'SP',
        ]);
        $produto = Produto::query()->create([
            'nome' => 'Produto fiscal',
            'status' => 'ativo',
            'classificacao' => 'revenda',
            'origem' => 'nacional',
            'ncm' => '3822.19.90',
        ]);
        $pedido = VendaOperacaoPedido::query()->create([
            'cliente_id' => $cliente->id,
            'status' => 'rascunho',
            'data_venda' => '2026-07-13',
        ]);
        $linha = VendaOperacao::query()->create([
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'produto_nome_snapshot' => $produto->nome,
            'data_venda' => '2026-07-13',
            'ano_referencia' => 2026,
            'mes_referencia' => 7,
            'quantidade' => 1,
            'preco_unitario' => 1000,
            'receita_bruta' => 1000,
            'custo_unitario_snapshot' => 400,
            'custo_total_snapshot' => 400,
            'receita_liquida' => 1000,
            'lucro_bruto' => 600,
            'lucro_apos_impostos' => 600,
        ]);

        $service->aplicarNaVenda($pedido);
        $linha = $linha->fresh();

        $this->assertSame($regra->id, $linha->regra_tributaria_id);
        $this->assertSame(1, $linha->regra_tributaria_versao_snapshot);
        $this->assertSame('SP', $linha->uf_destino_snapshot);
        $this->assertSame('38221990', $linha->ncm_snapshot);
        $this->assertSame(120.0, (float) $linha->icms_valor);
        $this->assertSame(10.0, (float) $linha->outros_impostos_valor);
        $this->assertSame(870.0, (float) $linha->receita_liquida);
        $this->assertSame(470.0, (float) $linha->lucro_apos_impostos);
    }
}
