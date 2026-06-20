<?php

namespace App\Filament\Resources\Oportunidades;

use App\Filament\Support\Fields\TaxIdentifierField;
use App\Filament\Resources\Oportunidades\Pages\CreateOportunidade;
use App\Filament\Resources\Oportunidades\Pages\EditOportunidade;
use App\Filament\Resources\Oportunidades\Pages\KanbanOportunidades;
use App\Filament\Resources\Oportunidades\Pages\ListOportunidades;
use App\Filament\Resources\Oportunidades\Pages\ViewOportunidade;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeInteracoesRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeMovimentacoesRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeProdutosRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeTarefasRelationManager;
use App\Models\Categorias\CategoriaSegmento;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use App\Models\Status\StatusCliente;
use App\Services\CRM\OportunidadeClienteService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OportunidadeResource extends Resource
{
    protected static ?string $model = Oportunidade::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'CRM Kanban';

    protected static ?string $modelLabel = 'CRM Kanban';

    protected static ?string $pluralModelLabel = 'CRM Kanban';

    public static ?string $slug = 'crm-kanban';

    protected static string|UnitEnum|null $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Negociacao')
                    ->description('Dados principais da oportunidade comercial.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Titulo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: Proposta reagentes linha hospitalar'),

                        static::makeClientSection(),

                        Select::make('etapa_id')
                            ->label('Etapa')
                            ->relationship('etapa', 'nome')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        Select::make('user_id')
                            ->label('Responsavel')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('temperatura')
                            ->label('Temperatura')
                            ->options(Oportunidade::temperaturaOptions())
                            ->required(),

                        TextInput::make('valor_estimado')
                            ->label('Valor estimado')
                            ->numeric()
                            ->rule('decimal:0,2')
                            ->formatStateUsing(fn ($state): ?string => NumericFormat::input($state))
                            ->prefix('R$')
                            ->minValue(0)
                            ->placeholder('0,00'),
                    ]),

                Section::make('Contexto')
                    ->description('Notas adicionais e dados de fechamento da negociacao.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('motivo_fechamento')
                            ->label('Motivo de fechamento')
                            ->rows(4)
                            ->visible(fn (Get $get): bool => static::etapaEhFechamento($get('etapa_id')))
                            ->required(fn (Get $get): bool => static::etapaEhFechamento($get('etapa_id')))
                            ->helperText('Obrigatorio quando a etapa selecionada for de encerramento.'),

                        Textarea::make('notas')
                            ->label('Notas')
                            ->rows(5)
                            ->columnSpanFull()
                            ->placeholder('Resumo do contexto comercial da oportunidade.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['cliente.categoriaSegmento', 'etapa', 'user']))
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('titulo')
                            ->label('Oportunidade')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold')
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('cliente.razao_social')
                            ->label('Cliente')
                            ->searchable()
                            ->sortable()
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ]),

                    TextColumn::make('valor_estimado')
                        ->label('Valor estimado')
                        ->description('Valor estimado', position: 'above')
                        ->money('BRL')
                        ->sortable()
                        ->placeholder('Sem valor')
                        ->alignEnd()
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-money'], merge: true),
                ])
                    ->from('md')
                    ->extraAttributes(['class' => 'crm-list-top']),

                Grid::make([
                    'default' => 1,
                    'sm' => 2,
                    'xl' => 4,
                ])
                    ->schema([
                        TextColumn::make('etapa.nome')
                            ->label('Etapa')
                            ->description('Etapa', position: 'above')
                            ->badge()
                            ->sortable()
                            ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),

                        TextColumn::make('temperatura')
                            ->label('Temperatura')
                            ->description('Temperatura', position: 'above')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Oportunidade::temperaturaOptions()[$state] ?? $state)
                            ->color(fn (string $state): string => match ($state) {
                                'hot' => 'danger',
                                'warm' => 'warning',
                                default => 'info',
                            })
                            ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),

                        TextColumn::make('cliente.categoriaSegmento.nome')
                            ->label('Segmento')
                            ->description('Segmento', position: 'above')
                            ->badge()
                            ->placeholder('Sem segmento')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('user.name')
                            ->label('Responsavel')
                            ->description('Responsavel', position: 'above')
                            ->searchable()
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->description('Atualizado em', position: 'above')
                    ->dateTime('d/m/Y H:i')
                    ->extraAttributes(['class' => 'crm-list-field crm-list-footer'], merge: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->searchPlaceholder('Buscar por oportunidade, cliente ou responsavel...')
            ->filters([
                SelectFilter::make('etapa_id')
                    ->label('Etapa')
                    ->relationship('etapa', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user_id')
                    ->label('Responsavel')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('temperatura')
                    ->label('Temperatura')
                    ->options(Oportunidade::temperaturaOptions()),
            ])
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm')
            ->recordActions([
                ViewAction::make()->label('Visualizar'),
                static::configureEditAction(EditAction::make()->label('Editar')),
                DeleteAction::make()->label('Excluir'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getRelations(): array
    {
        return [
            OportunidadeProdutosRelationManager::class,
            OportunidadeInteracoesRelationManager::class,
            OportunidadeTarefasRelationManager::class,
            OportunidadeMovimentacoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => KanbanOportunidades::route('/'),
            'list' => ListOportunidades::route('/lista'),
            'create' => CreateOportunidade::route('/create'),
            'view' => ViewOportunidade::route('/{record}'),
            'edit' => EditOportunidade::route('/{record}/edit'),
        ];
    }

    public static function configureCreateAction(CreateAction $action): CreateAction
    {
        return $action
            ->mutateDataUsing(fn (array $data): array => static::prepareOpportunityDataForPersistence($data));
    }

    public static function configureEditAction(EditAction $action): EditAction
    {
        return $action
            ->mutateRecordDataUsing(fn (array $data, Model $record): array => static::prepareOpportunityDataForFill($data, $record))
            ->mutateDataUsing(fn (array $data): array => static::prepareOpportunityDataForPersistence($data));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareOpportunityDataForPersistence(array $data): array
    {
        return app(OportunidadeClienteService::class)->prepareOpportunityData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareOpportunityDataForFill(array $data, ?Model $record = null): array
    {
        $cliente = null;

        if ($record instanceof Oportunidade) {
            $cliente = $record->cliente;
        } elseif (filled($data['cliente_id'] ?? null)) {
            $cliente = Cliente::query()->find($data['cliente_id']);
        }

        return [
            ...$data,
            ...app(OportunidadeClienteService::class)->existingClientFormState($cliente),
        ];
    }

    protected static function makeClientSection(): Section
    {
        return Section::make('Cliente')
            ->description('Busque um cliente ja cadastrado ou registre um novo sem sair da oportunidade.')
            ->icon(Heroicon::OutlinedBuildingOffice)
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Hidden::make('cliente_id')
                    ->default(null),

                Hidden::make('client_mode')
                    ->default(OportunidadeClienteService::MODE_EXISTING),

                Hidden::make('client_lookup_status')
                    ->default(''),

                Hidden::make('client_lookup_message')
                    ->default(''),

                TextInput::make('client_lookup')
                    ->label('Buscar cliente por codigo ou documento fiscal')
                    ->placeholder('Ex.: CLI-2026-014 ou VAT/Tax ID')
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('client_mode') !== OportunidadeClienteService::MODE_NEW)
                    ->live(onBlur: true)
                    ->required(fn (Get $get): bool => $get('client_mode') !== OportunidadeClienteService::MODE_NEW)
                    ->helperText('Informe o codigo interno ou o documento fiscal para localizar um cliente existente.')
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        if ($get('client_mode') === OportunidadeClienteService::MODE_NEW) {
                            return;
                        }

                        $set('cliente_id', null);

                        if (blank($state)) {
                            $set('client_lookup_status', '');
                            $set('client_lookup_message', '');

                            return;
                        }

                        $set('client_lookup_status', '');
                        $set('client_lookup_message', 'Clique em Buscar cliente para localizar um cadastro existente.');
                    }),

                Actions::make([
                    Action::make('buscar_cliente')
                        ->label('Buscar cliente')
                        ->icon(Heroicon::OutlinedMagnifyingGlass)
                        ->color('primary')
                        ->visible(fn (Get $get): bool => $get('client_mode') !== OportunidadeClienteService::MODE_NEW)
                        ->action(function (Get $get, Set $set): void {
                            $cliente = app(OportunidadeClienteService::class)->findByLookup($get('client_lookup'));

                            if (! $cliente) {
                                $set('cliente_id', null);
                                $set('client_lookup_status', 'missing');
                                $set('client_lookup_message', 'Nenhum cliente foi encontrado. Use Novo Cliente para cadastrar no mesmo fluxo.');

                                Notification::make()
                                    ->title('Cliente nao encontrado')
                                    ->body('Voce pode seguir com Novo Cliente e concluir a oportunidade no mesmo cadastro.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            static::applyClientState($set, app(OportunidadeClienteService::class)->existingClientFormState($cliente));

                            Notification::make()
                                ->title('Cliente encontrado')
                                ->body("{$cliente->razao_social} foi vinculado a oportunidade.")
                                ->success()
                                ->send();
                        }),
                    Action::make('novo_cliente')
                        ->label(fn (Get $get): string => $get('client_mode') === OportunidadeClienteService::MODE_NEW ? 'Usar cliente existente' : 'Novo Cliente')
                        ->icon(fn (Get $get): Heroicon => $get('client_mode') === OportunidadeClienteService::MODE_NEW
                            ? Heroicon::OutlinedArrowUturnLeft
                            : Heroicon::OutlinedPlus)
                        ->color('gray')
                        ->action(function (Get $get, Set $set): void {
                            if ($get('client_mode') === OportunidadeClienteService::MODE_NEW) {
                                static::applyClientState($set, app(OportunidadeClienteService::class)->blankFormState());

                                return;
                            }

                            static::applyClientState($set, app(OportunidadeClienteService::class)->newClientFormState());
                        }),
                ])
                    ->columnSpanFull(),

                Placeholder::make('client_feedback')
                    ->label('Vinculo do cliente')
                    ->columnSpanFull()
                    ->content(function (Get $get): string {
                        if ($get('cliente_id')) {
                            $cliente = Cliente::query()
                                ->with(['categoriaSegmento', 'statusCliente'])
                                ->find($get('cliente_id'));

                            if ($cliente) {
                                return collect([
                                    $cliente->razao_social,
                                    $cliente->codigo_interno ? "Codigo {$cliente->codigo_interno}" : null,
                                    $cliente->cnpj ? "Doc. fiscal {$cliente->cnpj}" : null,
                                    $cliente->categoriaSegmento?->nome ? "Segmento {$cliente->categoriaSegmento->nome}" : null,
                                    $cliente->statusCliente?->nome ? "Status {$cliente->statusCliente->nome}" : null,
                                ])
                                    ->filter()
                                    ->implode(' | ');
                            }
                        }

                        return (string) ($get('client_lookup_message') ?: 'Busque um cliente existente ou clique em Novo Cliente para preencher manualmente.');
                    }),

                TextInput::make('client_razao_social')
                    ->label('Razao social')
                    ->required(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW)
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_nome_fantasia')
                    ->label('Nome fantasia')
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TaxIdentifierField::make('client_cnpj', 'CNPJ / identificacao fiscal')
                    ->required(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                Select::make('client_segmento_id')
                    ->label('Segmento')
                    ->options(fn (): array => CategoriaSegmento::query()->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                Select::make('client_status_id')
                    ->label('Status do cliente')
                    ->options(fn (): array => StatusCliente::query()->orderBy('nome')->pluck('nome', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->required(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_nome_completo')
                    ->label('Contato principal')
                    ->required(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW)
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_cargo')
                    ->label('Cargo')
                    ->maxLength(100)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_telefone')
                    ->label('Telefone / WhatsApp')
                    ->mask('(99) 99999-9999')
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                TextInput::make('client_cidade')
                    ->label('Cidade')
                    ->maxLength(100)
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                Select::make('client_uf')
                    ->label('UF')
                    ->options(static::ufOptions())
                    ->searchable()
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),

                Textarea::make('client_observacao')
                    ->label('Observacoes do cliente')
                    ->rows(4)
                    ->columnSpanFull()
                    ->visible(fn (Get $get): bool => $get('client_mode') === OportunidadeClienteService::MODE_NEW),
            ]);
    }

    protected static function applyClientState(Set $set, array $state): void
    {
        foreach ($state as $field => $value) {
            $set($field, $value);
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function ufOptions(): array
    {
        return [
            'AC' => 'AC',
            'AL' => 'AL',
            'AM' => 'AM',
            'AP' => 'AP',
            'BA' => 'BA',
            'CE' => 'CE',
            'DF' => 'DF',
            'ES' => 'ES',
            'GO' => 'GO',
            'MA' => 'MA',
            'MG' => 'MG',
            'MS' => 'MS',
            'MT' => 'MT',
            'PA' => 'PA',
            'PB' => 'PB',
            'PE' => 'PE',
            'PI' => 'PI',
            'PR' => 'PR',
            'RJ' => 'RJ',
            'RN' => 'RN',
            'RO' => 'RO',
            'RR' => 'RR',
            'RS' => 'RS',
            'SC' => 'SC',
            'SE' => 'SE',
            'SP' => 'SP',
            'TO' => 'TO',
        ];
    }

    protected static function etapaEhFechamento(mixed $etapaId): bool
    {
        if (! $etapaId) {
            return false;
        }

        return (bool) Etapa::query()
            ->whereKey($etapaId)
            ->value('fechamento');
    }
}
