<?php

namespace Tests\Feature\Configuracoes;

use App\Models\DocumentoConfiguracao;
use App\Services\Email\SmtpConfigurationService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class SmtpConfigurationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_password_is_encrypted_and_hidden_from_serialization(): void
    {
        $settings = $this->createSettings();
        $rawPassword = DB::table('configuracoes_documentos')
            ->where('id', $settings->id)
            ->value('smtp_password');

        $this->assertIsString($rawPassword);
        $this->assertNotSame('SenhaSmtp@123', $rawPassword);
        $this->assertStringNotContainsString('SenhaSmtp@123', $rawPassword);
        $this->assertSame('SenhaSmtp@123', $settings->fresh()->smtp_password);
        $this->assertArrayNotHasKey('smtp_password', $settings->fresh()->toArray());
    }

    public function test_stored_configuration_replaces_and_refreshes_the_runtime_mailer(): void
    {
        $settings = $this->createSettings();
        $service = app(SmtpConfigurationService::class);

        $this->assertTrue($service->applyStoredConfiguration());
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.hostinger.com', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertFalse(config('mail.mailers.smtp.require_tls'));
        $this->assertSame('sistema@unibiotechbrasil.com.br', config('mail.from.address'));

        $settings->update([
            'smtp_host' => 'smtp-secundario.example.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => 'outro-usuario',
            'smtp_password' => 'NovaSenha@456',
        ]);

        // O hook registrado no worker deve recarregar o banco e descartar o
        // mailer antigo antes de processar cada job.
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([]);

        Event::dispatch(new JobProcessing('redis', $job));

        $this->assertSame('smtp-secundario.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.require_tls'));
        $this->assertSame('outro-usuario', config('mail.mailers.smtp.username'));
        $this->assertSame('NovaSenha@456', config('mail.mailers.smtp.password'));
    }

    public function test_disabled_or_incomplete_smtp_keeps_the_environment_fallback(): void
    {
        $fallback = config('mail.default');
        $settings = $this->createSettings();
        $service = app(SmtpConfigurationService::class);

        $this->assertTrue($service->applyStoredConfiguration());
        $this->assertSame('smtp', config('mail.default'));

        $settings->update(['smtp_enabled' => false]);

        $this->assertFalse($service->applyStoredConfiguration());
        $this->assertSame($fallback, config('mail.default'));

        $settings->update([
            'smtp_enabled' => true,
            'smtp_password' => null,
        ]);

        $this->assertFalse($service->applyStoredConfiguration());
        $this->assertSame($fallback, config('mail.default'));
    }

    private function createSettings(): DocumentoConfiguracao
    {
        return DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
            'smtp_enabled' => true,
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'sistema@unibiotechbrasil.com.br',
            'smtp_password' => 'SenhaSmtp@123',
            'mail_from_address' => 'sistema@unibiotechbrasil.com.br',
            'mail_from_name' => 'Unibiotech',
        ]);
    }
}
