<?php

namespace App\Filament\Resources\Oportunidades;

use App\Filament\Resources\Oportunidades\Pages\CreateOportunidade;
use App\Filament\Resources\Oportunidades\Pages\EditOportunidade;
use App\Filament\Resources\Oportunidades\Pages\KanbanOportunidades;
use App\Filament\Resources\Oportunidades\Pages\ListOportunidades;
use App\Filament\Resources\Oportunidades\Pages\ViewOportunidade;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeInteracoesRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeMovimentacoesRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeProdutosRelationManager;
use App\Filament\Resources\Oportunidades\RelationManagers\OportunidadeTarefasRelationManager;
use App\Models\Clientes\Cliente;
use App\Models\Etapa;
use App\Models\Oportunidade;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OportunidadeResource extends Resource
{
    protected static ?string $model = Oportunidade::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Oportunidades';

    protected static ?string $modelLabel = 'Oportunidade';

    protected static ?string $pluralModelLabel = 'Oportunidades';

    public static ?string $slug = 'oportunidades';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Negociação')
                    ->description('Dados principais da oportunidade comercial.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Ex.: Proposta reagentes linha hospitalar'),

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'razao_social')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        Placeholder::make('segmento_cliente')
                            ->label('Segmento do cliente')
                            ->content(function (Get $get): string {
                                $clienteId = $get('cliente_id');

                                if (! $clienteId) {
                                    return 'Selecione um cliente';
                                }

                                $cliente = Cliente::query()
                                    ->with('categoriaSegmento')
                                    ->find($clienteId);

                                return $cliente?->categoriaSegmento?->nome ?? 'Sem segmento';
                            }),

                        Select::make('etapa_id')
                            ->label('Etapa')
                            ->relationship('etapa', 'nome')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),

                        Select::make('user_id')
                            ->label('Responsável')
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
                            ->prefix('R$')
                            ->minValue(0)
                            ->placeholder('0,00'),
                    ]),

                Section::make('Contexto')
                    ->description('Notas adicionais e dados de fechamento da negociação.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('motivo_fechamento')
                            ->label('Motivo de fechamento')
                            ->rows(4)
                            ->visible(fn (Get $get): bool => static::etapaEhFechamento($get('etapa_id')))
                            ->required(fn (Get $get): bool => static::etapaEhFechamento($get('etapa_id')))
                            ->helperText('Obrigatório quando a etapa selecionada for de encerramento.'),

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
                TextColumn::make('titulo')
                    ->label('Oportunidade')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('cliente.razao_social')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente.categoriaSegmento.nome')
                    ->label('Segmento')
                    ->badge()
                    ->placeholder('Sem segmento'),

                TextColumn::make('etapa.nome')
                    ->label('Etapa')
                    ->badge()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Responsável')
                    ->searchable(),

                TextColumn::make('temperatura')
                    ->label('Temperatura')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Oportunidade::temperaturaOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'hot' => 'danger',
                        'warm' => 'warning',
                        default => 'info',
                    }),

                TextColumn::make('valor_estimado')
                    ->label('Valor estimado')
                    ->money('BRL')
                    ->sortable()
                    ->placeholder('Sem valor'),

                TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->searchPlaceholder('Buscar por oportunidade, cliente ou responsável...')
            ->filters([
                SelectFilter::make('etapa_id')
                    ->label('Etapa')
                    ->relationship('etapa', 'nome')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user_id')
                    ->label('Responsável')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('temperatura')
                    ->label('Temperatura')
                    ->options(Oportunidade::temperaturaOptions()),
            ])
            ->recordActions([
                ViewAction::make()->label('Visualizar'),
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ]);
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
