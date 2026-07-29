<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Enum\SeparacaoStatus;
use App\Filament\Resources\PedidosSeparacao\PedidoSeparacaoResource;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\VendaOperacaoLote;
use App\Services\Documentos\VendaFotoService;
use App\Services\Documentos\VendaSeparacaoService;
use App\Services\Operacao\VendaOperacaoService;
use App\Services\Operacao\VendaWorkflowService;
use App\Services\Produtos\MovimentacaoEstoqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PedidoSeparacaoFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_confirmacao_envia_pedido_para_fila_e_separacao_exige_lote_foto_e_peso(): void
    {
        $actor = $this->createActor();
        $pedido = $this->createConfirmedPedido($actor);
        $item = $pedido->vendasOperacao->firstOrFail();
        $service = app(VendaSeparacaoService::class);

        $this->assertSame(SeparacaoStatus::Aguardando->value, $pedido->separacao_status);

        try {
            $service->concluirSeparacao($pedido, $actor);
            $this->fail('A separação incompleta deveria ser recusada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('separacao', $exception->errors());
        }

        VendaOperacaoLote::query()->create([
            'venda_operacao_id' => $item->id,
            'user_id' => $actor->id,
            'numero_lote' => 'LOTE-FILA-1',
            'quantidade' => (float) $item->quantidade,
            'data_fabricacao' => now()->subMonth()->toDateString(),
            'data_validade' => now()->addYear()->toDateString(),
        ]);
        $path = "pedidos/{$pedido->id}/fotos/produto.png";
        Storage::disk('local')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        app(VendaFotoService::class)->registrarArquivoArmazenado(
            $pedido,
            $path,
            'produto.png',
            $actor,
            $item,
        );
        $service->atualizarObservacaoPedido($pedido, 'Conferido pela expedição.', $actor);

        $separado = $service->concluirSeparacao($pedido, $actor);

        $this->assertSame(SeparacaoStatus::Separado->value, $separado->separacao_status);
        $this->assertSame('Conferido pela expedição.', $separado->separacao_observacao);
        $this->assertSame($actor->id, $separado->separado_por);
        $this->assertNotNull($separado->separado_em);
        $this->assertDatabaseHas('venda_pedido_fotos', [
            'venda_operacao_pedido_id' => $pedido->id,
            'venda_operacao_id' => $item->id,
            'path' => $path,
        ]);
    }

    public function test_tela_lista_confirmados_e_destaca_pedido_retornado_do_romaneio(): void
    {
        $actor = $this->createActor();
        $pedido = $this->createConfirmedPedido($actor);
        $pedido->forceFill([
            'separacao_status' => SeparacaoStatus::RetornadoRomaneio->value,
            'retornado_romaneio_em' => now(),
            'retorno_romaneio_descricao' => 'Retornado do romaneio ROM-000123: rota cancelada.',
        ])->save();

        $this->actingAs($actor)
            ->get(PedidoSeparacaoResource::getUrl())
            ->assertOk()
            ->assertSeeText($pedido->codigo)
            ->assertSeeText('Retornado do romaneio')
            ->assertSeeText('rota cancelada');
    }

    private function createActor(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            PermissoesEnum::ListarVendasOperacao,
            PermissoesEnum::CadastrarLotesValidades,
            PermissoesEnum::AdicionarFotosPedido,
            PermissoesEnum::VisualizarAnexosPedido,
            PermissoesEnum::GerarRomaneio,
            PermissoesEnum::ListarRomaneios,
        ];

        foreach (PermissoesEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $actor = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operador de separação',
            'email' => Str::random(8).'@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'email_approved' => true,
        ]);
        $actor->givePermissionTo(array_map(fn (PermissoesEnum $permission): string => $permission->value, $permissions));

        return $actor;
    }

    private function createConfirmedPedido(User $actor)
    {
        $cliente = Cliente::query()->create([
            'razao_social' => 'Cliente da separação',
            'cnpj' => '11.444.777/0001-61',
        ]);
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-SEP-'.Str::upper(Str::random(5)),
            'nome' => 'Produto para separar',
            'status' => 'ativo',
            'ativo' => true,
            'unidade_medida' => 'UN',
            'peso_unitario_kg' => 1.25,
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
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 2,
                'preco_unitario' => 20,
            ]],
        ], $actor);

        return app(VendaWorkflowService::class)
            ->confirmar($pedido, $actor, 'teste-separacao-'.$pedido->id)
            ->fresh(['vendasOperacao.produto']);
    }
}
