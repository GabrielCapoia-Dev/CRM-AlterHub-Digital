<?php

namespace App\Filament\Resources\Clientes;

use App\Enum\RolesEnum;
use App\Filament\Resources\Clientes\Pages\ManageClientes;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\Clientes\Cliente;
use App\Rules\UniqueNormalizedTaxIdentifierRule;
use App\Services\Acesso\RoleService;
use App\Support\Fiscal\TaxIdentifier;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static string|UnitEnum|null $navigationGroup = 'Operação';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return (new Cliente)
            ->scopeVisiveisPara($query, auth()->user())
            ->with('vendedor');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da empresa')
                    ->description('Informacoes fiscais e de identificacao do cliente.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('razao_social')
                            ->label('Razao social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: LabTech Diagnosticos Ltda.')
                            ->validationMessages([
                                'required' => 'A razao social e obrigatoria.',
                                'max' => 'A razao social nao pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('nome_fantasia')
                            ->label('Nome fantasia')
                            ->maxLength(255)
                            ->placeholder('Nome comercial (opcional)')
                            ->validationMessages([
                                'max' => 'O nome fantasia nao pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('codigo_interno')
                            ->label('Codigo interno')
                            ->maxLength(50)
                            ->placeholder('Gerado automaticamente (ex.: CLI-2024-001)')
                            ->helperText('Deixe em branco para geracao automatica.')
                            ->unique(table: 'clientes', column: 'codigo_interno', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Este codigo interno ja esta em uso.',
                            ]),

                        TaxIdentifierField::make('cnpj')
                            ->required()
                            ->rule(fn (?Cliente $record): UniqueNormalizedTaxIdentifierRule => new UniqueNormalizedTaxIdentifierRule(
                                table: 'clientes',
                                column: 'cnpj',
                                ignoreValue: $record?->getKey(),
                                ignoreColumn: $record?->getKeyName() ?? 'id',
                            ))
                            ->validationMessages([
                                'required' => 'O documento fiscal e obrigatorio.',
                            ]),

                        Select::make('id_categoria_segmento')
                            ->label('Segmento')
                            ->relationship('categoriaSegmento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o segmento do cliente.',
                            ]),

                        Select::make('id_status_cliente')
                            ->label('Status do cliente')
                            ->relationship('statusCliente', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o status do cliente.',
                            ]),

                        Select::make('vendedor_id')
                            ->label('Vendedor responsavel')
                            ->relationship(
                                'vendedor',
                                'name',
                                fn (Builder $query): Builder => $query
                                    ->whereHas(
                                        'roles',
                                        fn (Builder $roles): Builder => $roles
                                            ->where('name', RolesEnum::Vendedor->value),
                                    )
                                    ->where('email_approved', true)
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->native(false)
                            ->default(fn (): ?int => auth()->user()?->hasRole(RolesEnum::Vendedor->value)
                                ? auth()->id()
                                : null)
                            ->disabled(fn (): bool => ! app(RoleService::class)->podeEscolherVendedor(auth()->user()))
                            ->dehydrated(fn (): bool => app(RoleService::class)->podeEscolherVendedor(auth()->user()))
                            ->helperText(fn (): string => app(RoleService::class)->podeEscolherVendedor(auth()->user())
                                ? 'Opcional. A troca altera apenas o responsavel atual; vendas anteriores permanecem intactas.'
                                : 'Vendedores novos sao vinculados automaticamente ao seu usuario.'),
                    ]),

                Section::make('Contato principal')
                    ->description('Responsavel comercial ou financeiro para comunicacao.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome_completo')
                            ->label('Nome completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Responsavel comercial ou financeiro')
                            ->validationMessages([
                                'required' => 'O nome do contato e obrigatorio.',
                                'max' => 'O nome nao pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->maxLength(100)
                            ->placeholder('Ex.: Gerente de compras'),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('contato@cliente.com.br')
                            ->validationMessages([
                                'email' => 'Informe um e-mail valido.',
                                'max' => 'O e-mail nao pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('telefone')
                            ->label('Telefone / WhatsApp')
                            ->mask('(99) 99999-9999')
                            ->placeholder('(00) 00000-0000')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) {
                                        return;
                                    }

                                    $digits = preg_replace('/\D/', '', $value);

                                    if (! in_array(strlen($digits), [10, 11], true)) {
                                        $fail('Informe um telefone valido com DDD (10 ou 11 digitos).');

                                        return;
                                    }

                                    $ddd = (int) substr($digits, 0, 2);

                                    if (($ddd < 11) || ($ddd > 99)) {
                                        $fail('DDD invalido.');

                                        return;
                                    }

                                    if ((strlen($digits) === 11) && ($digits[2] !== '9')) {
                                        $fail('Numero de celular deve comecar com 9 apos o DDD.');
                                    }
                                };
                            }),
                    ]),

                Section::make('Endereco')
                    ->description('Localizacao principal do cliente.')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('cep')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->placeholder('00000-000')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) {
                                        return;
                                    }

                                    $digits = preg_replace('/\D/', '', $value);

                                    if (strlen($digits) !== 8) {
                                        $fail('O CEP deve ter 8 digitos.');

                                        return;
                                    }

                                    if (preg_match('/^(\d)\1+$/', $digits) === 1) {
                                        $fail('CEP invalido.');
                                    }
                                };
                            }),

                        Select::make('uf')
                            ->label('UF')
                            ->options(static::ufOptions())
                            ->searchable()
                            ->placeholder('Selecione'),

                        TextInput::make('cidade')
                            ->label('Cidade')
                            ->maxLength(100)
                            ->placeholder('Nome da cidade'),

                        TextInput::make('logradouro')
                            ->label('Logradouro')
                            ->maxLength(255)
                            ->placeholder('Rua, Avenida, Rodovia...')
                            ->columnSpan(2),

                        TextInput::make('numero')
                            ->label('Numero')
                            ->maxLength(20)
                            ->placeholder('Ex.: 1200 ou S/N'),

                        TextInput::make('complemento')
                            ->label('Complemento')
                            ->maxLength(100)
                            ->placeholder('Sala, Andar, Bloco...'),

                        TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(100)
                            ->placeholder('Bairro'),
                    ]),

                Section::make('Observacoes')
                    ->description('Notas internas, condicoes especiais ou historico relevante do cliente.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacao')
                            ->label('Observacoes internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Ex.: Cliente preferencial. Contrato renovado em jan/2026.')
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => 'As observacoes nao podem ultrapassar 2.000 caracteres.',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    TextColumn::make('codigo_interno')
                        ->label('Codigo')
                        ->description('Codigo', position: 'above')
                        ->searchable()
                        ->sortable()
                        ->fontFamily('mono')
                        ->badge()
                        ->color('gray')
                        ->grow(false)
                        ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),

                    Stack::make([
                        TextColumn::make('razao_social')
                            ->label('Razao social')
                            ->searchable()
                            ->sortable()
                            ->weight('semibold')
                            ->description(fn (Cliente $record) => $record->nome_fantasia)
                            ->wrap()
                            ->extraAttributes(['class' => 'crm-list-title'], merge: true),

                        TextColumn::make('cnpj')
                            ->label('Documento fiscal')
                            ->searchable(query: function (Builder $query, string $search): Builder {
                                $normalized = TaxIdentifier::normalizeForLookup($search) ?? '';

                                return $query->where(function (Builder $builder) use ($search, $normalized): void {
                                    $builder->where('cnpj', 'like', "%{$search}%");

                                    if ($normalized !== '') {
                                        $builder->orWhereRaw(
                                            TaxIdentifier::comparableExpression('cnpj').' LIKE ?',
                                            ["%{$normalized}%"],
                                        );
                                    }
                                });
                            })
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),
                    ]),

                    TextColumn::make('statusCliente.nome')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match (true) {
                            str_contains(strtolower($state), 'ativo') => 'success',
                            str_contains(strtolower($state), 'prospect') => 'info',
                            str_contains(strtolower($state), 'inativo') => 'danger',
                            str_contains(strtolower($state), 'suspenso') => 'warning',
                            str_contains(strtolower($state), 'negociacao') => 'warning',
                            default => 'gray',
                        })
                        ->grow(false)
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
                        TextColumn::make('categoriaSegmento.nome')
                            ->label('Segmento')
                            ->description('Segmento', position: 'above')
                            ->sortable()
                            ->badge()
                            ->color('info')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('cidade')
                            ->label('Cidade / UF')
                            ->description('Cidade / UF', position: 'above')
                            ->formatStateUsing(fn (Cliente $record) => implode(' / ', array_filter([$record->cidade, $record->uf])))
                            ->color('gray')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('telefone')
                            ->label('Telefone')
                            ->description('Telefone', position: 'above')
                            ->searchable()
                            ->icon(Heroicon::OutlinedPhone)
                            ->color('gray')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('vendedor.name')
                            ->label('Vendedor')
                            ->description('Vendedor', position: 'above')
                            ->placeholder('Sem vendedor')
                            ->icon(Heroicon::OutlinedUser)
                            ->sortable()
                            ->searchable()
                            ->color('gray')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),
            ])
            ->defaultSort('razao_social')
            ->searchPlaceholder('Buscar por razao social, documento fiscal ou codigo...')
            ->filters([
                SelectFilter::make('id_categoria_segmento')
                    ->label('Segmento')
                    ->relationship('categoriaSegmento', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('id_status_cliente')
                    ->label('Status do cliente')
                    ->relationship('statusCliente', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('vendedor_id')
                    ->label('Vendedor')
                    ->relationship(
                        'vendedor',
                        'name',
                        fn (Builder $query): Builder => $query
                            ->whereHas(
                                'roles',
                                fn (Builder $roles): Builder => $roles
                                    ->where('name', RolesEnum::Vendedor->value),
                            )
                            ->orderBy('name'),
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('uf')
                    ->label('UF')
                    ->options(
                        Cliente::query()
                            ->whereNotNull('uf')
                            ->where('uf', '!=', '')
                            ->distinct()
                            ->orderBy('uf')
                            ->pluck('uf', 'uf')
                            ->toArray()
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--registry')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Editar'),
                    DeleteAction::make()->label('Excluir'),
                ])
                    ->label('Ações')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ], position: RecordActionsPosition::AfterContent)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Excluir selecionados'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClientes::route('/'),
        ];
    }

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
}
