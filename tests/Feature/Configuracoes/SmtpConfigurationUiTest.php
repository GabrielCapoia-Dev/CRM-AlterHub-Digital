<?php

namespace Tests\Feature\Configuracoes;

use App\Enum\RolesEnum;
use App\Filament\Resources\DocumentoConfiguracoes\Pages\ManageDocumentoConfiguracoes;
use App\Models\Acesso\User;
use App\Models\DocumentoConfiguracao;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SmtpConfigurationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_never_hydrates_the_existing_smtp_password(): void
    {
        $user = User::factory()->create(['email_approved' => true]);
        Role::findOrCreate(RolesEnum::SuperAdmin->value, 'web');
        $user->assignRole(RolesEnum::SuperAdmin->value);

        $settings = DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
            'logo_disk' => 'local',
            'logo_path' => 'documentos/logotipos/teste.png',
            'razao_social' => 'Unibiotech Brasil Ltda.',
            'cnpj' => '04.252.011/0001-10',
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sistema@unibiotechbrasil.com.br',
            'smtp_password' => 'SegredoQueNaoPodeVazar@123',
            'mail_from_address' => 'sistema@unibiotechbrasil.com.br',
            'mail_from_name' => 'Unibiotech',
        ]);

        Filament::setCurrentPanel(Filament::getPanel('painel'));
        $this->actingAs($user);

        Livewire::test(ManageDocumentoConfiguracoes::class)
            ->mountTableAction('edit', $settings)
            ->assertTableActionDataSet([
                'smtp_password' => null,
            ]);
    }
}
