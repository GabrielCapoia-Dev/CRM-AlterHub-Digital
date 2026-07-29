<?php

namespace App\Filament\Resources\DocumentoConfiguracoes;

use App\Filament\Resources\DocumentoConfiguracoes\Pages\ManageDocumentoConfiguracoes;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\DocumentoConfiguracao;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
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

class DocumentoConfiguracaoResource extends Resource
{
    protected static ?string $model = DocumentoConfiguracao::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Documentos da empresa';

    protected static ?string $modelLabel = 'Configuracao de documentos';

    protected static ?string $pluralModelLabel = 'Documentos da empresa';

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    protected static ?int $navigationSort = 3;

    public static ?string $slug = 'configuracoes/documentos';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('chave')->default(DocumentoConfiguracao::CHAVE_PADRAO),
            Hidden::make('logo_disk')->default('local'),
            Hidden::make('updated_by')
                ->default(fn (): ?int => auth()->id())
                ->dehydrateStateUsing(fn (): ?int => auth()->id()),

            Section::make('Identidade institucional')
                ->description('Informacoes obrigatorias compartilhadas por pedidos e romaneios.')
                ->columns(2)
                ->schema([
                    FileUpload::make('logo_path')
                        ->label('Logotipo padrao')
                        ->disk('local')
                        ->directory('documentos/logotipos')
                        ->visibility('private')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->imagePreviewHeight('120')
                        ->helperText('JPEG, PNG ou WebP, ate 5 MB. A proporcao sera preservada nos PDFs.')
                        ->required(fn (?DocumentoConfiguracao $record): bool => blank($record?->logo_path))
                        ->columnSpanFull(),

                    TextInput::make('razao_social')
                        ->label('Razao social')
                        ->required()
                        ->maxLength(255),

                    TaxIdentifierField::make('cnpj', 'CNPJ')
                        ->required(),

                    TextInput::make('nome_comercial')
                        ->label('Nome comercial')
                        ->maxLength(255),

                    Textarea::make('texto_complementar')
                        ->label('Texto abaixo da logomarca')
                        ->rows(2)
                        ->maxLength(1000),

                    Toggle::make('exibir_valores_romaneio')
                        ->label('Exibir valores comerciais no romaneio')
                        ->default(true)
                        ->helperText('Quando desativado, o mesmo template oculta valores unitarios e subtotais.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('razao_social')->label('Razao social')->searchable(),
                TextColumn::make('cnpj')->label('CNPJ'),
                TextColumn::make('nome_comercial')->label('Nome comercial')->placeholder('-'),
                IconColumn::make('exibir_valores_romaneio')
                    ->label('Valores no romaneio')
                    ->boolean(),
                TextColumn::make('updated_at')->label('Atualizado em')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentoConfiguracoes::route('/'),
        ];
    }
}
