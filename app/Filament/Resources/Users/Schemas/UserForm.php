<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User */
        $user = Auth::user();
        $userService = app(UserService::class);
        $roleService = app(RoleService::class);

        return $schema->components([
            TextInput::make('name')
                ->label('Nome:')
                ->required()
                ->minLength(3)
                ->maxLength(100)
                ->rule('regex:/^[\p{L}\p{N}]+(?: [\p{L}\p{N}]+)*$/u')
                ->validationMessages(['regex' => 'Use apenas letras, sem caracteres especiais.']),

            TextInput::make('email')
                ->label('E-mail')
                ->unique(ignoreRecord: true)
                ->email()
                ->required(),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->helperText('Mín. 8 e máx. 30 caracteres. Deve conter letras maiúsculas, minúsculas, números e caracteres especiais.')
                ->minLength(8)
                ->maxLength(30)
                ->rules([
                    'nullable',
                    'max:30',
                    PasswordRule::min(8)->mixedCase()->numbers()->symbols(),
                ])
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context): bool => $context === 'create')
                ->validationMessages(['max' => 'A senha deve ter no máximo 30 caracteres.']),

            Select::make('role')
                ->label('Nivel de acesso')
                ->helperText('Necessário para delimitar as ações do usuário no sistema.')
                ->relationship('roles', 'name', fn (Builder $query) => $userService->opcoesDeRoles($query, $user))
                ->preload()
                ->searchable()
                ->required()
                ->disabled(fn (?User $record, string $context) => $userService->desabilitarCampoRole($user, $record, $context)),

            Toggle::make('email_approved')
                ->label('Verificação de acesso')
                ->helperText('Ative para permitir o acesso ao sistema.')
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(true)
                ->visible(fn (?User $record, string $context) => $userService->podeVerToggleAprovacaoEmail($user, $record, $context)),

            Toggle::make('usar_permissoes_extras')
                ->label('Permissões adicionais')
                ->helperText('Ative para conceder permissões específicas além do nível de acesso.')
                ->default(fn (?User $record) => $record?->getDirectPermissions()->isNotEmpty())
                ->onColor('warning')
                ->offColor('info')
                ->onIcon('heroicon-s-lock-open')
                ->offIcon('heroicon-s-lock-closed')
                ->disabled(fn () => ! $roleService->ehSuperAdmin($user))
                ->visible(function (?User $record) use ($roleService, $user) {
                    if (! $roleService->ehSuperAdmin($user)) {
                        return false;
                    }
                    if (! $record) {
                        return true;
                    }
                    if ($record->id === $user->id) {
                        return false;
                    }
                    if ($roleService->ehSuperAdmin($record)) {
                        return false;
                    }

                    return true;
                })
                ->live(),

            Components\Section::make('Permissões específicas')
                ->collapsible()
                ->columnSpanFull()
                ->description('Marque somente permissões diretas. As permissões do nível de acesso são herdadas automaticamente.')
                ->visible(fn (Get $get) => $get('usar_permissoes_extras') === true)
                ->schema(function (?User $record) use ($roleService, $user) {
                    if (! $user || ! $roleService->ehSuperAdmin($user)) {
                        return [];
                    }
                    if ($record && $record->id === $user->id) {
                        return [];
                    }
                    if ($record && $roleService->ehSuperAdmin($record)) {
                        return [];
                    }

                    $atribuiveis = $roleService->permissoesAtribuiveis($user);

                    return [
                        \Filament\Forms\Components\CheckboxList::make('permissions')
                            ->label('Permissões adicionais')
                            ->options($atribuiveis->mapWithKeys(
                                fn (string $name): array => [$name => $name],
                            )->all())
                            ->default(fn (): array => $record?->getDirectPermissions()
                                ->pluck('name')
                                ->intersect($atribuiveis)
                                ->values()
                                ->all() ?? [])
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->columnSpanFull(),
                    ];
                }),
        ]);
    }
}
