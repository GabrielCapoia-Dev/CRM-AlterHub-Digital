<?php

namespace App\Filament\Resources\OportunidadeTarefas;

use App\Filament\Resources\OportunidadeTarefas\Pages\ManageOportunidadeTarefas;
use App\Models\OportunidadeTarefa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

class OportunidadeTarefaResource extends Resource
{
    protected static ?string $model = OportunidadeTarefa::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Tarefa da oportunidade';

    protected static ?string $pluralModelLabel = 'Tarefas da oportunidade';

    public static ?string $slug = 'oportunidade-tarefas';

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
            ->label('Responsável')
            ->relationship('user', 'name')
            ->searchable()
            ->preload()
            ->default(fn () => Auth::id())
            ->required();

        $components[] = TextInput::make('titulo')
            ->label('Título')
            ->required()
            ->maxLength(255)
            ->columnSpanFull();

        $components[] = Select::make('status')
            ->label('Status')
            ->options(OportunidadeTarefa::statusOptions())
            ->default('pendente')
            ->required();

        $components[] = DatePicker::make('data_prevista')
            ->label('Data prevista');

        return $components;
    }

    public static function tableColumns(bool $withOportunidade = true): array
    {
        $identity = [
            TextColumn::make('titulo')
                ->label('Título')
                ->searchable()
                ->sortable()
                ->weight('semibold')
                ->wrap()
                ->extraAttributes(['class' => 'crm-list-title'], merge: true),
        ];

        if ($withOportunidade) {
            array_unshift($identity, TextColumn::make('oportunidade.titulo')
                ->label('Oportunidade')
                ->searchable()
                ->toggleable()
                ->wrap()
                ->extraAttributes(['class' => 'crm-list-field'], merge: true));
        }

        return [
            Split::make([
                Stack::make($identity),

                TextColumn::make('status')
                    ->label('Status')
                    ->description('Status', position: 'above')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => OportunidadeTarefa::statusOptions()[$state] ?? $state)
                    ->grow(false)
                    ->extraAttributes(['class' => 'crm-list-field crm-list-status'], merge: true),
            ])
                ->from('md')
                ->extraAttributes(['class' => 'crm-list-top']),

            Grid::make([
                'default' => 1,
                'md' => 2,
            ])
                ->schema([
                    TextColumn::make('user.name')
                        ->label('Responsável')
                        ->description('Responsável', position: 'above')
                        ->searchable()
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),

                    TextColumn::make('data_prevista')
                        ->label('Data prevista')
                        ->description('Data prevista', position: 'above')
                        ->date('d/m/Y')
                        ->placeholder('Sem data')
                        ->sortable()
                        ->extraAttributes(['class' => 'crm-list-field'], merge: true),
                ])
                ->extraAttributes(['class' => 'crm-list-meta']),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tarefa')
                    ->description('Lembrete ou follow-up vinculado à oportunidade.')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema(static::formComponents()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::tableColumns())
            ->defaultSort('data_prevista')
            ->recordClasses(fn ($record): string => 'crm-list-record crm-list-record--crm')
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()->label('Excluir'),
            ], position: RecordActionsPosition::AfterContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOportunidadeTarefas::route('/'),
        ];
    }
}
