<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Acesso\User;
use App\Services\Acesso\PasswordResetService;
use App\Services\Acesso\RoleService;
use App\Services\Acesso\UserService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var User */
        $user = Auth::user();
        $userService = app(UserService::class);
        $roleService = app(RoleService::class);
        $passwordResetService = app(PasswordResetService::class);

        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->checkIfRecordIsSelectableUsing(fn (User $record) => $userService->podeSelecionarRegistro($user, $record))
            ->columns(self::columns($userService))
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--access')
            ->recordActions(self::recordActions($userService, $roleService, $passwordResetService, $user), position: RecordActionsPosition::AfterContent)
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
                    ->getStateUsing(fn (User $record): string => $record->getRoleNames()->first() ?? '-')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'crm-list-top']),

            Grid::make([
                'default' => 1,
                'sm' => 2,
                'xl' => 5,
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

                    TextColumn::make('must_change_password')
                        ->label('Senha')
                        ->description('Situação da senha', position: 'above')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Troca pendente' : 'Atualizada')
                        ->color(fn (bool $state): string => $state ? 'warning' : 'success')
                        ->icon(fn (bool $state): string => $state ? 'heroicon-o-key' : 'heroicon-o-check-circle')
                        ->grow(false)
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

    private static function recordActions(
        UserService $userService,
        RoleService $roleService,
        PasswordResetService $passwordResetService,
        User $user,
    ): array {
        return [
            ActionGroup::make([
                EditAction::make(),

                Action::make('resetar_senha')
                    ->label('Redefinir senha')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Definir senha temporária')
                    ->modalDescription('Escolha a senha que o usuário usará no próximo acesso. Depois de entrar, ele deverá criar uma nova senha pessoal. Registros e permissões não serão alterados.')
                    ->modalSubmitActionLabel('Redefinir senha')
                    ->schema([
                        TextInput::make('temporary_password')
                            ->label('Senha temporária')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->default(PasswordResetService::DEFAULT_TEMPORARY_PASSWORD)
                            ->required()
                            ->minLength(PasswordResetService::MIN_PASSWORD_LENGTH)
                            ->maxLength(PasswordResetService::MAX_PASSWORD_LENGTH)
                            ->rules([
                                PasswordRule::min(PasswordResetService::MIN_PASSWORD_LENGTH)
                                    ->mixedCase()
                                    ->numbers()
                                    ->symbols(),
                            ])
                            ->helperText('Mín. 8 e máx. 30 caracteres, com maiúscula, minúscula, número e símbolo.')
                            ->validationMessages([
                                'required' => 'Informe a senha temporária.',
                                'min' => 'A senha temporária deve ter pelo menos 8 caracteres.',
                                'max' => 'A senha temporária deve ter no máximo 30 caracteres.',
                            ]),
                    ])
                    ->visible(fn (User $record): bool => $passwordResetService->canReset($user, $record))
                    ->action(function (array $data, User $record) use ($passwordResetService, $user): void {
                        try {
                            $passwordResetService->resetWithTemporaryPassword(
                                $user,
                                $record,
                                $data['temporary_password'],
                            );
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Não foi possível redefinir a senha')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->send();

                            return;
                        } catch (AuthorizationException) {
                            Notification::make()
                                ->danger()
                                ->title('Ação não autorizada')
                                ->body('Você não pode redefinir a senha deste usuário.')
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Senha redefinida')
                            ->body('Informe ao usuário a senha temporária que você definiu.')
                            ->send();
                    }),

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
