<?php

namespace App\Filament\Resources\Clientes;

use App\Filament\Resources\Clientes\Pages\ManageClientes;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\Clientes\Cliente;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('codigo_interno')
                    ->label('Codigo')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('razao_social')
                    ->label('Razao social')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Cliente $record) => $record->nome_fantasia),

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
                    ->fontFamily('mono'),

                TextColumn::make('categoriaSegmento.nome')
                    ->label('Segmento')
                    ->sortable()
                    ->badge()
                    ->color('info'),

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
                    }),

                TextColumn::make('cidade')
                    ->label('Cidade / UF')
                    ->formatStateUsing(fn (Cliente $record) => implode(' / ', array_filter([$record->cidade, $record->uf])))
                    ->color('gray'),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->icon(Heroicon::OutlinedPhone)
                    ->color('gray'),
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
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ])
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
