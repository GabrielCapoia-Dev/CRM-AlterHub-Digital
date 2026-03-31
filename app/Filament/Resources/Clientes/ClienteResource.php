<?php

namespace App\Filament\Resources\Clientes;

use App\Filament\Resources\Clientes\Pages\ManageClientes;
use App\Models\Clientes\Cliente;
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
use Filament\Tables\Enums\FiltersLayout;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'Cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    protected static ?int $navigationSort = 2;

    // ──────────────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Dados da Empresa ─────────────────────────────────────────
                Section::make('Dados da empresa')
                    ->description('Informações fiscais e de identificação do cliente.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('razao_social')
                            ->label('Razão social')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: LabTech Diagnósticos Ltda.')
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
                            ->placeholder('Gerado automaticamente (ex.: CLI-2024-001)')
                            ->helperText('Deixe em branco para geração automática.')
                            ->unique(table: 'clientes', column: 'codigo_interno', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Este código interno já está em uso.',
                            ]),

                        TextInput::make('cnpj')
                            ->label('CNPJ')
                            ->required()
                            ->mask('99.999.999/9999-99')
                            ->placeholder('00.000.000/0001-00')
                            ->unique(table: 'clientes', column: 'cnpj', ignoreRecord: true)
                            ->rule(function () {
                                return function (string $attribute, $value, \Closure $fail) {
                                    $cnpj = preg_replace('/\D/', '', $value);

                                    if (strlen($cnpj) !== 14) {
                                        $fail('O CNPJ deve ter 14 dígitos.');
                                        return;
                                    }

                                    if (preg_match('/^(\d)\1+$/', $cnpj)) {
                                        $fail('CNPJ inválido.');
                                        return;
                                    }

                                    $calcDigit = function (string $cnpj, int $length): int {
                                        $sum = 0;
                                        $pos = $length - 7;
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

                        TextInput::make('segmento')
                            ->label('Segmento')
                            ->maxLength(100)
                            ->placeholder('Ex.: Laboratório clínico, Hospital, Clínica')
                            ->helperText('Segmento de atuação do cliente.'),

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

                // ── Contato Principal ────────────────────────────────────────
                Section::make('Contato principal')
                    ->description('Responsável comercial ou financeiro para comunicação.')
                    ->icon(Heroicon::OutlinedUser)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('nome_completo')
                            ->label('Nome completo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Responsável comercial ou financeiro')
                            ->validationMessages([
                                'required' => 'O nome do contato é obrigatório.',
                                'max'      => 'O nome não pode ultrapassar 255 caracteres.',
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

                                    if (!in_array(strlen($digits), [10, 11])) {
                                        $fail('Informe um telefone válido com DDD (10 ou 11 dígitos).');
                                        return;
                                    }

                                    $ddd = (int) substr($digits, 0, 2);
                                    if ($ddd < 11 || $ddd > 99) {
                                        $fail('DDD inválido.');
                                        return;
                                    }

                                    if (strlen($digits) === 11 && $digits[2] !== '9') {
                                        $fail('Número de celular deve começar com 9 após o DDD.');
                                    }
                                };
                            }),
                    ]),

                // ── Endereço ─────────────────────────────────────────────────
                Section::make('Endereço')
                    ->description('Localização principal do cliente.')
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
                            ->placeholder('Ex.: 1200 ou S/N'),

                        TextInput::make('complemento')
                            ->label('Complemento')
                            ->maxLength(100)
                            ->placeholder('Sala, Andar, Bloco…'),

                        TextInput::make('bairro')
                            ->label('Bairro')
                            ->maxLength(100)
                            ->placeholder('Bairro'),
                    ]),

                // ── Observações ──────────────────────────────────────────────
                Section::make('Observações')
                    ->description('Notas internas, condições especiais ou histórico relevante do cliente.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacao')
                            ->label('Observações internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Ex.: Cliente preferencial. Contrato renovado em jan/2026. Aceita somente NF-e.')
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
                    ->description(fn (Cliente $record) => $record->nome_fantasia),

                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('segmento')
                    ->label('Segmento')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('statusCliente.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        str_contains(strtolower($state), 'ativo')      => 'success',
                        str_contains(strtolower($state), 'prospect')   => 'info',
                        str_contains(strtolower($state), 'inativo')    => 'danger',
                        str_contains(strtolower($state), 'suspenso')   => 'warning',
                        str_contains(strtolower($state), 'negociação') => 'warning',
                        default                                         => 'gray',
                    }),

                TextColumn::make('cidade')
                    ->label('Cidade / UF')
                    ->formatStateUsing(fn (Cliente $record) => implode(' — ', array_filter([$record->cidade, $record->uf])))
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

    // ──────────────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => ManageClientes::route('/'),
        ];
    }
}