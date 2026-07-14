<?php

namespace Tests\Feature\Operacao;

use App\Enum\VendaStatus;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\ProdutoReserva;
use App\Models\RegraTributaria;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VendaConcurrencyMySqlTest extends TestCase
{
    public function test_only_one_sale_reserves_the_last_available_unit(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Este teste de concorrencia exige MySQL.');
        }

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

        $actor = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Teste concorrente',
            'email' => 'concorrencia@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente concorrente',
            'uf' => 'SP',
        ]);
        RegraTributaria::query()->create([
            'nome' => 'Regra concorrencia SP',
            'uf_destino' => 'SP',
            'versao' => 1,
            'vigencia_inicio' => now()->subYear()->toDateString(),
            'aliquota_icms' => 12,
            'ativo' => true,
        ]);
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-CONCORRENCIA',
            'nome' => 'Ultima unidade',
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
            'quantidade' => 1,
            'valor_unitario' => 5,
            'realizado_em' => now(),
        ], $actor);

        $pedidos = collect([1, 2])->map(fn (): VendaOperacaoPedido => app(VendaOperacaoService::class)->createPedido([
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'preco_unitario' => 20,
            ]],
        ], $actor));

        [$pedidoA, $pedidoB] = $pedidos->pluck('id')->all();
        $actorId = $actor->id;

        $confirmar = static function (int $pedidoId, string $chave) use ($actorId): string {
            try {
                app(VendaWorkflowService::class)->confirmar(
                    VendaOperacaoPedido::query()->findOrFail($pedidoId),
                    User::query()->findOrFail($actorId),
                    $chave,
                );

                return 'confirmada';
            } catch (ValidationException) {
                return 'recusada_por_saldo';
            }
        };

        $resultados = Concurrency::driver('process')->run([
            static fn (): string => $confirmar($pedidoA, 'corrida:venda:a'),
            static fn (): string => $confirmar($pedidoB, 'corrida:venda:b'),
        ]);

        sort($resultados);

        $this->assertSame(['confirmada', 'recusada_por_saldo'], $resultados);
        $this->assertSame(1.0, (float) $produto->fresh()->estoque_reservado);
        $this->assertSame(1, ProdutoReserva::query()->where('status', 'ativa')->count());
        $this->assertSame(1, VendaOperacaoPedido::query()->where('status', VendaStatus::Confirmada->value)->count());
        $this->assertSame(1, VendaOperacaoPedido::query()->where('status', VendaStatus::Rascunho->value)->count());
    }
}
