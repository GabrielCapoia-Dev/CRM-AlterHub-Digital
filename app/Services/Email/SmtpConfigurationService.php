<?php

namespace App\Services\Email;

use App\Models\DocumentoConfiguracao;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmtpConfigurationService
{
    /** @var array<string, mixed> */
    private array $fallbackMailConfiguration;

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly MailManager $mailManager,
    ) {
        $this->fallbackMailConfiguration = (array) $this->config->get('mail', []);
    }

    /**
     * Atualiza o mailer a partir do banco sem impedir o boot durante migrations.
     */
    public function applyStoredConfiguration(): bool
    {
        try {
            if (! Schema::hasTable('configuracoes_documentos')
                || ! Schema::hasColumns('configuracoes_documentos', [
                    'smtp_enabled',
                    'smtp_host',
                    'smtp_port',
                    'smtp_encryption',
                    'smtp_username',
                    'smtp_password',
                    'mail_from_address',
                    'mail_from_name',
                ])) {
                $this->restoreFallbackConfiguration();

                return false;
            }

            $settings = DocumentoConfiguracao::atual();

            if (! $settings?->smtp_enabled || ! $this->hasCompleteConfiguration($settings)) {
                $this->restoreFallbackConfiguration();

                return false;
            }

            $this->apply($settings);

            return true;
        } catch (Throwable) {
            // O e-mail continua usando o fallback do .env se o banco ainda nao
            // estiver pronto ou se uma configuracao armazenada estiver invalida.
            // A excecao nao e registrada para nunca serializar credenciais SMTP.
            $this->restoreFallbackConfiguration();

            return false;
        }
    }

    /**
     * @throws ValidationException
     */
    public function apply(DocumentoConfiguracao $settings): void
    {
        $smtp = $this->validatedConfiguration($settings);
        $fallbackSmtp = (array) data_get($this->fallbackMailConfiguration, 'mailers.smtp', []);

        $this->config->set('mail.default', 'smtp');
        $this->config->set('mail.mailers.smtp', [
            ...$fallbackSmtp,
            'transport' => 'smtp',
            'url' => null,
            'scheme' => $smtp['smtp_encryption'] === 'ssl' ? 'smtps' : 'smtp',
            'host' => $smtp['smtp_host'],
            'port' => $smtp['smtp_port'],
            'username' => $smtp['smtp_username'],
            'password' => $smtp['smtp_password'],
            'timeout' => 15,
            'auto_tls' => $smtp['smtp_encryption'] !== 'none',
            'require_tls' => $smtp['smtp_encryption'] === 'tls',
        ]);
        $this->config->set('mail.from.address', $smtp['mail_from_address']);
        $this->config->set('mail.from.name', $smtp['mail_from_name']);

        // Workers sao processos longos: um mailer ja resolvido precisa ser
        // descartado para que a proxima mensagem use os dados mais recentes.
        $this->mailManager->forgetMailers();
    }

    /**
     * Envia uma mensagem real e sincrona para validar autenticacao e entrega.
     *
     * @throws ValidationException
     */
    public function sendTest(DocumentoConfiguracao $settings, string $recipient): void
    {
        Validator::make(
            ['recipient' => $recipient],
            ['recipient' => ['required', 'email:rfc']],
            [],
            ['recipient' => 'destinatario'],
        )->validate();

        $this->apply($settings);

        Mail::mailer('smtp')->raw(
            'Este e um teste de envio do portal Unibiotech. A configuracao SMTP esta funcionando.',
            function (Message $message) use ($recipient): void {
                $message
                    ->to($recipient)
                    ->subject('Teste de e-mail - Portal Unibiotech');
            },
        );
    }

    private function hasCompleteConfiguration(DocumentoConfiguracao $settings): bool
    {
        try {
            $this->validatedConfiguration($settings);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @return array{
     *     smtp_host: string,
     *     smtp_port: int,
     *     smtp_encryption: string,
     *     smtp_username: string,
     *     smtp_password: string,
     *     mail_from_address: string,
     *     mail_from_name: string
     * }
     *
     * @throws ValidationException
     */
    private function validatedConfiguration(DocumentoConfiguracao $settings): array
    {
        /** @var array{
         *     smtp_host: string,
         *     smtp_port: int,
         *     smtp_encryption: string,
         *     smtp_username: string,
         *     smtp_password: string,
         *     mail_from_address: string,
         *     mail_from_name: string
         * } $validated
         */
        $validated = Validator::make([
            'smtp_host' => trim((string) $settings->smtp_host),
            'smtp_port' => $settings->smtp_port,
            'smtp_encryption' => (string) $settings->smtp_encryption,
            'smtp_username' => trim((string) $settings->smtp_username),
            'smtp_password' => (string) $settings->smtp_password,
            'mail_from_address' => trim((string) $settings->mail_from_address),
            'mail_from_name' => trim((string) $settings->mail_from_name),
        ], [
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => ['required', 'string'],
            'mail_from_address' => ['required', 'email:rfc', 'max:255'],
            'mail_from_name' => ['required', 'string', 'max:255'],
        ])->validate();

        return $validated;
    }

    private function restoreFallbackConfiguration(): void
    {
        $this->config->set('mail', $this->fallbackMailConfiguration);
        $this->mailManager->forgetMailers();
    }
}
