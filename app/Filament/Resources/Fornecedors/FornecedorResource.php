<?php

namespace App\Filament\Resources\Fornecedors;

use App\Filament\Resources\Fornecedors\Pages\ManageFornecedors;
use App\Models\Empresas\Fornecedor;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Illuminate\Validation\Rule;

class FornecedorResource extends Resource
{
    protected static ?string $model = Fornecedor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingStorefront;

    protected static ?string $navigationLabel = 'Fornecedores';

    protected static ?string $modelLabel = 'Fornecedor';

    protected static ?string $pluralModelLabel = 'Fornecedores';

    protected static ?int $navigationSort = 3;

    // ──────────────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Dados da Empresa ─────────────────────────────────────────
                Section::make('Dados da empresa')
                    ->description('Informações fiscais e de identificação do fornecedor.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('razao_social')
                            ->label('Razão social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: ReagentBio Distribuidora Ltda.')
                            ->validationMessages([
                                'required' => 'A razão social é obrigatória.',
                                'max'      => 'A razão social não pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('nome_fantasia')
                            ->label('Nome fantasia')
                            ->maxLength(255)
                            ->placeholder('Nome comercial (opcional)')
                            ->validationMessages([
                                'max' => 'O nome fantasia não pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('codigo_interno')
                            ->label('Código interno')
                            ->maxLength(50)
                            ->placeholder('Gerado automaticamente (ex.: FOR-2024-101)')
                            ->helperText('Deixe em branco para geração automática.')
                            ->unique(table: 'fornecedores', column: 'codigo_interno', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Este código interno já está em uso.',
                            ]),

                        TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->required()
                            ->mask('99.999.999/9999-99')
                            ->placeholder('00.000.000/0001-00')
                            ->unique(table: 'fornecedores', column: 'cnpj', ignoreRecord: true)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $cnpj = preg_replace('/\D/', '', $value);

                                    if (strlen($cnpj) !== 14) {
                                        $fail('O CNPJ deve ter 14 dígitos.');
                                        return;
                                    }

                                    // Bloqueia sequências repetidas (00000000000000, etc.)
                                    if (preg_match('/^(\d)\1+$/', $cnpj)) {
                                        $fail('CNPJ inválido.');
                                        return;
                                    }

                                    // Validação dos dígitos verificadores
                                    $calcDigit = function (string $cnpj, int $length): int {
                                        $sum    = 0;
                                        $pos    = $length - 7;
                                        for ($i = $length; $i >= 1; $i--) {
                                            $sum += (int) $cnpj[$length - $i] * $pos--;
                                            if ($pos < 2) {
                                                $pos = 9;
                                            }
                                        }
                                        $result = $sum % 11;
                                        return $result < 2 ? 0 : 11 - $result;
                                    };

                                    if ((int) $cnpj[12] !== $calcDigit($cnpj, 12)) {
                                        $fail('CNPJ inválido.');
                                        return;
                                    }

                                    if ((int) $cnpj[13] !== $calcDigit($cnpj, 13)) {
                                        $fail('CNPJ inválido.');
                                    }
                                };
                            })
                            ->validationMessages([
                                'required' => 'O CNPJ é obrigatório.',
                                'unique'   => 'Este CNPJ já está cadastrado.',
                            ]),

                        TextInput::make('inscricao_estadual')
                            ->label('Inscrição estadual')
                            ->placeholder('Isento ou número')
                            ->maxLength(30)
                            ->helperText('Digite "Isento" caso o fornecedor seja isento.'),
                    ]),

                // ── Relacionamentos / Classificação ──────────────────────────
                Section::make('Classificação e condições comerciais')
                    ->description('Categoria de fornecimento, status de homologação e condições de pagamento.')
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
                            ->label('Status de homologação')
                            ->relationship('statusHomologacao', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o status de homologação.',
                            ]),

                        Select::make('id_prazo_pagamento')
                            ->label('Prazo de pagamento padrão')
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

                // ── Contato Principal ────────────────────────────────────────
                Section::make('Contato principal')
                    ->description('Responsável comercial ou fiscal para comunicação.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('nome_completo')
                            ->label('Nome completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Responsável comercial ou fiscal')
                            ->validationMessages([
                                'required' => 'O nome do contato é obrigatório.',
                                'max'      => 'O nome não pode ultrapassar 255 caracteres.',
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
                                'email' => 'Informe um e-mail válido.',
                                'max'   => 'O e-mail não pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('telefone')
                            ->label('Telefone / WhatsApp')
                            ->mask('(99) 99999-9999')
                            ->placeholder('(00) 00000-0000')
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    if (empty($value)) return;

                                    $digits = preg_replace('/\D/', '', $value);

                                    // Aceita 10 dígitos (fixo) ou 11 dígitos (celular)
                                    if (!in_array(strlen($digits), [10, 11])) {
                                        $fail('Informe um telefone válido com DDD (10 ou 11 dígitos).');
                                        return;
                                    }

                                    // DDD válido (11–99)
                                    $ddd = (int) substr($digits, 0, 2);
                                    if ($ddd < 11 || $ddd > 99) {
                                        $fail('DDD inválido.');
                                        return;
                                    }

                                    // Celular: deve começar com 9
                                    if (strlen($digits) === 11 && $digits[2] !== '9') {
                                        $fail('Número de celular deve começar com 9 após o DDD.');
                                    }
                                };
                            }),
                    ]),

                // ── Endereço ─────────────────────────────────────────────────
                Section::make('Endereço')
                    ->description('Localização principal do fornecedor.')
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
                                    if (empty($value)) return;

                                    $digits = preg_replace('/\D/', '', $value);

                                    if (strlen($digits) !== 8) {
                                        $fail('O CEP deve ter 8 dígitos.');
                                        return;
                                    }

                                    // Bloqueia CEPs com todos dígitos iguais
                                    if (preg_match('/^(\d)\1+$/', $digits)) {
                                        $fail('CEP inválido.');
                                    }
                                };
                            }),

                        Select::make('uf')
                            ->label('UF')
                            ->options([
                                'AC' => 'AC', 'AL' => 'AL', 'AM' => 'AM', 'AP' => 'AP',
                                'BA' => 'BA', 'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES',
                                'GO' => 'GO', 'MA' => 'MA', 'MG' => 'MG', 'MS' => 'MS',
                                'MT' => 'MT', 'PA' => 'PA', 'PB' => 'PB', 'PE' => 'PE',
                                'PI' => 'PI', 'PR' => 'PR', 'RJ' => 'RJ', 'RN' => 'RN',
                                'RO' => 'RO', 'RR' => 'RR', 'RS' => 'RS', 'SC' => 'SC',
                                'SE' => 'SE', 'SP' => 'SP', 'TO' => 'TO',
                            ])
                            ->searchable()
                            ->placeholder('Selecione'),

                        TextInput::make('cidade')
                            ->label('Cidade')
                            ->maxLength(100)
                            ->placeholder('Nome da cidade'),

                        TextInput::make('logradouro')
                            ->label('Logradouro')
                            ->maxLength(255)
                            ->placeholder('Rua, Avenida, Rodovia…')
                            ->columnSpan(2),

                        TextInput::make('numero')
                            ->label('Número')
                            ->maxLength(20)
                            ->placeholder('Ex.: 500 ou S/N'),

                        TextInput::make('complemento')
                            ->label('Complemento')
                            ->maxLength(100)
                            ->placeholder('Sala, Galpão, Bloco…'),

                        TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(100)
                            ->placeholder('Bairro'),
                    ]),

                // ── Observações ──────────────────────────────────────────────
                Section::make('Certificações e observações')
                    ->description('SLA, certificações (ISO, ANVISA, RBC) e notas internas relevantes.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacoes')
                            ->label('Observações internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Ex.: ISO 9001 homologada. Lead time médio: 5 dias úteis. Auditoria prevista para abril/2026.')
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => 'As observações não podem ultrapassar 2.000 caracteres.',
                            ]),
                    ]),
            ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('codigo_interno')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('razao_social')
                    ->label('Razão social')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Fornecedor $record) => $record->nome_fantasia),

                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('categoriaFornecimento.nome')
                    ->label('Categoria')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('statusHomologacao.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'homologado') && !str_contains(strtolower($state), 'em') => 'success',
                        str_contains(strtolower($state), 'homologaç') || str_contains(strtolower($state), 'andamento') => 'warning',
                        str_contains(strtolower($state), 'suspenso')  => 'danger',
                        str_contains(strtolower($state), 'pendên')    => 'gray',
                        default                                        => 'gray',
                    }),

                TextColumn::make('cidade')
                    ->label('Cidade / UF')
                    ->formatStateUsing(fn (Fornecedor $record) => implode(' — ', array_filter([$record->cidade, $record->uf])))
                    ->color('gray'),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->searchable()
                    ->icon(Heroicon::OutlinedPhone)
                    ->color('gray'),
            ])

            ->defaultSort('razao_social')

            ->searchPlaceholder('Buscar por razão social, CNPJ ou código…')

            ->filters([
                SelectFilter::make('id_categoria_fornecimento')
                    ->label('Categoria')
                    ->relationship('categoriaFornecimento', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('id_status_homologacao')
                    ->label('Status de homologação')
                    ->relationship('statusHomologacao', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('uf')
                    ->label('UF')
                    ->options([
                        'AC' => 'AC', 'AL' => 'AL', 'AM' => 'AM', 'AP' => 'AP',
                        'BA' => 'BA', 'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES',
                        'GO' => 'GO', 'MA' => 'MA', 'MG' => 'MG', 'MS' => 'MS',
                        'MT' => 'MT', 'PA' => 'PA', 'PB' => 'PB', 'PE' => 'PE',
                        'PI' => 'PI', 'PR' => 'PR', 'RJ' => 'RJ', 'RN' => 'RN',
                        'RO' => 'RO', 'RR' => 'RR', 'RS' => 'RS', 'SC' => 'SC',
                        'SE' => 'SE', 'SP' => 'SP', 'TO' => 'TO',
                    ]),
            ])

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

    // ──────────────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ManageFornecedors::route('/'),
        ];
    }
}