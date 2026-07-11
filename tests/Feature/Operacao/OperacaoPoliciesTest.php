<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Filament\Pages\Operacao\DashboardBiPage;
use App\Filament\Pages\Operacao\LucroPorProdutoPage;
use App\Filament\Pages\Operacao\ResultadoOperacaoPage;
use App\Filament\Pages\Operacao\VisaoConsolidadaPage;
use App\Models\Acesso\User;
use App\Models\DespesaOperacional;
use App\Models\VendaOperacao;
use App\Policies\DespesaOperacionalPolicy;
use App\Policies\VendaOperacaoPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperacaoPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_operacao_policies_and_pages_follow_permissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operacao com permissao',
            'email' => 'operacao.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $semPermissao = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Operacao sem permissao',
            'email' => 'operacao-sem.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $permissoes = [
            PermissoesEnum::ListarDespesasOperacionais,
            PermissoesEnum::CriarDespesasOperacionais,
            PermissoesEnum::EditarDespesasOperacionais,
            PermissoesEnum::ExcluirDespesasOperacionais,
            PermissoesEnum::ListarVendasOperacao,
            PermissoesEnum::CriarVendasOperacao,
            PermissoesEnum::EditarVendasOperacao,
            PermissoesEnum::ExcluirVendasOperacao,
            PermissoesEnum::ListarVisaoConsolidadaOperacao,
            PermissoesEnum::ListarResultadoOperacao,
            PermissoesEnum::ListarLucroPorProduto,
            PermissoesEnum::ListarDashboardBI,
        ];

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
        }

        $user->givePermissionTo(collect($permissoes)->map(fn (PermissoesEnum $permissao) => $permissao->value)->all());

        $despesa = new DespesaOperacional();
        $venda = new VendaOperacao();

        $this->assertTrue(app(DespesaOperacionalPolicy::class)->viewAny($user));
        $this->assertTrue(app(DespesaOperacionalPolicy::class)->create($user));
        $this->assertTrue(app(DespesaOperacionalPolicy::class)->update($user, $despesa));
        $this->assertTrue(app(DespesaOperacionalPolicy::class)->delete($user, $despesa));

        $this->assertTrue(app(VendaOperacaoPolicy::class)->viewAny($user));
        $this->assertTrue(app(VendaOperacaoPolicy::class)->create($user));
        $this->assertTrue(app(VendaOperacaoPolicy::class)->update($user, $venda));
        $this->assertTrue(app(VendaOperacaoPolicy::class)->delete($user, $venda));

        $this->assertFalse(app(DespesaOperacionalPolicy::class)->viewAny($semPermissao));
        $this->assertFalse(app(VendaOperacaoPolicy::class)->create($semPermissao));

        $this->actingAs($user);
        $this->assertTrue(VisaoConsolidadaPage::canAccess());
        $this->assertTrue(ResultadoOperacaoPage::canAccess());
        $this->assertTrue(LucroPorProdutoPage::canAccess());
        $this->assertTrue(DashboardBiPage::canAccess());

        $this->actingAs($semPermissao);
        $this->assertFalse(VisaoConsolidadaPage::canAccess());
        $this->assertFalse(ResultadoOperacaoPage::canAccess());
        $this->assertFalse(LucroPorProdutoPage::canAccess());
        $this->assertFalse(DashboardBiPage::canAccess());
    }
}
