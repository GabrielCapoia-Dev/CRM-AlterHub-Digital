<?php

namespace Tests\Feature;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\OrdemProducao;
use App\Models\RegraTributaria;
use App\Models\Transportadora;
use App\Policies\OrdemProducaoPolicy;
use App\Policies\RegraTributariaPolicy;
use App\Policies\TransportadoraPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProductionReadinessPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_domains_follow_their_crud_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            PermissoesEnum::ListarOrdensProducao,
            PermissoesEnum::CriarOrdensProducao,
            PermissoesEnum::EditarOrdensProducao,
            PermissoesEnum::ExcluirOrdensProducao,
            PermissoesEnum::ListarRegrasTributarias,
            PermissoesEnum::CriarRegrasTributarias,
            PermissoesEnum::EditarRegrasTributarias,
            PermissoesEnum::ExcluirRegrasTributarias,
            PermissoesEnum::ListarTransportadoras,
            PermissoesEnum::CriarTransportadoras,
            PermissoesEnum::EditarTransportadoras,
            PermissoesEnum::ExcluirTransportadoras,
        ];

        foreach ($permissions as $permission) {
            Permission::query()->create(['name' => $permission->value, 'guard_name' => 'web']);
        }

        $allowed = User::query()->create([
            'name' => 'Gestor de operacoes',
            'email' => 'gestor.operacoes@example.com',
            'email_verified_at' => now(),
            'email_approved' => true,
            'password' => 'password',
        ]);
        $allowed->givePermissionTo(collect($permissions)->pluck('value')->all());
        $denied = User::query()->create([
            'name' => 'Sem acesso',
            'email' => 'sem.acesso.operacoes@example.com',
            'email_verified_at' => now(),
            'email_approved' => true,
            'password' => 'password',
        ]);

        $this->assertCrudPolicy(app(OrdemProducaoPolicy::class), $allowed, $denied, new OrdemProducao);
        $this->assertCrudPolicy(app(RegraTributariaPolicy::class), $allowed, $denied, new RegraTributaria);
        $this->assertCrudPolicy(app(TransportadoraPolicy::class), $allowed, $denied, new Transportadora);
    }

    protected function assertCrudPolicy(object $policy, User $allowed, User $denied, object $model): void
    {
        $this->assertTrue($policy->viewAny($allowed));
        $this->assertTrue($policy->view($allowed, $model));
        $this->assertTrue($policy->create($allowed));
        $this->assertTrue($policy->update($allowed, $model));
        $this->assertTrue($policy->delete($allowed, $model));
        $this->assertTrue($policy->deleteAny($allowed));

        $this->assertFalse($policy->viewAny($denied));
        $this->assertFalse($policy->create($denied));
        $this->assertFalse($policy->update($denied, $model));
        $this->assertFalse($policy->delete($denied, $model));
    }
}
