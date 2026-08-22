<?php

namespace Tests\Feature\Comercial;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Produto;
use App\Models\Status\StatusCliente;
use App\Policies\ClientePolicy;
use App\Services\Acesso\UserService;
use App\Services\CRM\OportunidadeClienteService;
use App\Services\Operacao\VendaOperacaoService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendedorClienteVendaFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_vendedor_cria_cliente_vinculado_a_si_e_enxerga_apenas_a_propria_carteira(): void
    {
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);
        $admin = $this->createUser(RolesEnum::Admin);

        $this->actingAs($vendedorA);
        $clienteA = $this->createCliente('Cliente A', $vendedorB);

        $this->assertSame($vendedorA->id, $clienteA->vendedor_id);

        $this->actingAs($admin);
        $clienteB = $this->createCliente('Cliente B', $vendedorB);

        $this->assertSame([$clienteA->id], Cliente::query()
            ->visiveisPara($vendedorA)
            ->pluck('id')
            ->all());
        $this->assertSame([$clienteB->id], Cliente::query()
            ->visiveisPara($vendedorB)
            ->pluck('id')
            ->all());
        $this->assertCount(2, Cliente::query()->visiveisPara($admin)->get());

        foreach ([PermissoesEnum::ListarClientes, PermissoesEnum::EditarClientes] as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
            $vendedorA->givePermissionTo($permissao->value);
        }

        $policy = app(ClientePolicy::class);
        $this->assertTrue($policy->view($vendedorA, $clienteA));
        $this->assertFalse($policy->view($vendedorA, $clienteB));
        $this->assertTrue($policy->update($vendedorA, $clienteA));
        $this->assertFalse($policy->update($vendedorA, $clienteB));
    }

    public function test_carteira_do_vendedor_e_hidratada_e_sincronizada_sem_apagar_vendas(): void
    {
        Permission::findOrCreate(PermissoesEnum::ListarUsuarios->value, 'web');
        Permission::findOrCreate(PermissoesEnum::EditarUsuarios->value, 'web');

        $admin = $this->createUser(RolesEnum::SuperAdmin);
        $admin->givePermissionTo([
            PermissoesEnum::ListarUsuarios->value,
            PermissoesEnum::EditarUsuarios->value,
        ]);
        $vendedor = $this->createUser(RolesEnum::Vendedor);

        $this->actingAs($admin);
        $clienteA = $this->createCliente('Carteira A');
        $clienteB = $this->createCliente('Carteira B');

        app(UserService::class)->sincronizarClientesDoVendedor($vendedor, [
            $clienteA->id,
            $clienteB->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('painel'));

        Livewire::test(EditUser::class, ['record' => $vendedor->getRouteKey()])
            ->assertSchemaStateSet([
                'cliente_ids' => [$clienteA->id, $clienteB->id],
            ]);

        app(UserService::class)->sincronizarClientesDoVendedor($vendedor, [$clienteB->id]);

        $this->assertNull($clienteA->fresh()->vendedor_id);
        $this->assertSame($vendedor->id, $clienteB->fresh()->vendedor_id);

        Role::findOrCreate(RolesEnum::Admin->value, 'web');
        $vendedor->syncRoles([RolesEnum::Admin->value]);
        app(UserService::class)->sincronizarClientesDoVendedor($vendedor, []);

        $this->assertNull($clienteB->fresh()->vendedor_id);
    }

    public function test_admin_cria_usuario_vendedor_ja_com_a_carteira_selecionada(): void
    {
        foreach ([
            PermissoesEnum::ListarUsuarios,
            PermissoesEnum::CriarUsuarios,
            PermissoesEnum::EditarUsuarios,
        ] as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
        }

        $admin = $this->createUser(RolesEnum::SuperAdmin);
        $admin->givePermissionTo([
            PermissoesEnum::ListarUsuarios->value,
            PermissoesEnum::CriarUsuarios->value,
            PermissoesEnum::EditarUsuarios->value,
        ]);
        $roleVendedor = Role::findOrCreate(RolesEnum::Vendedor->value, 'web');

        $this->actingAs($admin);
        $clienteA = $this->createCliente('Novo vendedor A');
        $clienteB = $this->createCliente('Novo vendedor B');

        Filament::setCurrentPanel(Filament::getPanel('painel'));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Vendedor Criado',
                'email' => 'vendedor.criado@example.com',
                'password' => 'Senha@123',
                'role' => $roleVendedor->id,
                'cliente_ids' => [$clienteA->id, $clienteB->id],
                'email_approved' => true,
                'usar_permissoes_extras' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vendedor = User::query()->where('email', 'vendedor.criado@example.com')->firstOrFail();

        $this->assertTrue($vendedor->hasRole(RolesEnum::Vendedor->value));
        $this->assertSame(
            [$clienteA->id, $clienteB->id],
            $vendedor->clientes()->orderBy('id')->pluck('clientes.id')->all(),
        );
    }

    public function test_venda_preserva_responsavel_historico_apos_transferencia_e_exclusao_do_vendedor(): void
    {
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);
        $admin = $this->createUser(RolesEnum::Admin);

        $this->actingAs($vendedorA);
        $cliente = $this->createCliente('Cliente transferivel');
        $produto = $this->createProduto();

        $service = app(VendaOperacaoService::class);
        $pedido = $service->createPedido([
            'cliente_id' => $cliente->id,
            'vendedor_user_id' => $vendedorB->id,
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'preco_unitario' => 20,
            ]],
        ], $vendedorA);

        $this->assertSame($vendedorA->id, $pedido->user_id);
        $this->assertSame($vendedorA->name, $pedido->vendedor_nome_snapshot);
        $this->assertSame($vendedorA->id, $pedido->vendasOperacao->first()->user_id);

        $this->actingAs($admin);
        $cliente->update(['vendedor_id' => $vendedorB->id]);

        $this->assertSame($vendedorA->id, $pedido->fresh()->user_id);
        $this->assertSame($vendedorA->name, $pedido->fresh()->vendedor_nome_snapshot);

        // O vendedor original ainda pode salvar o rascunho mantendo o cliente
        // historico, mas nao consegue selecionar outro cliente da nova carteira.
        $this->actingAs($vendedorA);
        $payload = $service->buildEditPayload($pedido->fresh());
        $updated = $service->updatePedido($pedido->fresh(), $payload, $vendedorA);
        $this->assertSame($cliente->id, $updated->cliente_id);

        $this->actingAs($admin);
        $vendedorA->delete();

        $this->assertDatabaseHas('venda_operacao_pedidos', [
            'id' => $pedido->id,
            'user_id' => null,
            'vendedor_nome_snapshot' => $vendedorA->name,
        ]);
        $this->assertDatabaseHas('vendas_operacao', [
            'venda_operacao_pedido_id' => $pedido->id,
            'user_id' => null,
            'vendedor_nome' => $vendedorA->name,
        ]);

        $pedidoSemUsuario = $pedido->fresh();
        $preservado = $service->updatePedido(
            $pedidoSemUsuario,
            $service->buildEditPayload($pedidoSemUsuario),
            $admin,
        );

        $this->assertNull($preservado->user_id);
        $this->assertSame($vendedorA->name, $preservado->vendedor_nome_snapshot);
        $this->assertNull($preservado->vendasOperacao->first()->user_id);
        $this->assertSame($vendedorA->name, $preservado->vendasOperacao->first()->vendedor_nome);

        $payloadComNovaEscolha = $service->buildEditPayload($preservado);
        $payloadComNovaEscolha['vendedor_user_id'] = $vendedorB->id;
        $reatribuido = $service->updatePedido($preservado, $payloadComNovaEscolha, $admin);

        $this->assertSame($vendedorB->id, $reatribuido->user_id);
        $this->assertSame($vendedorB->name, $reatribuido->vendedor_nome_snapshot);
        $this->assertSame($vendedorB->id, $reatribuido->vendasOperacao->first()->user_id);
    }

    public function test_admin_pode_escolher_vendedor_ou_assumir_a_venda_e_vendedor_nao_pode_escolher_outro(): void
    {
        $admin = $this->createUser(RolesEnum::Admin);
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);
        $produto = $this->createProduto();

        $this->actingAs($admin);
        $cliente = $this->createCliente('Cliente do vendedor A', $vendedorA);
        $service = app(VendaOperacaoService::class);

        $semVendedor = $service->createPedido($this->salePayload($cliente, $produto), $admin);
        $this->assertSame($admin->id, $semVendedor->user_id);
        $this->assertSame($admin->name, $semVendedor->vendedor_nome_snapshot);

        $comVendedor = $service->createPedido([
            ...$this->salePayload($cliente, $produto),
            'vendedor_user_id' => $vendedorA->id,
        ], $admin);
        $this->assertSame($vendedorA->id, $comVendedor->user_id);

        $this->actingAs($vendedorA);
        $vendaDoVendedor = $service->createPedido([
            ...$this->salePayload($cliente, $produto),
            'vendedor_user_id' => $vendedorB->id,
        ], $vendedorA);
        $this->assertSame($vendedorA->id, $vendaDoVendedor->user_id);

        $this->actingAs($admin);
        $clienteAlheio = $this->createCliente('Cliente do vendedor B', $vendedorB);
        $this->actingAs($vendedorA);

        try {
            $service->createPedido($this->salePayload($clienteAlheio, $produto), $vendedorA);
            $this->fail('A venda deveria rejeitar cliente fora da carteira do vendedor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cliente_id', $exception->errors());
        }
    }

    public function test_outro_admin_pode_editar_venda_sem_vendedor_preservando_o_admin_criador(): void
    {
        Role::findOrCreate(RolesEnum::Vendedor->value, 'web');
        $adminCriador = $this->createUser(RolesEnum::Admin);
        $adminEditor = $this->createUser(RolesEnum::Admin);
        $adminAlheio = $this->createUser(RolesEnum::Admin);
        $produto = $this->createProduto();

        $this->actingAs($adminCriador);
        $cliente = $this->createCliente('Cliente da venda administrativa');
        $service = app(VendaOperacaoService::class);
        $pedido = $service->createPedido($this->salePayload($cliente, $produto), $adminCriador);

        $this->assertSame($adminCriador->id, $pedido->user_id);

        $this->actingAs($adminEditor);
        $optionsMethod = new \ReflectionMethod(VendaOperacaoResource::class, 'vendedorOptions');
        $options = $optionsMethod->invoke(null, $adminCriador);

        $this->assertArrayHasKey($adminCriador->id, $options);
        $this->assertArrayHasKey($adminEditor->id, $options);
        $this->assertArrayNotHasKey($adminAlheio->id, $options);

        $pedido = $service->updatePedido(
            $pedido,
            $service->buildEditPayload($pedido),
            $adminEditor,
        );

        $this->assertSame($adminCriador->id, $pedido->user_id);
        $this->assertSame($adminCriador->name, $pedido->vendedor_nome_snapshot);
        $this->assertSame($adminCriador->id, $pedido->vendasOperacao->first()->user_id);
        $this->assertSame($adminCriador->name, $pedido->vendasOperacao->first()->vendedor_nome);

        $payloadInvalido = $service->buildEditPayload($pedido);
        $payloadInvalido['vendedor_user_id'] = $adminAlheio->id;

        try {
            $service->updatePedido($pedido, $payloadInvalido, $adminEditor);
            $this->fail('Um administrador terceiro nao deveria ser aceito como vendedor.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('vendedor_user_id', $exception->errors());
        }
    }

    public function test_vendedor_nao_consegue_transferir_cliente_por_payload_adulterado(): void
    {
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);

        $this->actingAs($vendedorA);
        $cliente = $this->createCliente('Cliente protegido');

        $cliente->update(['vendedor_id' => $vendedorB->id]);

        $this->assertSame($vendedorA->id, $cliente->fresh()->vendedor_id);
    }

    public function test_outro_perfil_com_permissao_de_edicao_nao_transfere_cliente_por_payload_adulterado(): void
    {
        Permission::findOrCreate(PermissoesEnum::EditarClientes->value, 'web');

        $admin = $this->createUser(RolesEnum::Admin);
        $usuario = $this->createUser(RolesEnum::Usuario);
        $usuario->givePermissionTo(PermissoesEnum::EditarClientes->value);
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);

        $this->actingAs($admin);
        $cliente = $this->createCliente('Cliente sem transferencia lateral', $vendedorA);

        $this->actingAs($usuario);
        $cliente->update(['vendedor_id' => $vendedorB->id]);

        $this->assertSame($vendedorA->id, $cliente->fresh()->vendedor_id);
    }

    public function test_novo_cliente_de_oportunidade_rejeita_documento_existente_fora_da_carteira(): void
    {
        $vendedorDaCarteira = $this->createUser(RolesEnum::Vendedor);
        $vendedorSolicitante = $this->createUser(RolesEnum::Vendedor);
        $documentoFiscal = '98.765.432/0001-10';

        $this->actingAs($vendedorDaCarteira);
        $clienteExistente = $this->createCliente('Cliente de outra carteira');
        $clienteExistente->update(['cnpj' => $documentoFiscal]);

        $this->actingAs($vendedorSolicitante);
        $service = app(OportunidadeClienteService::class);

        $this->assertNull($service->findByLookup($documentoFiscal));

        try {
            $service->resolveClientFromData([
                'client_mode' => OportunidadeClienteService::MODE_NEW,
                'client_razao_social' => 'Tentativa duplicada',
                'client_cnpj' => $documentoFiscal,
            ]);
            $this->fail('O documento duplicado deveria gerar um erro de validacao controlado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('client_cnpj', $exception->errors());
            $this->assertStringContainsString(
                'transferencia',
                $exception->errors()['client_cnpj'][0],
            );
        }

        $this->assertDatabaseCount('clientes', 1);
        $this->assertSame($vendedorDaCarteira->id, $clienteExistente->fresh()->vendedor_id);
    }

    public function test_oportunidade_antiga_pode_manter_cliente_transferido_sem_liberar_nova_selecao(): void
    {
        $vendedorA = $this->createUser(RolesEnum::Vendedor);
        $vendedorB = $this->createUser(RolesEnum::Vendedor);
        $admin = $this->createUser(RolesEnum::Admin);

        $this->actingAs($vendedorA);
        $cliente = $this->createCliente('Cliente da oportunidade');

        $this->actingAs($admin);
        $cliente->update(['vendedor_id' => $vendedorB->id]);
        $clienteAlheio = $this->createCliente('Outro cliente da carteira B', $vendedorB);

        $data = [
            'titulo' => 'Renovacao',
            'client_mode' => OportunidadeClienteService::MODE_EXISTING,
            'cliente_id' => $cliente->id,
            'client_lookup' => $cliente->codigo_interno,
            'etapa_id' => 1,
            'user_id' => $vendedorA->id,
            'temperatura' => 'warm',
        ];

        $this->actingAs($vendedorA);
        $service = app(OportunidadeClienteService::class);
        $this->assertNull($service->findVisibleById($clienteAlheio->id));
        $this->assertSame($cliente->id, $service->findVisibleById($cliente->id, $cliente->id)?->id);

        $payload = $service->prepareOpportunityData($data, $cliente->id);
        $this->assertSame($cliente->id, $payload['cliente_id']);

        $this->expectException(ValidationException::class);
        $service->prepareOpportunityData($data);
    }

    private function createUser(RolesEnum $role): User
    {
        Role::findOrCreate($role->value, 'web');

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function createCliente(string $nome, ?User $vendedor = null): Cliente
    {
        $status = StatusCliente::query()->firstOrCreate(['nome' => 'Ativo']);
        $segmento = CategoriaSegmento::query()->firstOrCreate(['nome' => 'Industrial']);

        return Cliente::query()->create([
            'razao_social' => $nome,
            'cnpj' => null,
            'id_status_cliente' => $status->id,
            'id_categoria_segmento' => $segmento->id,
            'vendedor_id' => $vendedor?->id,
        ]);
    }

    private function createProduto(): Produto
    {
        return Produto::query()->create([
            'nome' => 'Produto comercial '.fake()->unique()->numerify('###'),
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 5,
            'preco_tabela' => 20,
            'preco_minimo' => 10,
            'ativo' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function salePayload(Cliente $cliente, Produto $produto): array
    {
        return [
            'cliente_id' => $cliente->id,
            'data_venda' => now()->toDateString(),
            'itens' => [[
                'produto_id' => $produto->id,
                'quantidade' => 1,
                'preco_unitario' => 20,
            ]],
        ];
    }
}
