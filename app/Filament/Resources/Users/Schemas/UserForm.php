<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Forms\Components\CheckboxList;
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
use Spatie\Permission\Models\Permission;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var \App\Models\Acesso\User */
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
                ->dehydrateStateUsing(fn($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $context): bool => $context === 'create')
                ->validationMessages(['max' => 'A senha deve ter no máximo 30 caracteres.']),

            Select::make('role')
                ->label('Nivel de acesso')
                ->helperText('Necessário para delimitar as ações do usuário no sistema.')
                ->relationship('roles', 'name', fn(Builder $query) => $userService->opcoesDeRoles($query, $user))
                ->preload()
                ->required(),

            Toggle::make('email_approved')
                ->label('Verificação de acesso')
                ->helperText('Ative para permitir o acesso ao sistema.')
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->default(true)
                ->visible(fn(?User $record, string $context) => $userService->podeVerToggleAprovacaoEmail($user, $record, $context)),

            Toggle::make('usar_permissoes_extras')
                ->label('Permissões adicionais')
                ->helperText('Ative para conceder permissões específicas além do nível de acesso.')
                ->default(fn(?User $record) => $record?->getDirectPermissions()->isNotEmpty())
                ->onColor('warning')
                ->offColor('info')
                ->onIcon('heroicon-s-lock-open')
                ->offIcon('heroicon-s-lock-closed')
                ->disabled(fn() => ! $roleService->ehSuperAdmin($user))
                ->live(),

            Components\Section::make('Permissões específicas')
                ->collapsible()
                ->columnSpanFull()
                ->description('Permissões herdadas do nível de acesso já vêm marcadas.')
                ->visible(fn(Get $get) => $get('usar_permissoes_extras') === true)
                ->schema(function (?User $record) use ($roleService, $user) {
                    if (! $user || ! $roleService->ehSuperAdmin($user)) return [];

                    $todasPermissoes = Permission::orderBy('name')->get();

                    $permissoesDaRole = $record?->roles
                        ->flatMap(fn($role) => $role->permissions)
                        ->pluck('name')
                        ->toArray() ?? [];

                    $permissoesDiretas = $record?->getDirectPermissions()->pluck('name')->toArray() ?? [];

                    return $todasPermissoes
                        ->groupBy(fn($perm) => explode(' ', $perm->name)[0])
                        ->map(function ($permissoes, $grupo) use ($permissoesDaRole, $permissoesDiretas) {
                            $permissoesDoGrupoNaRole = collect($permissoes)
                                ->filter(fn($p) => in_array($p->name, $permissoesDaRole))
                                ->pluck('name')
                                ->toArray();

                            return CheckboxList::make("permissions_{$grupo}")
                                ->label($grupo)
                                ->options($permissoes->pluck('name', 'name')->toArray())
                                ->columns(3)
                                ->helperText(! empty($permissoesDoGrupoNaRole) ? '🔒 Herança da role: ' . implode(', ', $permissoesDoGrupoNaRole) : '')
                                ->afterStateHydrated(function (callable $set) use ($grupo, $permissoes, $permissoesDaRole, $permissoesDiretas) {
                                    $set("permissions_{$grupo}", collect($permissoesDaRole)
                                        ->merge($permissoesDiretas)
                                        ->intersect($permissoes->pluck('name'))
                                        ->values()
                                        ->toArray());
                                })
                                ->dehydrated(true);
                        })
                        ->values()
                        ->toArray();
                }),
        ]);
    }
}