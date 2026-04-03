<?php

namespace App\Filament\Resources\OportunidadeMovimentacoes;

use App\Filament\Resources\OportunidadeMovimentacoes\Pages\ManageOportunidadeMovimentacoes;
use App\Models\OportunidadeMovimentacao;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OportunidadeMovimentacaoResource extends Resource
{
    protected static ?string $model = OportunidadeMovimentacao::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Movimentação da oportunidade';

    protected static ?string $pluralModelLabel = 'Movimentações da oportunidade';

    public static ?string $slug = 'oportunidade-movimentacoes';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function formComponents(bool $withOportunidade = true): array
    {
        $components = [];

        if ($withOportunidade) {
            $components[] = Select::make('oportunidade_id')
                ->label('Oportunidade')
                ->relationship('oportunidade', 'titulo')
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
        $columns = [];

        if ($withOportunidade) {
            $columns[] = TextColumn::make('oportunidade.titulo')
                ->label('Oportunidade')
                ->searchable()
                ->toggleable();
        }

        $columns[] = TextColumn::make('etapaOrigem.nome')
            ->label('Origem')
            ->badge()
            ->placeholder('Sem origem');

        $columns[] = TextColumn::make('etapaDestino.nome')
            ->label('Destino')
            ->badge();

        $columns[] = TextColumn::make('user.name')
            ->label('Usuário')
            ->searchable();

        $columns[] = TextColumn::make('movido_em')
            ->label('Movido em')
            ->dateTime('d/m/Y H:i')
            ->sortable();

        $columns[] = TextColumn::make('motivo')
            ->label('Motivo')
            ->limit(80)
            ->placeholder('Sem motivo');

        return $columns;
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
            ->recordActions([
                ViewAction::make()
                    ->label('Visualizar')
                    ->slideOver(),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeMovimentacoes::route('/'),
        ];
    }
}
