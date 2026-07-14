<?php

namespace App\Filament\Resources\Transportadoras;

use App\Enum\ModalidadeEntrega;
use App\Filament\Resources\Transportadoras\Pages\ManageTransportadoras;
use App\Models\Clientes\Cliente;
use App\Models\Transportadora;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class TransportadoraResource extends Resource
{
    protected static ?string $model = Transportadora::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Transportadoras';

    protected static ?string $modelLabel = 'Transportadora';

    protected static ?string $pluralModelLabel = 'Transportadoras';

    public static ?string $slug = 'logistica/transportadoras';

    protected static string|UnitEnum|null $navigationGroup = 'Logistica';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transportadora')
                ->description('Dados cadastrais e valores padrao usados na cotacao de frete.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('codigo_interno')
                        ->label('Codigo')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('Gerado automaticamente'),

                    Toggle::make('ativo')
                        ->label('Ativa')
                        ->default(true),

                    TextInput::make('razao_social')
                        ->label('Razao social')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    TextInput::make('nome_fantasia')
                        ->label('Nome fantasia')
                        ->maxLength(255),

                    TextInput::make('cnpj')
                        ->label('CNPJ / identificacao fiscal')
                        ->maxLength(24)
                        ->unique(ignoreRecord: true),

                    TextInput::make('contato_nome')
                        ->label('Contato')
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->maxLength(255),

                    TextInput::make('telefone')
                        ->label('Telefone')
                        ->tel()
                        ->maxLength(255),

                    TextInput::make('prazo_estimado_dias')
                        ->label('Prazo estimado')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->suffix('dias'),

                    TextInput::make('valor_frete_custo_padrao')
                        ->label('Custo de frete padrao')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$'),

                    TextInput::make('valor_frete_cobrado_padrao')
                        ->label('Frete cobrado padrao')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('R$'),

                    Select::make('modalidades_entrega')
                        ->label('Modalidades atendidas')
                        ->options(ModalidadeEntrega::options())
                        ->multiple()
                        ->default([ModalidadeEntrega::Transportadora->value])
                        ->required()
                        ->columnSpanFull(),

                    Textarea::make('observacao')
                        ->label('Observacao')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),

            Section::make('Clientes vinculados')
                ->description('Sobrescreva a modalidade e os valores padrao para clientes com contrato proprio.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('clienteTransportadoras')
                        ->relationship()
                        ->addActionLabel('Vincular cliente')
                        ->defaultItems(0)
                        ->columns(2)
                        ->schema([
                            Select::make('cliente_id')
                                ->label('Cliente')
                                ->options(fn (): array => Cliente::query()
                                    ->orderBy('razao_social')
                                    ->pluck('razao_social', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->distinct()
                                ->required(),

                            Toggle::make('preferencial')
                                ->label('Transportadora preferencial'),

                            TextInput::make('codigo_cliente_transportadora')
                                ->label('Codigo do cliente na transportadora')
                                ->maxLength(255),

                            Select::make('modalidade_entrega_padrao')
                                ->label('Modalidade padrao')
                                ->options(ModalidadeEntrega::options()),

                            TextInput::make('valor_frete_custo_padrao')
                                ->label('Custo de frete')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('R$'),

                            TextInput::make('valor_frete_cobrado_padrao')
                                ->label('Frete cobrado')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('R$'),

                            Textarea::make('observacao')
                                ->label('Observacao do vinculo')
                                ->rows(2)
                                ->columnSpanFull(),
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
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('razao_social')
                    ->label('Razao social')
                    ->description(fn (Transportadora $record): ?string => $record->nome_fantasia)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cnpj')
                    ->label('Documento fiscal')
                    ->searchable()
                    ->placeholder('Nao informado'),

                TextColumn::make('telefone')
                    ->label('Telefone')
                    ->placeholder('Nao informado'),

                TextColumn::make('prazo_estimado_dias')
                    ->label('Prazo')
                    ->suffix(' dias')
                    ->placeholder('Nao informado')
                    ->sortable(),

                TextColumn::make('valor_frete_custo_padrao')
                    ->label('Custo padrao')
                    ->money('BRL')
                    ->placeholder('Nao informado')
                    ->sortable(),

                TextColumn::make('cliente_transportadoras_count')
                    ->counts('clienteTransportadoras')
                    ->label('Clientes')
                    ->badge(),

                IconColumn::make('ativo')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('razao_social')
            ->filters([
                TernaryFilter::make('ativo')
                    ->label('Situacao')
                    ->trueLabel('Ativas')
                    ->falseLabel('Inativas'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->modalWidth('6xl'),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransportadoras::route('/'),
        ];
    }
}
