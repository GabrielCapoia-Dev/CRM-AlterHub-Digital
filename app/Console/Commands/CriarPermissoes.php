<?php

namespace App\Console\Commands;

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CriarPermissoes extends Command
{
    protected $signature = 'permissoes:criar';

    protected $description = 'Cria permissoes e vincula conjuntos padrao por role';

    public function handle(): int
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info('Criando permissoes...');

        foreach (PermissoesEnum::cases() as $permissao) {
            $permission = Permission::firstOrCreate([
                'name' => $permissao->value,
                'guard_name' => 'web',
            ]);

            if ($permission->wasRecentlyCreated) {
                $this->line("Criada: {$permissao->value}");
            }
        }

        $allPermissions = collect(PermissoesEnum::activeCases())
            ->map(fn (PermissoesEnum $permissao): string => $permissao->value)
            ->all();

        $superAdminRole = Role::firstOrCreate([
            'name' => RolesEnum::SuperAdmin->value,
            'guard_name' => 'web',
        ]);
        $superAdminRole->syncPermissions($allPermissions);
        $this->info('Permissoes sincronizadas com Super Admin.');

        $adminRole = Role::firstOrCreate([
            'name' => RolesEnum::Admin->value,
            'guard_name' => 'web',
        ]);
        if ($adminRole->wasRecentlyCreated) {
            $adminRole->syncPermissions($this->adminPermissions());
        }
        $this->info('Perfil Admin disponivel.');

        $gestorRole = Role::firstOrCreate([
            'name' => RolesEnum::Gestor->value,
            'guard_name' => 'web',
        ]);
        if ($gestorRole->wasRecentlyCreated) {
            $gestorRole->syncPermissions($this->gestorPermissions());
        }
        $this->info('Perfil Gestor disponivel.');

        $vendedorRole = Role::firstOrCreate([
            'name' => RolesEnum::Vendedor->value,
            'guard_name' => 'web',
        ]);
        if ($vendedorRole->wasRecentlyCreated) {
            $vendedorRole->syncPermissions($this->vendedorPermissions());
        }
        $this->info('Perfil Vendedor disponivel.');

        $estoquistaRole = Role::firstOrCreate([
            'name' => RolesEnum::Estoquista->value,
            'guard_name' => 'web',
        ]);
        if ($estoquistaRole->wasRecentlyCreated) {
            $estoquistaRole->syncPermissions($this->estoquistaPermissions());
        }
        $this->info('Perfil Estoquista disponivel.');

        Role::firstOrCreate([
            'name' => RolesEnum::Usuario->value,
            'guard_name' => 'web',
        ]);

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function vendedorPermissions(): array
    {
        return [
            PermissoesEnum::ListarClientes->value,
            PermissoesEnum::CriarClientes->value,
            PermissoesEnum::EditarClientes->value,
            PermissoesEnum::ListarProdutosCRM->value,
            PermissoesEnum::ListarEtapasCRM->value,
            PermissoesEnum::ListarOportunidades->value,
            PermissoesEnum::CriarOportunidades->value,
            PermissoesEnum::EditarOportunidades->value,
            PermissoesEnum::ListarProdutosDaOportunidade->value,
            PermissoesEnum::CriarProdutosDaOportunidade->value,
            PermissoesEnum::EditarProdutosDaOportunidade->value,
            PermissoesEnum::ExcluirProdutosDaOportunidade->value,
            PermissoesEnum::ListarInteracoesDeOportunidade->value,
            PermissoesEnum::CriarInteracoesDeOportunidade->value,
            PermissoesEnum::EditarInteracoesDeOportunidade->value,
            PermissoesEnum::ExcluirInteracoesDeOportunidade->value,
            PermissoesEnum::ListarTarefasDeOportunidade->value,
            PermissoesEnum::CriarTarefasDeOportunidade->value,
            PermissoesEnum::EditarTarefasDeOportunidade->value,
            PermissoesEnum::ExcluirTarefasDeOportunidade->value,
            PermissoesEnum::ListarMovimentacoesDeOportunidade->value,
            PermissoesEnum::ListarVendasOperacao->value,
            PermissoesEnum::CriarVendasOperacao->value,
            PermissoesEnum::EditarVendasOperacao->value,
            PermissoesEnum::GerarPdfPedido->value,
            PermissoesEnum::VisualizarAnexosPedido->value,
            PermissoesEnum::AdicionarFotosPedido->value,
        ];
    }

    /**
     * @return list<string>
     */
    protected function adminPermissions(): array
    {
        return collect(PermissoesEnum::activeCases())
            ->map(fn (PermissoesEnum $permissao): string => $permissao->value)
            ->reject(fn (string $permission): bool => in_array($permission, [
                PermissoesEnum::AplicarPermissoes->value,
                PermissoesEnum::EditarNivelDeAcessoAdmin->value,
            ], true))
            ->values()
            ->all();
    }

    /** @return list<string> */
    protected function estoquistaPermissions(): array
    {
        return [
            PermissoesEnum::ListarProdutosCRM->value,
            PermissoesEnum::MovimentarEstoqueProdutos->value,
            PermissoesEnum::ListarInsumos->value,
            PermissoesEnum::MovimentarEstoqueInsumos->value,
            PermissoesEnum::ListarVendasOperacao->value,
            PermissoesEnum::VisualizarTodasVendasOperacao->value,
            PermissoesEnum::SepararPedidos->value,
            PermissoesEnum::GerarPdfPedido->value,
            PermissoesEnum::VisualizarAnexosPedido->value,
            PermissoesEnum::AdicionarFotosPedido->value,
            PermissoesEnum::RemoverFotosPedido->value,
            PermissoesEnum::CadastrarLotesValidades->value,
            PermissoesEnum::GerarRomaneio->value,
            PermissoesEnum::ListarRomaneios->value,
            PermissoesEnum::ReimprimirRomaneio->value,
            PermissoesEnum::CancelarRomaneio->value,
        ];
    }

    /**
     * Gestores operam e aprovam o funil e as vendas, mas nao recebem por
     * heranca poderes de administracao, fiscal, producao ou edicao de estoque.
     *
     * @return list<string>
     */
    protected function gestorPermissions(): array
    {
        return array_values(array_unique([
            ...$this->vendedorPermissions(),
            PermissoesEnum::VisualizarTodasVendasOperacao->value,
            PermissoesEnum::SepararPedidos->value,
            PermissoesEnum::AprovarDesconto->value,
            PermissoesEnum::ListarDespesasOperacionais->value,
            PermissoesEnum::CriarDespesasOperacionais->value,
            PermissoesEnum::EditarDespesasOperacionais->value,
            PermissoesEnum::ListarResultadoOperacao->value,
            PermissoesEnum::ListarLucroPorProduto->value,
            PermissoesEnum::ListarDashboardBI->value,
            PermissoesEnum::RemoverFotosPedido->value,
            PermissoesEnum::CadastrarLotesValidades->value,
            PermissoesEnum::GerarRomaneio->value,
            PermissoesEnum::ListarRomaneios->value,
            PermissoesEnum::ReimprimirRomaneio->value,
            PermissoesEnum::CancelarRomaneio->value,
        ]));
    }
}
