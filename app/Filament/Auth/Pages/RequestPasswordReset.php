<?php

namespace App\Filament\Auth\Pages;

use Caresome\FilamentAuthDesigner\Pages\Auth\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function getTitle(): string|Htmlable
    {
        return 'Recuperar acesso';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Recupere seu acesso ao portal';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $loginUrl = e(filament()->getLoginUrl());

        return new HtmlString(
            'Informe o e-mail da sua conta e enviaremos as instruções. '
            .'<a class="auth-inline-link" href="'.$loginUrl.'">Voltar ao login</a>'
        );
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'email_approved' => true,
        ];
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return $this->genericRequestNotification();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->genericRequestNotification();
    }

    protected function getRequestFormAction(): Action
    {
        return parent::getRequestFormAction()
            ->label('Enviar link de redefinição')
            ->icon('heroicon-m-paper-airplane')
            ->iconPosition('after');
    }

    private function genericRequestNotification(): Notification
    {
        return Notification::make()
            ->title('Confira seu e-mail')
            ->body('Se houver uma conta aprovada com esse endereço, você receberá as instruções para criar uma nova senha.')
            ->success();
    }
}
