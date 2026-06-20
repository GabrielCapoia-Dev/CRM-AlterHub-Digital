<?php

namespace App\Filament\Resources\Fornecedors;

use App\Filament\Resources\Fornecedors\Pages\ManageFornecedors;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\Empresas\Fornecedor;
use App\Rules\UniqueNormalizedTaxIdentifierRule;
use App\Support\Fiscal\TaxIdentifier;
use BackedEnum;
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
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FornecedorResource extends Resource
{
    protected static ?string $model = Fornecedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Fornecedores';

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados da empresa')
                    ->description('Informacoes fiscais e de identificacao do fornecedor.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('razao_social')
                            ->label('Razao social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: ReagentBio Distribuidora Ltda.')
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
                            ->placeholder('Gerado automaticamente (ex.: FOR-2024-101)')
                            ->helperText('Deixe em branco para geracao automatica.')
                            ->unique(table: 'fornecedores', column: 'codigo_interno', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Este codigo interno ja esta em uso.',
                            ]),

                        TaxIdentifierField::make('cnpj')
                            ->required()
                            ->rule(fn (?Fornecedor $record): UniqueNormalizedTaxIdentifierRule => new UniqueNormalizedTaxIdentifierRule(
                                table: 'fornecedores',
                                column: 'cnpj',
                                ignoreValue: $record?->getKey(),
                                ignoreColumn: $record?->getKeyName() ?? 'uuid',
                            ))
                            ->validationMessages([
                                'required' => 'O documento fiscal e obrigatorio.',
                            ]),

                        TextInput::make('inscricao_estadual')
                            ->label('Inscricao estadual')
                            ->placeholder('Isento ou numero')
                            ->maxLength(30)
                            ->helperText('Digite "Isento" caso o fornecedor seja isento.'),
                    ]),

                Section::make('Classificacao e condicoes comerciais')
                    ->description('Categoria de fornecimento, status de homologacao e condicoes de pagamento.')
                    ->icon(Heroicon::OutlinedTag)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('id_categoria_fornecimento')
                            ->label('Categoria de fornecimento')
                            ->relationship('categoriaFornecimento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione a categoria de fornecimento.',
                            ]),

                        Select::make('id_status_homologacao')
                            ->label('Status de homologacao')
                            ->relationship('statusHomologacao', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o status de homologacao.',
                            ]),

                        Select::make('id_prazo_pagamento')
                            ->label('Prazo de pagamento padrao')
                            ->relationship('prazoPagamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o prazo de pagamento.',
                            ]),

                        Select::make('id_forma_pagamento')
                            ->label('Forma de pagamento preferencial')
                            ->relationship('formaPagamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione a forma de pagamento.',
                            ]),
                    ]),

                Section::make('Contato principal')
                    ->description('Responsavel comercial ou fiscal para comunicacao.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome_completo')
                            ->label('Nome completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Responsavel comercial ou fiscal')
                            ->validationMessages([
                                'required' => 'O nome do contato e obrigatorio.',
                                'max' => 'O nome nao pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('cargo')
                            ->label('Cargo')
                            ->maxLength(100)
                            ->placeholder('Ex.: Executiva de contas'),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('contato@fornecedor.com.br')
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
                    ->description('Localizacao principal do fornecedor.')
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
                            ->placeholder('Ex.: 500 ou S/N'),

                        TextInput::make('complemento')
                            ->label('Complemento')
                            ->maxLength(100)
                            ->placeholder('Sala, Galpao, Bloco...'),

                        TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(100)
                            ->placeholder('Bairro'),
                    ]),

                Section::make('Certificacoes e observacoes')
                    ->description('SLA, certificacoes e notas internas relevantes.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacoes')
                            ->label('Observacoes internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Ex.: ISO 9001 homologada. Lead time medio: 5 dias uteis.')
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
                            ->description(fn (Fornecedor $record) => $record->nome_fantasia)
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
                                            TaxIdentifier::comparableExpression('cnpj') . ' LIKE ?',
                                            ["%{$normalized}%"],
                                        );
                                    }
                                });
                            })
                            ->fontFamily('mono')
                            ->extraAttributes(['class' => 'crm-list-field crm-list-code'], merge: true),
                    ]),

                    TextColumn::make('statusHomologacao.nome')
                        ->label('Status')
                        ->badge()
                        ->color(fn (string $state): string => match (true) {
                            str_contains(strtolower($state), 'homologado') && ! str_contains(strtolower($state), 'em') => 'success',
                            str_contains(strtolower($state), 'homologa') || str_contains(strtolower($state), 'andamento') => 'warning',
                            str_contains(strtolower($state), 'suspenso') => 'danger',
                            str_contains(strtolower($state), 'penden') => 'gray',
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
                    'xl' => 3,
                ])
                    ->schema([
                        TextColumn::make('categoriaFornecimento.nome')
                            ->label('Categoria')
                            ->description('Categoria', position: 'above')
                            ->sortable()
                            ->badge()
                            ->color('info')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('cidade')
                            ->label('Cidade / UF')
                            ->description('Cidade / UF', position: 'above')
                            ->formatStateUsing(fn (Fornecedor $record) => implode(' / ', array_filter([$record->cidade, $record->uf])))
                            ->color('gray')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                        TextColumn::make('telefone')
                            ->label('Telefone')
                            ->description('Telefone', position: 'above')
                            ->searchable()
                            ->icon(Heroicon::OutlinedPhone)
                            ->color('gray')
                            ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                    ])
                    ->extraAttributes(['class' => 'crm-list-meta']),
            ])
            ->defaultSort('razao_social')
            ->searchPlaceholder('Buscar por razao social, documento fiscal ou codigo...')
            ->filters([
                SelectFilter::make('id_categoria_fornecimento')
                    ->label('Categoria')
                    ->relationship('categoriaFornecimento', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('id_status_homologacao')
                    ->label('Status de homologacao')
                    ->relationship('statusHomologacao', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('uf')
                    ->label('UF')
                    ->options(
                        Fornecedor::query()
                            ->whereNotNull('uf')
                            ->where('uf', '!=', '')
                            ->distinct()
                            ->orderBy('uf')
                            ->limit(5)
                            ->pluck('uf', 'uf')
                            ->toArray()
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--registry')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
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
            'index' => ManageFornecedors::route('/'),
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
