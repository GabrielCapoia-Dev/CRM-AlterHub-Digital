<?php

namespace App\Filament\Auth\Pages;

use Caresome\FilamentAuthDesigner\Pages\Auth\ResetPassword as BaseResetPassword;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;

class ResetPassword extends BaseResetPassword
{
    public function getTitle(): string|Htmlable
    {
        return 'Definir nova senha';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Crie sua nova senha';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Escolha uma senha forte para proteger seu acesso ao portal Unibiotech.';
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()
            ->label('Salvar nova senha')
            ->icon('heroicon-m-check')
            ->iconPosition('after');
    }
}
