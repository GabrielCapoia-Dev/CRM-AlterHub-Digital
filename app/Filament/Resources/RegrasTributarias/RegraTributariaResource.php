<?php

namespace App\Filament\Resources\RegrasTributarias;

use App\Enum\UnidadeFederativa;
use App\Filament\Resources\RegrasTributarias\Pages\ManageRegrasTributarias;
use App\Models\RegraTributaria;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RegraTributariaResource extends Resource
{
    protected static ?string $model = RegraTributaria::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Regras tributarias';

    protected static ?string $modelLabel = 'Regra tributaria';

    protected static ?string $pluralModelLabel = 'Regras tributarias';

    public static ?string $slug = 'fiscal/regras-tributarias';

    protected static string|UnitEnum|null $navigationGroup = 'Fiscal';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Abrangencia e vigencia')
                ->description('Deixe o NCM vazio para criar a regra geral da UF; uma regra com NCM prevalece sobre ela.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('nome')
                        ->label('Nome')
                        ->maxLength(255)
                        ->required()
                        ->columnSpanFull(),

                    Select::make('uf_destino')
                        ->label('UF de destino')
                        ->options(UnidadeFederativa::options())
                        ->searchable()
                        ->required(),

                    TextInput::make('ncm')
                        ->label('NCM especifico')
                        ->maxLength(10)
                        ->placeholder('Vazio = regra geral da UF'),

                    DatePicker::make('vigencia_inicio')
                        ->label('Inicio da vigencia')
                        ->default(now())
                        ->required(),

                    DatePicker::make('vigencia_fim')
                        ->label('Fim da vigencia')
                        ->rule('after_or_equal:vigencia_inicio'),

                    Toggle::make('ativo')
                        ->label('Regra ativa')
                        ->default(true),
                ]),

            Section::make('Aliquotas')
                ->description('Percentuais aplicados sobre a base, ja considerando a reducao configurada.')
                ->columns(4)
                ->columnSpanFull()
                ->schema([
                    static::rateField('aliquota_icms', 'ICMS'),
                    static::rateField('aliquota_icms_st', 'ICMS ST'),
                    static::rateField('aliquota_ipi', 'IPI'),
                    static::rateField('aliquota_pis', 'PIS'),
                    static::rateField('aliquota_cofins', 'COFINS'),
                    static::rateField('aliquota_fcp', 'FCP'),
                    static::rateField('reducao_base_calculo', 'Reducao da base'),
                ]),

            Section::make('Observacao')
                ->columnSpanFull()
                ->schema([
                    Textarea::make('observacao')
                        ->label('Notas da versao')
                        ->rows(3),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Regra')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('uf_destino')
                    ->label('UF')
                    ->badge()
                    ->formatStateUsing(fn (UnidadeFederativa|string $state): string => $state instanceof UnidadeFederativa
                        ? $state->value
                        : $state)
                    ->sortable(),

                TextColumn::make('ncm')
                    ->label('NCM')
                    ->placeholder('Regra geral')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('versao')
                    ->label('Versao')
                    ->badge()
                    ->sortable(),

                TextColumn::make('vigencia_inicio')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('vigencia_fim')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->placeholder('Sem termino')
                    ->sortable(),

                TextColumn::make('aliquota_icms')
                    ->label('ICMS')
                    ->suffix('%')
                    ->numeric(decimalPlaces: 2),

                IconColumn::make('ativo')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->defaultSort('vigencia_inicio', 'desc')
            ->filters([
                SelectFilter::make('uf_destino')
                    ->label('UF')
                    ->options(UnidadeFederativa::options()),
            ])
            ->recordActions([
                Action::make('toggleAtiva')
                    ->label(fn (RegraTributaria $record): string => $record->ativo ? 'Desativar' : 'Ativar')
                    ->color(fn (RegraTributaria $record): string => $record->ativo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->authorize(fn (RegraTributaria $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (RegraTributaria $record): void {
                        $record->update(['ativo' => ! $record->ativo]);
                        Notification::make()->title('Situacao da regra atualizada')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRegrasTributarias::route('/'),
        ];
    }

    protected static function rateField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->step('0.0001')
            ->minValue(0)
            ->maxValue(100)
            ->suffix('%')
            ->default(0)
            ->required();
    }
}
