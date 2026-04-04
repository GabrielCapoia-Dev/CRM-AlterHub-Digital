<?php

namespace Tests\Feature\CRM;

use App\Enum\PermissoesEnum;
use App\Filament\Resources\Oportunidades\OportunidadeResource;
use App\Filament\Resources\Oportunidades\Pages\KanbanOportunidades;
use App\Models\Acesso\User;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\Status\StatusCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class KanbanOportunidadesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_kanban_index_and_keeps_the_support_list_route_available(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$user, $cliente, $lead, $negociacao] = $this->criarBaseDeKanban();

        Oportunidade::create([
            'titulo' => 'Pipeline reagentes',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
            'valor_estimado' => 1500,
        ]);

        $this->actingAs($user);

        $kanbanUrl = OportunidadeResource::getUrl();
        $listUrl = OportunidadeResource::getUrl('list');

        $this->assertStringEndsWith('/painel/oportunidades', $kanbanUrl);
        $this->assertStringEndsWith('/painel/oportunidades/lista', $listUrl);

        $this->get($kanbanUrl)
            ->assertOk()
            ->assertSeeText('CRM - Kanban')
            ->assertSeeText('Pipeline comercial');

        $this->get($listUrl)
            ->assertOk();
    }

    public function test_it_creates_a_new_opportunity_from_the_drawer(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$user, $cliente, $lead] = $this->criarBaseDeKanban();

        $this->actingAs($user);

        $component = app(KanbanOportunidades::class);
        $component->mount();
        $component->openCreateDrawer($lead->id);
        $component->opportunityForm['titulo'] = 'Nova oportunidade Kanban';
        $component->opportunityForm['cliente_id'] = $cliente->id;
        $component->opportunityForm['etapa_id'] = $lead->id;
        $component->opportunityForm['user_id'] = $user->id;
        $component->opportunityForm['temperatura'] = 'hot';
        $component->opportunityForm['valor_estimado'] = 3200.50;
        $component->saveOpportunity();

        $this->assertDatabaseHas('oportunidades', [
            'titulo' => 'Nova oportunidade Kanban',
            'cliente_id' => $cliente->id,
            'etapa_id' => $lead->id,
            'user_id' => $user->id,
            'temperatura' => 'hot',
        ]);

        $this->assertSame('edit', $component->drawerMode);
        $this->assertSame(
            Oportunidade::query()->where('titulo', 'Nova oportunidade Kanban')->value('id'),
            $component->selectedOpportunityId,
        );
    }

    public function test_it_requires_a_reason_to_close_an_opportunity_from_the_kanban(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        [$user, $cliente, $lead, $negociacao, $perdido] = $this->criarBaseDeKanban(includeClosingStage: true);

        $oportunidade = Oportunidade::create([
            'titulo' => 'Cliente hospitalar',
            'cliente_id' => $cliente->id,
            'etapa_id' => $negociacao->id,
            'user_id' => $user->id,
            'temperatura' => 'warm',
        ]);

        $this->actingAs($user);

        $component = app(KanbanOportunidades::class);
        $component->mount();
        $component->handleStageDrop($oportunidade->id, $perdido->id);

        $this->assertTrue($component->closingReasonModalOpen);
        $this->assertSame($oportunidade->id, $component->pendingMoveOpportunityId);
        $this->assertSame($perdido->id, $component->pendingMoveStageId);

        $component->pendingMoveReason = 'Concorrente venceu por preco.';
        $component->confirmPendingStageMove();

        $this->assertFalse($component->closingReasonModalOpen);

        $this->assertDatabaseHas('oportunidades', [
            'id' => $oportunidade->id,
            'etapa_id' => $perdido->id,
            'motivo_fechamento' => 'Concorrente venceu por preco.',
        ]);

        $this->assertDatabaseHas('oportunidade_movimentacoes', [
            'oportunidade_id' => $oportunidade->id,
            'etapa_origem_id' => $negociacao->id,
            'etapa_destino_id' => $perdido->id,
            'motivo' => 'Concorrente venceu por preco.',
        ]);
    }

    private function criarBaseDeKanban(bool $includeClosingStage = false): array
    {
        $grantedPermissions = [
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
        ];

        foreach (PermissoesEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

        $user = User::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Gestor CRM',
            'email' => 'kanban.' . Str::random(8) . '@teste.com',
            'email_approved' => true,
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $user->givePermissionTo(collect($grantedPermissions)->map(fn (PermissoesEnum $permission) => $permission->value)->all());

        $status = StatusCliente::create(['nome' => 'Ativo']);
        $segmento = CategoriaSegmento::create(['nome' => 'Laboratorio']);

        $cliente = Cliente::create([
            'razao_social' => 'Cliente Kanban Ltda',
            'nome_fantasia' => 'Cliente Kanban',
            'cnpj' => '12.345.678/0001-90',
            'id_status_cliente' => $status->id,
            'id_categoria_segmento' => $segmento->id,
            'nome_completo' => 'Contato Kanban',
            'email' => 'contato@kanban.test',
        ]);

        $lead = Etapa::create([
            'nome' => 'Lead',
            'slug' => 'lead',
            'ordem' => 1,
            'cor' => '#1d4ed8',
            'fechamento' => false,
        ]);

        $negociacao = Etapa::create([
            'nome' => 'Negociacao',
            'slug' => 'negociacao',
            'ordem' => 2,
            'cor' => '#c2410c',
            'fechamento' => false,
        ]);

        if (! $includeClosingStage) {
            return [$user, $cliente, $lead, $negociacao];
        }

        $perdido = Etapa::create([
            'nome' => 'Perdido',
            'slug' => 'perdido',
            'ordem' => 3,
            'cor' => '#be123c',
            'fechamento' => true,
        ]);

        return [$user, $cliente, $lead, $negociacao, $perdido];
    }
}
