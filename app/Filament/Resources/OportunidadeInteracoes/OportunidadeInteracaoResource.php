<?php

namespace App\Filament\Resources\OportunidadeInteracoes;

use App\Filament\Resources\OportunidadeInteracoes\Pages\ManageOportunidadeInteracoes;
use App\Models\OportunidadeInteracao;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OportunidadeInteracaoResource extends Resource
{
    protected static ?string $model = OportunidadeInteracao::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'Interação da oportunidade';

    protected static ?string $pluralModelLabel = 'Interações da oportunidade';

    public static ?string $slug = 'oportunidade-interacoes';

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
                ->preload()
                ->required();
        }

        $components[] = Select::make('user_id')
            ->label('Responsável pelo registro')
            ->relationship('user', 'name')
            ->searchable()
            ->preload()
            ->default(fn () => Auth::id())
            ->required();

        $components[] = Select::make('tipo')
            ->label('Tipo')
            ->options(OportunidadeInteracao::tipoOptions())
            ->required();

        $components[] = DateTimePicker::make('ocorreu_em')
            ->label('Ocorreu em')
            ->seconds(false)
            ->default(now())
            ->required();

        $components[] = Textarea::make('nota')
            ->label('Nota')
            ->rows(5)
            ->required()
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

        $columns[] = TextColumn::make('tipo')
            ->label('Tipo')
            ->badge()
            ->formatStateUsing(fn (string $state): string => OportunidadeInteracao::tipoOptions()[$state] ?? $state);

        $columns[] = TextColumn::make('user.name')
            ->label('Usuário')
            ->searchable();

        $columns[] = TextColumn::make('ocorreu_em')
            ->label('Ocorreu em')
            ->dateTime('d/m/Y H:i')
            ->sortable();

        $columns[] = TextColumn::make('nota')
            ->label('Nota')
            ->limit(80);

        return $columns;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Interação')
                    ->description('Registre o histórico de contato realizado com o cliente.')
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
            ->defaultSort('ocorreu_em', 'desc')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeInteracoes::route('/'),
        ];
    }
}
