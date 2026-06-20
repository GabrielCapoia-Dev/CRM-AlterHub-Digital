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
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
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
        $identity = [
            TextColumn::make('tipo')
                ->label('Tipo')
                ->badge()
                ->formatStateUsing(fn (string $state): string => OportunidadeInteracao::tipoOptions()[$state] ?? $state)
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

                TextColumn::make('ocorreu_em')
                    ->label('Ocorreu em')
                    ->description('Ocorreu em', position: 'above')
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

                    TextColumn::make('nota')
                        ->label('Nota')
                        ->description('Nota', position: 'above')
                        ->limit(80)
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
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeInteracoes::route('/'),
        ];
    }
}
