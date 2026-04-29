<?php

namespace Tests\Feature\Produtos;

use App\Models\Acesso\User;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MovimentacaoEstoqueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_insumo_movements_and_updates_running_balance(): void
    {
        $responsavel = User::query()->create([
            'name' => 'Responsavel Teste',
            'email' => 'responsavel.movimentacao@example.com',
            'email_verified_at' => now(),
            'email_approved' => true,
            'password' => Hash::make('password'),
        ]);

        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-MOV-01',
            'nome' => 'Insumo de teste',
            'origem' => 'importado',
            'moeda_origem' => 'USD',
            'custo_moeda_origem' => 7.0000,
            'taxa_cambio' => 5.450000,
            'custo_referencia' => 42.8652,
            'custo_nacionalizado' => 42.8652,
        ]);

        $insumo->insumoFatoresCusto()->create([
            'nome' => 'Taxa Fiduciaria',
            'tipo' => 'percentual',
            'valor' => 0.8900,
            'ordem' => 1,
        ]);

        $service = app(MovimentacaoEstoqueService::class);

        $entrada = $service->createForInsumo([
            'insumo_id' => $insumo->id,
            'tipo' => 'entrada',
            'quantidade' => 5,
            'user_id' => $responsavel->id,
            'realizado_em' => now()->toDateString(),
        ]);

        $saida = $service->createForInsumo([
            'insumo_id' => $insumo->id,
            'tipo' => 'saida',
            'quantidade' => 1,
            'motivo' => 'Consumo interno',
            'user_id' => $responsavel->id,
            'realizado_em' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame(0.0, (float) $entrada->saldo_anterior);
        $this->assertSame(5.0, (float) $entrada->saldo_atual);
        $this->assertSame(42.8652, (float) $entrada->valor_unitario);
        $this->assertSame(214.326, (float) $entrada->valor_total);
        $this->assertSame($responsavel->id, $entrada->user_id);
        $this->assertSame($responsavel->name, $entrada->responsavel_nome);
        $this->assertSame(-1.0, (float) $saida->impacto_estoque);
        $this->assertSame(4.0, (float) $saida->saldo_atual);
        $this->assertSame(4.0, (float) $insumo->fresh()->estoqueAtual());
    }

    public function test_it_supports_inventory_adjustments(): void
    {
        $service = app(MovimentacaoEstoqueService::class);

        $prepared = $service->prepareForPersistence([
            'tipo' => 'ajuste',
            'quantidade' => 3,
            'motivo' => 'Inventario ciclico',
        ], 10);

        $this->assertSame(-7.0, $prepared['impacto_estoque']);
        $this->assertSame(10.0, $prepared['saldo_anterior']);
        $this->assertSame(3.0, $prepared['saldo_atual']);
    }

    public function test_it_creates_product_movements_and_computes_total_value(): void
    {
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-MOV-01',
            'nome' => 'Produto de teste',
            'status' => 'ativo',
            'preco_tabela' => 120.00,
            'preco_minimo' => 100.00,
        ]);

        $service = app(MovimentacaoEstoqueService::class);

        $movimentacao = $service->createForProduto([
            'produto_id' => $produto->id,
            'tipo' => 'entrada',
            'quantidade' => 2,
            'valor_unitario' => 10,
            'realizado_em' => now(),
        ]);

        $this->assertSame(20.0, (float) $movimentacao->valor_total);
        $this->assertSame(2.0, (float) $movimentacao->saldo_atual);
        $this->assertSame(2.0, (float) $produto->fresh()->estoqueAtual());
    }

    public function test_it_blocks_outbound_movements_above_current_balance(): void
    {
        $insumo = Insumo::query()->create([
            'codigo_interno' => 'INS-MOV-02',
            'nome' => 'Insumo com saldo limitado',
            'origem' => 'nacional',
            'custo_referencia' => 12.5000,
        ]);

        $service = app(MovimentacaoEstoqueService::class);

        $service->createForInsumo([
            'insumo_id' => $insumo->id,
            'tipo' => 'entrada',
            'quantidade' => 2,
            'realizado_em' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $service->createForInsumo([
            'insumo_id' => $insumo->id,
            'tipo' => 'saida',
            'quantidade' => 3,
            'motivo' => 'Separacao',
            'realizado_em' => now()->addMinute(),
        ]);
    }
}
