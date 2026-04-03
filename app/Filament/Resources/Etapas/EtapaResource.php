<?php

namespace App\Filament\Resources\Etapas;

use App\Filament\Resources\Etapas\Pages\ManageEtapas;
use App\Models\Etapa;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class EtapaResource extends Resource
{
    protected static ?string $model = Etapa::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Etapas';

    protected static ?string $modelLabel = 'Etapa';

    protected static ?string $pluralModelLabel = 'Etapas';

    public static ?string $slug = 'etapas';

    protected static string | UnitEnum | null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Etapa do funil')
                    ->description('Defina a etapa e sua posição no funil comercial.')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex.: Qualificado'),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'etapas', column: 'slug', ignoreRecord: true)
                            ->placeholder('Ex.: qualificado'),

                        TextInput::make('ordem')
                            ->label('Ordem')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->placeholder('Ex.: 1'),

                        TextInput::make('cor')
                            ->label('Cor')
                            ->maxLength(20)
                            ->placeholder('Ex.: #22C55E'),

                        Toggle::make('fechamento')
                            ->label('Etapa de fechamento')
                            ->helperText('Quando ativo, a oportunidade exigirá motivo de fechamento.')
                            ->inline(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ordem')
                    ->label('Ordem')
                    ->sortable(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->fontFamily('mono')
                    ->color('gray'),

                TextColumn::make('cor')
                    ->label('Cor')
                    ->placeholder('Sem cor'),

                IconColumn::make('fechamento')
                    ->label('Fechamento')
                    ->boolean(),
            ])
            ->defaultSort('ordem')
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

    public static function getPages(): array
    {
        return [
            'index' => ManageEtapas::route('/'),
        ];
    }
}
