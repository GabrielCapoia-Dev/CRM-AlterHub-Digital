<?php

namespace App\Filament\Resources\Roles;

use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Support\Icons\Heroicon;
use App\Models\Acesso\Role;
use Filament\Forms;
use Filament\Resources\Resource;
use App\Services\Acesso\RoleService;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\Roles\Pages\ManageRoles;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('name')
                    ->label('Nivel de acesso')
                    ->required()
                    ->disabled(fn($record, $context) => app(RoleService::class)->roleEhBloqueadaParaEdicao($record, $context))
                    ->unique(ignoreRecord: true),

                Section::make('Permissões')
                    ->description('Selecione as permissões para este nível de acesso.')
                    ->collapsible()
                    ->schema([
                        Components\TextInput::make('search_permissions')
                            ->label('Buscar permissões')
                            ->placeholder('Digite para filtrar as permissões...')
                            ->live(debounce: 300)
                            ->dehydrated(false),

                        Group::make()
                            ->schema(function (?Role $record, Get $get) {
                                $todasPermissoes = Permission::orderBy('name')->get();
                                $busca = $get('search_permissions');

                                if ($busca) {
                                    $todasPermissoes = $todasPermissoes->filter(
                                        fn($perm) => str_contains(strtolower($perm->name), strtolower($busca))
                                    );
                                }

                                $permissoesAtribuidas = $record?->permissions->pluck('name')->toArray() ?? [];

                                $agrupadas = $todasPermissoes->groupBy(fn($perm) => explode(' ', $perm->name)[0]);

                                $schema = [];

                                foreach ($agrupadas as $grupo => $permissoes) {
                                    $schema[] = Components\CheckboxList::make("permissions_{$grupo}")
                                        ->label($grupo)
                                        ->options($permissoes->pluck('name', 'name')->toArray())
                                        ->columns(3)
                                        ->afterStateHydrated(function (callable $set) use ($grupo, $permissoes, $permissoesAtribuidas) {
                                            $set("permissions_{$grupo}", collect($permissoesAtribuidas)
                                                ->intersect($permissoes->pluck('name'))
                                                ->values()
                                                ->toArray());
                                        })
                                        ->dehydrated(true);
                                }

                                return $schema;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nivel de acesso')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->slideOver()
                    ->visible(function () {
                        /** @var \App\Models\Acesso\User */
                        $user = Auth::user();
                        return $user->hasPermissionTo('Aplicar Permissoes') && $user->hasPermissionTo('Editar Níveis de Acesso');
                    })
                    ->disabled(fn($record) => app(RoleService::class)->roleEhBloqueadaParaEdicao($record, 'edit'))
                    ->modalHeading(fn($record) => 'Editar nível de acesso')
                    ->modalDescription(fn($record) => $record->name)
                    ->schema(function (Role $record) {
                        return [
                            Forms\Components\TextInput::make('name')
                                ->label('Nível de acesso')
                                ->required()
                                ->disabled(fn($record) => app(RoleService::class)->roleEhBloqueadaParaEdicao($record, 'edit'))
                                ->default($record->name),

                            Forms\Components\TextInput::make('search_permissions')
                                ->label('Buscar permissões')
                                ->live(debounce: 30)
                                ->dehydrated(false)
                                ->extraInputAttributes([
                                    'onkeydown' => 'if(event.key === "Enter") event.preventDefault()',
                                ]),

                            Forms\Components\Hidden::make('permissions_state')
                                ->default(fn(Role $record) => $record->permissions->pluck('name')->toArray())
                                ->dehydrated(true),

                            Group::make()
                                ->schema(function (Get $get) use ($record) {
                                    $todasPermissoes = Permission::orderBy('name')->get();
                                    $busca = strtolower($get('search_permissions') ?? '');

                                    $porGrupo = $todasPermissoes->groupBy(fn($p) => explode(' ', $p->name)[0]);

                                    $schema = [];

                                    foreach ($porGrupo as $grupo => $permissoes) {
                                        $filtradas = $permissoes->when(
                                            $busca,
                                            fn($collection) => $collection->filter(
                                                fn($perm) => str_contains(strtolower($perm->name), $busca)
                                            )
                                        );

                                        if ($filtradas->isEmpty()) continue;

                                        $schema[] = Forms\Components\CheckboxList::make("permissions_{$grupo}")
                                            ->label($grupo)
                                            ->options($filtradas->pluck('name', 'name')->toArray())
                                            ->columns(3)
                                            ->default(
                                                fn(Get $get) => collect($get('permissions_state') ?? [])
                                                    ->intersect($permissoes->pluck('name'))
                                                    ->values()
                                                    ->toArray()
                                            )
                                            ->afterStateUpdated(function ($state, Get $get, callable $set) use ($permissoes) {
                                                $atual = collect($get('permissions_state') ?? []);
                                                $atual = $atual->diff($permissoes->pluck('name'));
                                                $atual = $atual->merge($state ?? []);
                                                $set('permissions_state', $atual->unique()->values()->toArray());
                                            });
                                    }

                                    return $schema;
                                }),
                        ];
                    })
                    ->action(function (Role $record, array $data) {
                        $novoNome = $data['name'] ?? $record->name;
                        if ($record->name !== $novoNome) {
                            $record->update(['name' => $novoNome]);
                        }

                        $permissoesSelecionadas = collect($data)
                            ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
                            ->flatten()
                            ->unique()
                            ->values();

                        $permissoesAtuais = $record->permissions()->pluck('name');
                        $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);
                        $paraAdicionar = $permissoesSelecionadas->diff($permissoesAtuais);

                        if ($paraRemover->isNotEmpty()) {
                            $record->revokePermissionTo($paraRemover->toArray());
                            \Filament\Notifications\Notification::make()->title('Permissões removidas')->body($paraRemover->implode(', '))->danger()->icon('heroicon-s-x-mark')->send();
                        }

                        if ($paraAdicionar->isNotEmpty()) {
                            $record->givePermissionTo($paraAdicionar->toArray());
                            \Filament\Notifications\Notification::make()->title('Permissões adicionadas')->body($paraAdicionar->implode(', '))->success()->icon('heroicon-s-check')->send();
                        }

                        if ($paraRemover->isEmpty() && $paraAdicionar->isEmpty()) {
                            \Filament\Notifications\Notification::make()->title('Nenhuma alteração foi realizada')->info()->send();
                        }
                    }),

                Actions\DeleteAction::make()
                    ->disabled(fn($record) => app(RoleService::class)->roleEhBloqueadaParaExclusao($record)),
            ])
            ->groupedBulkActions([
                Actions\DeleteBulkAction::make()
                    ->visible(function () {
                        /** @var \App\Models\Acesso\User */
                        $user = Auth::user();
                        return app(RoleService::class)->ehSuperAdmin($user);
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn($record) => app(RoleService::class)->roleEhSelecionavelEmMassa($record));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}