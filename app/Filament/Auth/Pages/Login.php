<?php

namespace App\Filament\Auth\Pages;

use Caresome\FilamentAuthDesigner\Pages\Auth\Login as BaseLogin;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getTitle(): string|Htmlable
    {
        return 'Acesso Unibiotech';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Bem-vindo à Unibiotech';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Acesse o portal de gestão com seu e-mail e senha.';
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Acessar o portal')
            ->icon('heroicon-m-arrow-right')
            ->iconPosition('after');
    }
}
