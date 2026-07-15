<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use App\Models\Acesso\User;
use App\Models\VendaOperacaoPedido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VendaOperacaoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_page_renders_when_column_visibility_is_evaluated_without_a_record(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissoesEnum::cases() as $permissionCase) {
            Permission::findOrCreate($permissionCase->value, 'web');
        }

        $user = User::factory()->create([
            'email_approved' => true,
            'email_verified_at' => now(),
        ]);
        $user->givePermissionTo(PermissoesEnum::ListarVendasOperacao->value);

        VendaOperacaoPedido::query()->create([
            'user_id' => $user->id,
            'status' => VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO,
            'data_venda' => now()->toDateString(),
            'vendedor_nome_snapshot' => $user->name,
            'motivos_aprovacao' => [
                'estoque' => [[
                    'produto' => 'Produto teste',
                    'produto_id' => 123,
                    'solicitado' => 2,
                    'disponivel' => 1,
                    'deficit' => 1,
                ]],
            ],
        ]);

        $this->actingAs($user)
            ->get(VendaOperacaoResource::getUrl())
            ->assertOk()
            ->assertSeeText('Produto teste');
    }
}
