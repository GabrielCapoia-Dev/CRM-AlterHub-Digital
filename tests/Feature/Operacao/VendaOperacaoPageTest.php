<?php

namespace Tests\Feature\Operacao;

use App\Enum\PermissoesEnum;
use App\Filament\Resources\VendasOperacao\Pages\ManageVendasOperacao;
use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use App\Models\Acesso\User;
use App\Models\VendaOperacaoPedido;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_sales_decision_modal_uses_safe_width_and_footer_alignment(): void
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

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $this->actingAs($user);

        $component = Livewire::test(ManageVendasOperacao::class);
        $action = $component->instance()->getTable()->getAction('approveDiscount');

        $this->assertNotNull($action);
        $this->assertSame(Width::TwoExtraLarge, $action->getModalWidth());
        $this->assertSame(Alignment::Start, $action->getModalAlignment());
        $this->assertSame(Alignment::End, $action->getModalFooterActionsAlignment());
        $this->assertSame('Aprovar venda', $action->getModalSubmitActionLabel());
        $this->assertSame('Cancelar', $action->getModalCancelActionLabel());
        $this->assertSame(
            'oa-record-modal oa-decision-modal',
            $action->getExtraModalWindowAttributes()['class'] ?? null,
        );
    }

    public function test_sale_creation_queues_only_one_contextual_notification(): void
    {
        $action = VendaOperacaoResource::configureCreateAction(CreateAction::make());

        $pending = (new VendaOperacaoPedido)->forceFill([
            'status' => VendaOperacaoPedido::STATUS_PENDENTE_APROVACAO,
        ]);

        session()->forget('filament.notifications');
        $action->record($pending)->sendSuccessNotification();

        $notifications = session()->get('filament.notifications', []);
        $this->assertCount(1, $notifications);
        $this->assertSame('Venda enviada para aprovacao', $notifications[0]['title']);

        $draft = (new VendaOperacaoPedido)->forceFill([
            'status' => VendaOperacaoPedido::STATUS_RASCUNHO,
        ]);

        session()->forget('filament.notifications');
        $action->record($draft)->sendSuccessNotification();

        $notifications = session()->get('filament.notifications', []);
        $this->assertCount(1, $notifications);
        $this->assertSame('Rascunho de venda criado', $notifications[0]['title']);
    }
}
