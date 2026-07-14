<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var User */
        $user = Auth::user();
        $userService = app(UserService::class);
        $roleService = app(RoleService::class);

        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->checkIfRecordIsSelectableUsing(fn (User $record) => $userService->podeSelecionarRegistro($user, $record))
            ->columns(self::columns($userService))
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--access')
            ->recordActions(self::recordActions($userService, $roleService, $user), position: RecordActionsPosition::AfterContent)
            ->toolbarActions(self::bulkActions($userService, $roleService, $user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private static function columns(UserService $userService): array
    {
        return self::responsiveColumns($userService);
    }

    private static function responsiveColumns(UserService $userService): array
    {
        return [
            Split::make([
                Stack::make([
                    TextColumn::make('name')
                        ->label('Nome de usuário')
                        ->wrap()
                        ->sortable()
                        ->searchable()
                        ->weight('semibold')
                        ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                    TextColumn::make('email')
                        ->label('E-mail')
                        ->wrap()
                        ->copyable()
                        ->searchable()
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                ]),

                TextColumn::make('role')
                    ->label('Nível de acesso')
                    ->description('Nível de acesso', position: 'above')
                    ->alignCenter()
                    ->grow(false)
                    ->getStateUsing(fn (User $record) => $record->roles->first()?->name ?? '-')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'crm-list-top']),

            Grid::make([
                'default' => 1,
                'sm' => 2,
                'xl' => 4,
            ])
                ->schema([
                    ToggleColumn::make('email_approved')
                        ->label('Verificação')
                        ->sortable()
                        ->alignCenter()
                        ->grow(false)
                        ->disabled(fn (User $record) => $userService->desabilitarToggleAprovacaoEmail(Auth::user(), $record))
                        ->visible(fn () => $userService->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                        ->inline(false)
                        ->onColor('success')
                        ->offColor('danger')
                        ->onIcon('heroicon-s-check')
                        ->offIcon('heroicon-s-x-mark')
                        ->columnSpan(1)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    TextColumn::make('email_verified_at')
                        ->label('Verificado em')
                        ->description('Verificado em', position: 'above')
                        ->grow(false)
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->formatStateUsing(function ($state, User $record) {
                            if (! $record->email_approved) {
                                return '--/--/-- --:--:--';
                            }

                            return $state ? $state->format('d/m/Y H:i:s') : '-';
                        })
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    TextColumn::make('created_at')
                        ->label('Criado em')
                        ->description('Criado em', position: 'above')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    TextColumn::make('updated_at')
                        ->label('Atualizado em')
                        ->description('Atualizado em', position: 'above')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                ])
                ->extraAttributes(['class' => 'crm-list-meta']),
        ];
    }

    private static function recordActions(UserService $userService, RoleService $roleService, User $user): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),

                DeleteAction::make()
                    ->before(function (User $record, DeleteAction $action) use ($userService, $user) {
                        if (! $userService->podeDeletar($user, $record)) {
                            $action->failure();
                            $action->halt();
                        }
                    })
                    ->disabled(fn (User $record) => ($record->id === 1) || (Auth::id() === $record->id))
                    ->visible(fn () => $roleService->ehSuperAdmin(Auth::user())),
            ])
                ->label('Ações')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
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
                ->visible(fn () => $roleService->ehSuperAdmin(Auth::user())),
        ];
    }
}
