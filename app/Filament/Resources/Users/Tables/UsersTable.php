<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
            ->toolbarActions(self::bulkActions($userService, $roleService, $user))
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
            DeleteBulkAction::make()
                ->label('')
                ->before(function ($records, $action) use ($userService, $user) {
                    if (! $userService->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $roleService->ehSuperAdmin(Auth::user())),
        ];
    }
}
