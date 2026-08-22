<?php

namespace App\Filament\Resources\DocumentoConfiguracoes;

use App\Filament\Resources\DocumentoConfiguracoes\Pages\ManageDocumentoConfiguracoes;
use App\Filament\Support\Fields\TaxIdentifierField;
use App\Models\DocumentoConfiguracao;
use App\Services\Email\SmtpConfigurationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class DocumentoConfiguracaoResource extends Resource
{
    protected static ?string $model = DocumentoConfiguracao::class;

    protected static bool $shouldRegisterNavigation = false;

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
                ->columnSpanFull()
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

            Section::make('Envio de e-mails')
                ->description('Credenciais usadas em recuperacao de acesso e demais notificacoes por e-mail.')
                ->icon(Heroicon::OutlinedEnvelope)
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('smtp_enabled')
                        ->label('Ativar envio por SMTP')
                        ->helperText('Enquanto estiver desativado ou incompleto, o sistema mantem a configuracao do servidor.')
                        ->default(false)
                        ->live()
                        ->columnSpanFull(),

                    TextInput::make('smtp_host')
                        ->label('Servidor SMTP')
                        ->default('smtp.hostinger.com')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'smtp.hostinger.com')
                        ->placeholder('smtp.hostinger.com')
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    TextInput::make('smtp_port')
                        ->label('Porta SMTP')
                        ->numeric()
                        ->default(465)
                        ->formatStateUsing(fn (mixed $state): int => filled($state) ? (int) $state : 465)
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->minValue(1)
                        ->maxValue(65535)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    Select::make('smtp_encryption')
                        ->label('Seguranca da conexao')
                        ->options([
                            'ssl' => 'SSL/TLS implicito (recomendado para porta 465)',
                            'tls' => 'STARTTLS obrigatorio (normalmente porta 587)',
                            'none' => 'Sem criptografia',
                        ])
                        ->default('ssl')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'ssl')
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    TextInput::make('smtp_username')
                        ->label('Usuario SMTP')
                        ->helperText('Na Hostinger, normalmente e o endereco de e-mail completo.')
                        ->placeholder('sistema@unibiotechbrasil.com.br')
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    TextInput::make('smtp_password')
                        ->label('Senha SMTP')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->afterStateHydrated(function (TextInput $component): void {
                            $component->state(null);
                        })
                        ->dehydrated(fn (mixed $state): bool => filled($state))
                        ->required(fn (Get $get, ?DocumentoConfiguracao $record): bool => (bool) $get('smtp_enabled')
                            && blank($record?->getRawOriginal('smtp_password')))
                        ->helperText(fn (?DocumentoConfiguracao $record): string => filled($record?->getRawOriginal('smtp_password'))
                            ? 'Uma senha criptografada ja esta armazenada. Deixe em branco para mante-la.'
                            : 'A senha sera criptografada antes de ser armazenada.')
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    TextInput::make('mail_from_address')
                        ->label('E-mail remetente')
                        ->email()
                        ->placeholder('sistema@unibiotechbrasil.com.br')
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),

                    TextInput::make('mail_from_name')
                        ->label('Nome do remetente')
                        ->default('Unibiotech')
                        ->formatStateUsing(fn (?string $state): string => filled($state) ? $state : 'Unibiotech')
                        ->required(fn (Get $get): bool => (bool) $get('smtp_enabled'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('smtp_enabled')),
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
                IconColumn::make('smtp_enabled')
                    ->label('Envio de e-mail')
                    ->boolean(),
                TextColumn::make('updated_at')->label('Atualizado em')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('testarEmail')
                    ->label('Testar e-mail')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->modalHeading('Testar configuracao de e-mail')
                    ->modalDescription('Enviaremos uma mensagem real usando as credenciais SMTP salvas.')
                    ->modalSubmitActionLabel('Enviar teste')
                    ->schema([
                        TextInput::make('recipient')
                            ->label('Destinatario do teste')
                            ->email()
                            ->required()
                            ->default(fn (): ?string => auth()->user()?->email),
                    ])
                    ->visible(fn (DocumentoConfiguracao $record): bool => $record->smtp_enabled
                        && (auth()->user()?->can('update', $record) ?? false))
                    ->authorize(fn (DocumentoConfiguracao $record): bool => auth()->user()?->can('update', $record) ?? false)
                    ->action(function (DocumentoConfiguracao $record, array $data): void {
                        try {
                            app(SmtpConfigurationService::class)->sendTest(
                                $record,
                                (string) $data['recipient'],
                            );

                            Notification::make()
                                ->success()
                                ->title('E-mail de teste enviado')
                                ->body('Confira a caixa de entrada e a pasta de spam do destinatario informado.')
                                ->send();
                        } catch (Throwable) {
                            Notification::make()
                                ->danger()
                                ->title('Nao foi possivel enviar o teste')
                                ->body('Revise servidor, porta, seguranca, usuario e senha SMTP. Nenhuma credencial foi exibida ou registrada.')
                                ->persistent()
                                ->send();
                        }
                    }),
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
