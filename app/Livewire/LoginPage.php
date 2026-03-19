<?php

namespace App\Livewire;

use Caresome\FilamentAuthDesigner\Pages\Auth\Login as BaseLogin;
use Filament\Forms;
use Filament\Actions;
use Filament\Schemas\Schema;


class LoginPage extends BaseLogin
{
    protected static string $layout = 'layouts.login-page';


    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Forms\Components\TextInput::make('email')
                ->label('Email')
                ->type('email')
                ->required()
                ->placeholder('exemplo@exemplo.com')
                ->autocomplete('username'),

            Forms\Components\TextInput::make('password')
                ->label('Senha')
                ->password()
                ->required()
                ->placeholder('************')
                ->autocomplete('current-password'),
        ]);
    }


    protected function getFormActions(): array
    {
        return [
            // Botão de login padrão (manual)
            Actions\Action::make('authenticate')  // troque 'login' por 'authenticate'
                ->label('Entrar no Sistema')
                ->color('primary')
                ->submit('authenticate'),
        ];
    }
}