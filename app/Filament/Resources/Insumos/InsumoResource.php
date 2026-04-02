<?php

namespace App\Filament\Resources\Insumos;

use App\Filament\Resources\Insumos\Pages\ManageInsumos;
use App\Models\Produtos\Insumo;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Enums\FiltersLayout;

class InsumoResource extends Resource
{
    protected static ?string $model = Insumo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Insumos';

    protected static ?string $modelLabel = 'Insumo';

    protected static ?string $pluralModelLabel = 'Insumos';

    protected static ?int $navigationSort = 4;

    // ──────────────────────────────────────────────────────────────────────────
    // FORM
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Identificação ─────────────────────────────────────────────
                Section::make('Identificação')
                    ->description('Dados de cadastro e classificação do insumo.')
                    ->icon(Heroicon::OutlinedBeaker)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        TextInput::make('nome')
                            ->label('Nome do insumo')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: Ácido Clorídrico P.A. 37%')
                            ->validationMessages([
                                'required' => 'O nome do insumo é obrigatório.',
                                'max'      => 'O nome não pode ultrapassar 255 caracteres.',
                            ]),

                        TextInput::make('codigo_interno')
                            ->label('Código interno')
                            ->maxLength(50)
                            ->placeholder('Gerado automaticamente (ex.: INS-2024-001)')
                            ->helperText('Deixe em branco para geração automática.')
                            ->unique(table: 'insumos', column: 'codigo_interno', ignoreRecord: true)
                            ->validationMessages([
                                'unique' => 'Este código interno já está em uso.',
                            ]),

                        TextInput::make('ncm')
                            ->label('NCM')
                            ->maxLength(10)
                            ->placeholder('0000.00.00')
                            ->helperText('Nomenclatura Comum do Mercosul (8 dígitos).'),

                        Select::make('tipo_insumo_id')
                            ->label('Tipo de insumo')
                            ->relationship('tipoInsumo', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o tipo de insumo.',
                            ]),

                        Select::make('status_insumo_id')
                            ->label('Status')
                            ->relationship('statusInsumo', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o status do insumo.',
                            ]),

                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->rows(3)
                            ->maxLength(1000)
                            ->placeholder('Descreva as características técnicas do insumo.')
                            ->columnSpanFull()
                            ->validationMessages([
                                'max' => 'A descrição não pode ultrapassar 1.000 caracteres.',
                            ]),
                    ]),

                // ── Dados Técnicos / Comerciais ───────────────────────────────
                Section::make('Dados técnicos e comerciais')
                    ->description('Unidade de medida, armazenamento, custo de referência e estoque mínimo.')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([

                        Select::make('tipo_unidade_medida_id')
                            ->label('Unidade de medida')
                            ->relationship('tipoUnidadeMedida', 'nome')
                            ->getOptionLabelFromRecordUsing(fn($record) => $record->sigla
                                ? "{$record->nome} ({$record->sigla})"
                                : $record->nome)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione a unidade de medida.',
                            ]),

                        Select::make('tipo_armazenamento_id')
                            ->label('Tipo de armazenamento')
                            ->relationship('tipoArmazenamento', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->validationMessages([
                                'required' => 'Selecione o tipo de armazenamento.',
                            ]),

                        TextInput::make('custo_referencia')
                            ->label('Custo de referência (R$)')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('0,0000')
                            ->helperText('Valor unitário de referência para precificação.')
                            ->validationMessages([
                                'min' => 'O custo não pode ser negativo.',
                            ]),

                        TextInput::make('estoque_minimo')
                            ->label('Estoque mínimo')
                            ->numeric()
                            ->minValue(0)
                            ->placeholder('0,0000')
                            ->helperText('Quantidade mínima que deve haver em estoque.')
                            ->validationMessages([
                                'min' => 'O estoque mínimo não pode ser negativo.',
                            ]),
                    ]),

                // ── Observações ───────────────────────────────────────────────
                Section::make('Observações')
                    ->description('Certificações, restrições regulatórias ou notas internas relevantes.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('observacao')
                            ->label('Observações internas')
                            ->rows(4)
                            ->maxLength(2000)
                            ->placeholder('Ex.: Produto controlado ANVISA. Requer FISPQ atualizada. Validade máxima 24 meses.')
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

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn(Insumo $record) => $record->descricao
                        ? \Illuminate\Support\Str::limit($record->descricao, 60)
                        : null),

                TextColumn::make('tipoInsumo.nome')
                    ->label('Tipo')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('tipoUnidadeMedida.nome')
                    ->label('Unidade')
                    ->formatStateUsing(function (Insumo $record): string {
                        $un = $record->tipoUnidadeMedida;
                        if (!$un) return '—';
                        return $un->sigla ? "{$un->nome} ({$un->sigla})" : $un->nome;
                    })
                    ->color('gray'),

                TextColumn::make('custo_referencia')
                    ->label('Custo ref.')
                    ->money('BRL')
                    ->sortable()
                    ->color('gray'),

                TextColumn::make('estoque_minimo')
                    ->label('Est. mín.')
                    ->sortable()
                    ->color('gray')
                    ->formatStateUsing(function (Insumo $record): string {
                        $un = $record->tipoUnidadeMedida;

                        $valor = number_format((int) $record->estoque_minimo, 0, ',', '.');

                        if (!$un) {
                            return $valor;
                        }

                        return $un->sigla
                            ? "{$valor} {$un->nome} ({$un->sigla})"
                            : "{$valor} {$un->nome}";
                    }),

                TextColumn::make('tipoArmazenamento.nome')
                    ->label('Armazenamento')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('statusInsumo.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match (true) {
                        str_contains(strtolower($state), 'ativo')          => 'success',
                        str_contains(strtolower($state), 'análise')        => 'warning',
                        str_contains(strtolower($state), 'descontinuado')  => 'danger',
                        str_contains(strtolower($state), 'suspenso')       => 'danger',
                        default                                             => 'gray',
                    }),
            ])

            ->defaultSort('nome')

            ->searchPlaceholder('Buscar por nome, código ou NCM…')

            ->filters([
                SelectFilter::make('tipo_insumo_id')
                    ->label('Tipo de insumo')
                    ->relationship('tipoInsumo', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('tipo_armazenamento_id')
                    ->label('Armazenamento')
                    ->relationship('tipoArmazenamento', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status_insumo_id')
                    ->label('Status')
                    ->relationship('statusInsumo', 'nome')
                    ->searchable()
                    ->preload(),
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
            'index' => ManageInsumos::route('/'),
        ];
    }
}
