<?php

namespace Tests\Feature\CRM;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
use App\Models\Empresas\Fornecedor;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeMovimentacao;
use App\Models\OportunidadeProduto;
use App\Models\OportunidadeTarefa;
use App\Models\Produto;
use App\Models\Produtos\Insumo;
use App\Policies\ClientePolicy;
use App\Policies\EtapaPolicy;
use App\Policies\FornecedorPolicy;
use App\Policies\InsumoPolicy;
use App\Policies\OportunidadeInteracaoPolicy;
use App\Policies\OportunidadeMovimentacaoPolicy;
use App\Policies\OportunidadePolicy;
use App\Policies\OportunidadeProdutoPolicy;
use App\Policies\OportunidadeTarefaPolicy;
use App\Policies\ProdutoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CrmPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_policies_follow_the_assigned_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario com permissao',
            'email' => 'permissao.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $semPermissao = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuario sem permissao',
            'email' => 'sem-permissao.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $permissoes = [
            PermissoesEnum::ListarProdutosCRM,
            PermissoesEnum::CriarProdutosCRM,
            PermissoesEnum::EditarProdutosCRM,
            PermissoesEnum::ExcluirProdutosCRM,
            PermissoesEnum::ListarClientes,
            PermissoesEnum::CriarClientes,
            PermissoesEnum::EditarClientes,
            PermissoesEnum::ExcluirClientes,
            PermissoesEnum::ListarFornecedores,
            PermissoesEnum::CriarFornecedores,
            PermissoesEnum::EditarFornecedores,
            PermissoesEnum::ExcluirFornecedores,
            PermissoesEnum::ListarInsumos,
            PermissoesEnum::CriarInsumos,
            PermissoesEnum::EditarInsumos,
            PermissoesEnum::ExcluirInsumos,
            PermissoesEnum::ListarEtapasCRM,
            PermissoesEnum::CriarEtapasCRM,
            PermissoesEnum::EditarEtapasCRM,
            PermissoesEnum::ExcluirEtapasCRM,
            PermissoesEnum::ListarOportunidades,
            PermissoesEnum::CriarOportunidades,
            PermissoesEnum::EditarOportunidades,
            PermissoesEnum::ExcluirOportunidades,
            PermissoesEnum::ListarProdutosDaOportunidade,
            PermissoesEnum::CriarProdutosDaOportunidade,
            PermissoesEnum::EditarProdutosDaOportunidade,
            PermissoesEnum::ExcluirProdutosDaOportunidade,
            PermissoesEnum::ListarInteracoesDeOportunidade,
            PermissoesEnum::CriarInteracoesDeOportunidade,
            PermissoesEnum::EditarInteracoesDeOportunidade,
            PermissoesEnum::ExcluirInteracoesDeOportunidade,
            PermissoesEnum::ListarTarefasDeOportunidade,
            PermissoesEnum::CriarTarefasDeOportunidade,
            PermissoesEnum::EditarTarefasDeOportunidade,
            PermissoesEnum::ExcluirTarefasDeOportunidade,
            PermissoesEnum::ListarMovimentacoesDeOportunidade,
            PermissoesEnum::ExcluirMovimentacoesDeOportunidade,
        ];

        foreach ($permissoes as $permissao) {
            Permission::create([
                'name' => $permissao->value,
                'guard_name' => 'web',
            ]);
        }

        $user->givePermissionTo(collect($permissoes)->map(fn (PermissoesEnum $permissao) => $permissao->value)->all());

        $produto = new Produto();
        $cliente = new Cliente();
        $fornecedor = new Fornecedor();
        $insumo = new Insumo();
        $etapa = new Etapa();
        $oportunidade = new Oportunidade();
        $oportunidadeProduto = new OportunidadeProduto();
        $interacao = new OportunidadeInteracao();
        $tarefa = new OportunidadeTarefa();
        $movimentacao = new OportunidadeMovimentacao();

        $this->assertTrue(app(ProdutoPolicy::class)->viewAny($user));
        $this->assertTrue(app(ProdutoPolicy::class)->create($user));
        $this->assertTrue(app(ProdutoPolicy::class)->update($user, $produto));
        $this->assertTrue(app(ProdutoPolicy::class)->delete($user, $produto));
        $this->assertTrue(app(ProdutoPolicy::class)->deleteAny($user));

        $this->assertTrue(app(ClientePolicy::class)->viewAny($user));
        $this->assertTrue(app(ClientePolicy::class)->create($user));
        $this->assertTrue(app(ClientePolicy::class)->update($user, $cliente));
        $this->assertTrue(app(ClientePolicy::class)->delete($user, $cliente));
        $this->assertTrue(app(ClientePolicy::class)->deleteAny($user));

        $this->assertTrue(app(FornecedorPolicy::class)->viewAny($user));
        $this->assertTrue(app(FornecedorPolicy::class)->create($user));
        $this->assertTrue(app(FornecedorPolicy::class)->update($user, $fornecedor));
        $this->assertTrue(app(FornecedorPolicy::class)->delete($user, $fornecedor));
        $this->assertTrue(app(FornecedorPolicy::class)->deleteAny($user));

        $this->assertTrue(app(InsumoPolicy::class)->viewAny($user));
        $this->assertTrue(app(InsumoPolicy::class)->create($user));
        $this->assertTrue(app(InsumoPolicy::class)->update($user, $insumo));
        $this->assertTrue(app(InsumoPolicy::class)->delete($user, $insumo));
        $this->assertTrue(app(InsumoPolicy::class)->deleteAny($user));

        $this->assertTrue(app(EtapaPolicy::class)->viewAny($user));
        $this->assertTrue(app(OportunidadePolicy::class)->create($user));
        $this->assertFalse(app(OportunidadePolicy::class)->deleteAny($user));
        $this->assertTrue(app(OportunidadeProdutoPolicy::class)->create($user));
        $this->assertTrue(app(OportunidadeInteracaoPolicy::class)->update($user, $interacao));
        $this->assertTrue(app(OportunidadeTarefaPolicy::class)->delete($user, $tarefa));
        $this->assertTrue(app(OportunidadeMovimentacaoPolicy::class)->view($user, $movimentacao));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->delete($user, $movimentacao));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->deleteAny($user));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->create($user));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->update($user, $movimentacao));

        $this->assertFalse(app(ProdutoPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(ClientePolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(FornecedorPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(InsumoPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(EtapaPolicy::class)->create($semPermissao));
        $this->assertFalse(app(OportunidadePolicy::class)->update($semPermissao, $oportunidade));
        $this->assertFalse(app(OportunidadeProdutoPolicy::class)->delete($semPermissao, $oportunidadeProduto));
        $this->assertFalse(app(OportunidadeInteracaoPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(OportunidadeTarefaPolicy::class)->create($semPermissao));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->viewAny($semPermissao));
    }
}
