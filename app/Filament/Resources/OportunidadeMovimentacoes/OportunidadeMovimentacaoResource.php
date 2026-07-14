<?php

namespace App\Filament\Resources\OportunidadeMovimentacoes;

use App\Filament\Resources\OportunidadeMovimentacoes\Pages\ManageOportunidadeMovimentacoes;
use App\Models\OportunidadeMovimentacao;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OportunidadeMovimentacaoResource extends Resource
{
    protected static ?string $model = OportunidadeMovimentacao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Movimentação da oportunidade';

    protected static ?string $pluralModelLabel = 'Movimentações da oportunidade';

    public static ?string $slug = 'oportunidade-movimentacoes';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('oportunidade', fn (Builder $query) => $query->visiveisPara(auth()->user()));
    }

    public static function formComponents(bool $withOportunidade = true): array
    {
        $components = [];

        if ($withOportunidade) {
            $components[] = Select::make('oportunidade_id')
                ->label('Oportunidade')
                ->relationship('oportunidade', 'titulo', modifyQueryUsing: fn (Builder $query) => $query->visiveisPara(auth()->user()))
                ->searchable()
                ->preload();
        }

        $components[] = Select::make('user_id')
            ->label('Usuário')
            ->relationship('user', 'name')
            ->searchable()
            ->preload();

        $components[] = Select::make('etapa_origem_id')
            ->label('Etapa de origem')
            ->relationship('etapaOrigem', 'nome')
            ->searchable()
            ->preload();

        $components[] = Select::make('etapa_destino_id')
            ->label('Etapa de destino')
            ->relationship('etapaDestino', 'nome')
            ->searchable()
            ->preload();

        $components[] = DateTimePicker::make('movido_em')
            ->label('Movido em')
            ->seconds(false);

        $components[] = Textarea::make('motivo')
            ->label('Motivo')
            ->rows(4)
            ->columnSpanFull();

        return $components;
    }

    public static function tableColumns(bool $withOportunidade = true): array
    {
        $identity = [
            TextColumn::make('etapaOrigem.nome')
                ->label('Origem')
                ->badge()
                ->placeholder('Sem origem')
                ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),

            TextColumn::make('etapaDestino.nome')
                ->label('Destino')
                ->badge()
                ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
        ];

        if ($withOportunidade) {
            array_unshift($identity, TextColumn::make('oportunidade.titulo')
                ->label('Oportunidade')
                ->searchable()
                ->toggleable()
                ->wrap()
                ->extraAttributes(['class' => 'crm-list-title'], merge: true));
        }

        return [
            Split::make([
                Stack::make($identity),

                TextColumn::make('movido_em')
                    ->label('Movido em')
                    ->description('Movido em', position: 'above')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->grow(false)
                    ->extraAttributes(['class' => 'crm-list-field'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'crm-list-top']),

            Grid::make([
                'default' => 1,
                'md' => 2,
            ])
                ->schema([
                    TextColumn::make('user.name')
                        ->label('Usuário')
                        ->description('Usuário', position: 'above')
                        ->searchable()
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    TextColumn::make('motivo')
                        ->label('Motivo')
                        ->description('Motivo', position: 'above')
                        ->limit(80)
                        ->placeholder('Sem motivo')
                        ->wrap()
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                ])
                ->extraAttributes(['class' => 'crm-list-meta']),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Movimentação')
                    ->description('Histórico automático das trocas de etapa da oportunidade.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(static::formComponents()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->defaultSort('movido_em', 'desc')
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm')
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Visualizar')
                        ->slideOver(),
                ])
                    ->label('Ações')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->button()
                    ->color('gray'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeMovimentacoes::route('/'),
        ];
    }
}
