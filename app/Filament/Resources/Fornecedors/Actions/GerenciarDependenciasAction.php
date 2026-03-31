<?php

namespace App\Filament\Resources\Fornecedors\Actions;

use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Empresas\FormaPagamento;
use App\Models\Empresas\PrazoPagamento;
use App\Models\Status\StatusHomologacao;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

class GerenciarDependenciasAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'gerenciar_dependencias';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Gerenciar dependências')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->color('gray')
            ->modalWidth('2xl')
            ->modalHeading('Gerenciar dependências')
            ->modalDescription('Cadastre e edite as opções disponíveis nos selects do formulário de fornecedores.')
            ->modalIcon(Heroicon::OutlinedCog6Tooth)
            ->slideOver(false)
            ->fillForm(function (): array {
                return [
                    'categorias'      => CategoriaFornecimento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'status'          => StatusHomologacao::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'prazos'          => PrazoPagamento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'formas'          => FormaPagamento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                ];
            })
            ->form([
                Tabs::make('dependencias')
                    ->tabs([

                        Tab::make('Categorias de fornecimento')
                            ->icon(Heroicon::OutlinedTag)
                            ->schema([
                                Repeater::make('categorias')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Reagentes / insumos'),
                                    ])
                                    ->addActionLabel('Adicionar categoria')
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? null)
                                    ->defaultItems(0),
                            ]),

                        Tab::make('Status de homologação')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->schema([
                                Repeater::make('status')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Homologado'),
                                    ])
                                    ->addActionLabel('Adicionar status')
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? null)
                                    ->defaultItems(0),
                            ]),

                        Tab::make('Prazos de pagamento')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Repeater::make('prazos')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: 30 dias'),
                                    ])
                                    ->addActionLabel('Adicionar prazo')
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? null)
                                    ->defaultItems(0),
                            ]),

                        Tab::make('Formas de pagamento')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Repeater::make('formas')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Boleto bancário'),
                                    ])
                                    ->addActionLabel('Adicionar forma')
                                    ->reorderable()
                                    ->collapsible()
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? null)
                                    ->defaultItems(0),
                            ]),

                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->sincronizar(
                    CategoriaFornecimento::class,
                    $data['categorias'] ?? []
                );

                $this->sincronizar(
                    StatusHomologacao::class,
                    $data['status'] ?? []
                );

                $this->sincronizar(
                    PrazoPagamento::class,
                    $data['prazos'] ?? []
                );

                $this->sincronizar(
                    FormaPagamento::class,
                    $data['formas'] ?? []
                );

                Notification::make()
                    ->title('Dependências atualizadas')
                    ->body('Todos os registros foram salvos com sucesso.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Sincroniza os itens do Repeater com a tabela correspondente.
     * - Itens com 'id' existente → atualiza o nome
     * - Itens sem 'id' → cria novo registro
     * - IDs que sumiram do Repeater → NÃO deleta (segurança: pode ter FK ativa)
     */
    private function sincronizar(string $model, array $itens): void
    {
        $idsEnviados = [];

        foreach ($itens as $item) {
            if (!empty($item['id'])) {
                // Atualiza existente
                $model::where('id', $item['id'])->update(['nome' => $item['nome']]);
                $idsEnviados[] = $item['id'];
            } else {
                // Cria novo
                $novo = $model::create(['nome' => $item['nome']]);
                $idsEnviados[] = $novo->id;
            }
        }
    }
}