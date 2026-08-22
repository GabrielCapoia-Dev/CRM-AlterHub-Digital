<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enum\RolesEnum;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use App\Models\Clientes\Cliente;
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
                ->live()
                ->disabled(fn (?User $record, string $context) => $userService->desabilitarCampoRole($user, $record, $context)),

            Components\Section::make('Carteira comercial')
                ->icon('heroicon-o-user-group')
                ->iconColor('primary')
                ->description('Selecione os clientes que ficarão sob responsabilidade deste vendedor.')
                ->visible(fn (Get $get, ?User $record): bool => static::roleSelecionadaEhVendedor(
                    $get('role'),
                    $record,
                ))
                ->compact()
                ->extraAttributes(['class' => 'seller-portfolio-section'])
                ->schema([
                    CheckboxList::make('cliente_ids')
                        ->label('Clientes vinculados')
                        ->hint(function (mixed $state): string {
                            $count = count((array) $state);

                            return $count === 1
                                ? '1 cliente selecionado'
                                : "{$count} clientes selecionados";
                        })
                        ->options(fn (?User $record): array => static::carteiraDeClientes($record)['options'])
                        ->descriptions(fn (?User $record): array => static::carteiraDeClientes($record)['descriptions'])
                        ->searchable()
                        ->searchDebounce(200)
                        ->searchPrompt('Buscar por cliente, código, CNPJ ou vendedor atual...')
                        ->noSearchResultsMessage('Nenhum cliente corresponde à busca.')
                        ->columns([
                            'default' => 1,
                            'xl' => 2,
                        ])
                        ->default(fn (?User $record): array => $record?->clientes()
                            ->orderBy('razao_social')
                            ->pluck('clientes.id')
                            ->all() ?? [])
                        ->afterStateHydrated(function (CheckboxList $component, ?User $record): void {
                            if (! $record) {
                                return;
                            }

                            $component->state(
                                $record->clientes()
                                    ->orderBy('razao_social')
                                    ->pluck('clientes.id')
                                    ->all(),
                            );
                        })
                        ->helperText('Ao salvar, a seleção substituirá a carteira atual. O histórico das vendas já registradas continuará preservado.')
                        ->extraAttributes(['class' => 'seller-portfolio-grid'])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),

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
                    if (! $roleService->ehSuperAdmin($user)) {
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
                        CheckboxList::make('permissions')
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

    protected static function roleSelecionadaEhVendedor(mixed $state, ?User $record): bool
    {
        $ids = collect(is_array($state) ? $state : [$state])
            ->filter(fn (mixed $id): bool => filled($id) && is_numeric($id))
            ->map(fn (mixed $id): int => (int) $id);

        if ($ids->isNotEmpty()) {
            return Role::query()
                ->whereKey($ids->all())
                ->where('name', RolesEnum::Vendedor->value)
                ->exists();
        }

        return $record?->hasRole(RolesEnum::Vendedor->value) ?? false;
    }

    /**
     * @return array{options: array<int, string>, descriptions: array<int, string>}
     */
    protected static function carteiraDeClientes(?User $record): array
    {
        $clientes = Cliente::query()
            ->select([
                'id',
                'codigo_interno',
                'razao_social',
                'cnpj',
                'vendedor_id',
            ])
            ->with('vendedor:id,name')
            ->orderBy('razao_social')
            ->get();

        $options = [];
        $descriptions = [];

        foreach ($clientes as $cliente) {
            $codigo = filled($cliente->codigo_interno)
                ? $cliente->codigo_interno
                : 'Sem código';
            $documento = filled($cliente->cnpj)
                ? "CNPJ {$cliente->cnpj} · "
                : '';
            $vendedor = $cliente->vendedor;

            $options[$cliente->id] = "{$cliente->razao_social} · {$codigo}";

            if (! $vendedor instanceof User) {
                $descriptions[$cliente->id] = $documento.'Disponível para vínculo';

                continue;
            }

            if ($record && ((int) $vendedor->id === (int) $record->id)) {
                $descriptions[$cliente->id] = $documento.'Já faz parte desta carteira';

                continue;
            }

            $descriptions[$cliente->id] = $documento
                ."Atualmente com {$vendedor->name} · será transferido ao salvar";
        }

        return [
            'options' => $options,
            'descriptions' => $descriptions,
        ];
    }
}
