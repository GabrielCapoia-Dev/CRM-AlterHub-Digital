<?php

namespace Tests\Feature\CRM;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\OportunidadeInteracao;
use App\Models\OportunidadeMovimentacao;
use App\Models\OportunidadeProduto;
use App\Models\OportunidadeTarefa;
use App\Models\Produto;
use App\Policies\EtapaPolicy;
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
            'name' => 'Usuário com permissão',
            'email' => 'permissao.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $semPermissao = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Usuário sem permissão',
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

        $this->assertTrue(app(EtapaPolicy::class)->viewAny($user));
        $this->assertTrue(app(OportunidadePolicy::class)->create($user));
        $this->assertTrue(app(OportunidadeProdutoPolicy::class)->create($user));
        $this->assertTrue(app(OportunidadeInteracaoPolicy::class)->update($user, $interacao));
        $this->assertTrue(app(OportunidadeTarefaPolicy::class)->delete($user, $tarefa));
        $this->assertTrue(app(OportunidadeMovimentacaoPolicy::class)->view($user, $movimentacao));
        $this->assertTrue(app(OportunidadeMovimentacaoPolicy::class)->delete($user, $movimentacao));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->create($user));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->update($user, $movimentacao));

        $this->assertFalse(app(ProdutoPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(EtapaPolicy::class)->create($semPermissao));
        $this->assertFalse(app(OportunidadePolicy::class)->update($semPermissao, $oportunidade));
        $this->assertFalse(app(OportunidadeProdutoPolicy::class)->delete($semPermissao, $oportunidadeProduto));
        $this->assertFalse(app(OportunidadeInteracaoPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(OportunidadeTarefaPolicy::class)->create($semPermissao));
        $this->assertFalse(app(OportunidadeMovimentacaoPolicy::class)->viewAny($semPermissao));
    }
}
