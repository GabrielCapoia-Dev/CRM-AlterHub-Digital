<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Acesso\Role;
use App\Models\Acesso\User;
use App\Services\Acesso\RoleService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationParentItem = 'Usuários';

    public static ?string $modelLabel = 'Nivel de acesso';

    public static ?string $label = 'Niveis de acesso';

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    public static ?string $pluralLabel = 'Niveis de acesso';

    public static ?string $navigationLabel = 'Niveis de acesso';

    public static ?string $pluralModelLabel = 'Niveis de acesso';

    public static ?string $slug = 'niveis-de-acesso';

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('name')
                    ->label('Nivel de acesso')
                    ->required()
                    ->disabled(fn ($record, $context) => $context !== 'create'
                        && $record
                        && app(RoleService::class)->nomeDaRoleEhBloqueado($record))
                    ->unique(ignoreRecord: true),

                Section::make('Permissões')
                    ->description('Selecione as permissões para este nível de acesso.')
                    ->collapsible()
                    ->schema([
                        static::permissionsField(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns([
                Split::make([
                    Stack::make([
                        Tables\Columns\TextColumn::make('name')
                            ->label('Nivel de acesso')
                            ->searchable()
                            ->weight('semibold')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),
                    ]),

                    Tables\Columns\TextColumn::make('created_at')
                        ->label('Criado em')
                        ->description('Criado em', position: 'above')
                        ->dateTime('d/m/Y H:i:s')
                        ->sortable()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),
            ])
            ->filters([])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--access')
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('editar')
                        ->label('Editar')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->slideOver()
                        ->visible(fn (Role $record): bool => Gate::allows('update', $record))
                        ->disabled(fn ($record) => app(RoleService::class)->roleEhBloqueadaParaEdicao($record, 'edit'))
                        ->modalHeading(fn ($record) => 'Editar nível de acesso')
                        ->modalDescription(fn ($record) => $record->name)
                        ->schema(function (Role $record) {
                            return [
                                Components\TextInput::make('name')
                                    ->label('Nível de acesso')
                                    ->required()
                                    ->disabled(fn (Role $record) => app(RoleService::class)->nomeDaRoleEhBloqueado($record))
                                    ->default($record->name),

                                static::permissionsField($record),
                            ];
                        })
                        ->action(function (Role $record, array $data) {
                            Gate::authorize('update', $record);
                            $novoNome = $data['name'] ?? $record->name;
                            if ($record->name !== $novoNome) {
                                $record->update(['name' => $novoNome]);
                            }

                            /** @var User $user */
                            $user = Auth::user();
                            app(RoleService::class)->sincronizarPermissoes(
                                $record,
                                $user,
                                (array) ($data['permissions'] ?? []),
                            );

                            Notification::make()
                                ->title('Nível de acesso atualizado')
                                ->body('As permissões selecionadas foram salvas.')
                                ->success()
                                ->send();
                        }),

                    Actions\DeleteAction::make()
                        ->disabled(fn ($record) => app(RoleService::class)->roleEhBloqueadaParaExclusao($record)),
                ])
                    ->label('Ações')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ], position: RecordActionsPosition::AfterContent)
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make()
                    ->visible(function () {
                        /** @var User */
                        $user = Auth::user();

                        return app(RoleService::class)->ehSuperAdmin($user);
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn ($record) => app(RoleService::class)->roleEhSelecionavelEmMassa($record));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }

    protected static function permissionsField(?Role $record = null): Components\CheckboxList
    {
        /** @var User|null $user */
        $user = Auth::user();
        $atribuiveis = app(RoleService::class)->permissoesAtribuiveis($user);

        return Components\CheckboxList::make('permissions')
            ->label('Permissões disponíveis')
            ->options($atribuiveis->mapWithKeys(fn (string $name): array => [$name => $name])->all())
            ->default(fn (): array => $record
                ? $record->permissions
                    ->pluck('name')
                    ->intersect($atribuiveis)
                    ->values()
                    ->all()
                : [])
            ->searchable()
            ->bulkToggleable()
            ->columns(3)
            ->columnSpanFull();
    }
}
