<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Acesso\User;
use App\RolesEnum;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Actions\Action;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var \App\Models\Acesso\User */
        $user = Auth::user();
        $userService = app(UserService::class);
        $roleService = app(RoleService::class);

        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->checkIfRecordIsSelectableUsing(fn(User $record) => $userService->podeSelecionarRegistro($user, $record))
            ->columns(self::columns($userService, $user))
            ->recordActions(self::recordActions($userService, $roleService, $user))
            ->groupedBulkActions(self::bulkActions($userService, $roleService, $user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private static function columns(UserService $userService, User $user): array
    {
        return [
            \Filament\Tables\Columns\TextColumn::make('name')
                ->label('Nome de usuário')
                ->wrap()
                ->sortable()
                ->grow(false)
                ->searchable(),

            \Filament\Tables\Columns\TextColumn::make('email')
                ->label('E-mail')
                ->wrap()
                ->copyable()
                ->alignCenter()
                ->grow(false)
                ->searchable(),

            \Filament\Tables\Columns\ToggleColumn::make('email_approved')
                ->label('Verificação')
                ->sortable()
                ->alignCenter()
                ->grow(false)
                ->disabled(fn(User $record) => $userService->desabilitarToggleAprovacaoEmail(Auth::user(), $record))
                ->visible(fn() => $userService->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->columnSpan(1),

            \Filament\Tables\Columns\TextColumn::make('email_verified_at')
                ->label('Verificado em')
                ->grow(false)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(function ($state, User $record) {
                    if (! $record->email_approved) return '--/--/-- --:--:--';
                    return $state ? $state->format('d/m/Y H:i:s') : '-';
                }),

            \Filament\Tables\Columns\TextColumn::make('role')
                ->label('Nivel de acesso')
                ->alignCenter()
                ->grow(false)
                ->getStateUsing(fn(User $record) => $record->roles->first()?->name ?? '-')
                ->toggleable(isToggledHiddenByDefault: false),

            \Filament\Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            \Filament\Tables\Columns\TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    private static function recordActions(UserService $userService, RoleService $roleService, User $user): array
    {
        return [
            Action::make('permissoes')
                ->label('Permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->slideOver()
                ->modalSubmitActionLabel('Salvar')
                ->modalSubmitAction(fn(Action $action) => $action->color('primary'))
                ->visible(function (User $record) use ($userService, $roleService, $user) {
                    if ($record->id === $user->id) return false;
                    if ($roleService->ehAdmin($record)) return false;
                    return $user->hasPermissionTo('Aplicar Permissoes');
                })
                ->modalHeading(fn(User $record) => 'Permissões do usuário')
                ->modalDescription(fn(User $record) => "{$record->name} • {$record->email}")
                ->modalIcon('heroicon-o-key')
                ->schema(function (User $record) use ($userService, $user) {
                    return [
                        TextInput::make('buscar_permissao')
                            ->label('Pesquisar permissão')
                            ->placeholder('Ex: listar, editar, excluir...')
                            ->live(debounce: 30)
                            ->extraInputAttributes([
                                'onkeydown' => 'if(event.key === "Enter" || event.keyCode === 13) event.preventDefault()',
                            ])
                            ->dehydrated(false),

                        Components\Group::make()
                            ->schema(fn(Get $get) => $userService->checkboxesPermissoesComEstado($record, $get, $user)),
                    ];
                })
                ->action(function (User $record, array $data) {
                    $permissoesSelecionadas = collect($data)
                        ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
                        ->flatten()
                        ->unique()
                        ->values();

                    $permissoesAtuais = $record->getDirectPermissions()->pluck('name');

                    $permissoesDaRole = $record->roles
                        ->flatMap(fn($role) => $role->permissions)
                        ->pluck('name')
                        ->toArray();

                    $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);
                    $paraAdicionar = $permissoesSelecionadas->diff($permissoesAtuais)->diff($permissoesDaRole);

                    if ($paraRemover->isNotEmpty()) {
                        $record->revokePermissionTo($paraRemover->toArray());
                        Notification::make()->title('Permissões removidas')->body($paraRemover->map(fn($p) => "• {$p}")->implode('<br>'))->danger()->icon('heroicon-s-x-mark')->send();
                    }

                    if ($paraAdicionar->isNotEmpty()) {
                        $record->givePermissionTo($paraAdicionar->toArray());
                        Notification::make()->title('Permissões adicionadas')->body($paraAdicionar->map(fn($p) => "• {$p}")->implode('<br>'))->success()->icon('heroicon-s-check')->send();
                    }

                    if ($paraRemover->isEmpty() && $paraAdicionar->isEmpty()) {
                        Notification::make()->title('Nenhuma alteração foi realizada')->info()->send();
                    }
                }),

            EditAction::make(),

            \Filament\Actions\DeleteAction::make()
                ->before(function (User $record, \Filament\Actions\DeleteAction $action) use ($userService, $user) {
                    if (! $userService->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->disabled(fn(User $record) => ($record->id === 1) || (Auth::id() === $record->id))
                ->visible(fn() => $roleService->ehSuperAdmin(Auth::user())),
        ];
    }

    private static function bulkActions(UserService $userService, RoleService $roleService, User $user): array
    {
        return [
            Action::make('permissoes_em_massa')
                ->label('Editar permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->slideOver()
                ->visible(fn() => $user->hasPermissionTo('Aplicar Permissoes'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn(Action $action) => $action->label('Fechar'))
                ->modalHeading('Editar permissões em massa')
                ->modalDescription('As permissões selecionadas serão aplicadas aos usuários escolhidos.')
                ->modalIcon('heroicon-o-key')
                ->schema(fn() => [
                    Toggle::make('substituir')
                        ->label('Substituir permissões existentes')
                        ->visible(false)
                        ->default(true),

                    Components\Section::make('Permissões')
                        ->collapsible()
                        ->schema(fn(Get $get) => [
                            TextInput::make('buscar_permissao')
                                ->label('Pesquisar permissão')
                                ->placeholder('Ex: listar, editar, excluir...')
                                ->live(debounce: 100)
                                ->extraInputAttributes([
                                    'onkeydown' => 'if(event.key === "Enter" || event.keyCode === 13) event.preventDefault()',
                                ])
                                ->dehydrated(false),

                            ...$userService->checkboxesPermissoesEmMassa($get, $user),
                        ]),
                ])
                ->action(function ($records, array $data) use ($roleService) {
                    $permissoesSelecionadas = collect($data)
                        ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
                        ->flatten()
                        ->unique()
                        ->values()
                        ->toArray();

                    if (empty($permissoesSelecionadas)) return;

                    foreach ($records as $record) {
                        if ($roleService->ehAdmin($record)) continue;
                        $record->givePermissionTo($permissoesSelecionadas);
                    }
                }),

            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($userService, $user) {
                    if (! $userService->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $roleService->ehSuperAdmin(Auth::user())),
        ];
    }
}