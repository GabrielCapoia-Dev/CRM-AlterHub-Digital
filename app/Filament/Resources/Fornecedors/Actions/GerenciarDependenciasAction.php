<?php

namespace App\Filament\Resources\Fornecedors\Actions;

use App\Enum\PermissoesEnum;
use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Empresas\FormaPagamento;
use App\Models\Empresas\PrazoPagamento;
use App\Models\Status\StatusHomologacao;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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
            ->authorize(fn (): bool => auth()->user()?->hasPermissionTo(PermissoesEnum::EditarFornecedores->value) ?? false)
            ->modalWidth('4xl')
            ->modalHeading('Gerenciar dependências')
            ->modalDescription('Cadastre e edite as opções disponíveis nos selects do formulário de fornecedores.')
            ->modalIcon(Heroicon::OutlinedCog6Tooth)
            ->slideOver(false)
            ->fillForm(function (): array {
                return [
                    'categorias' => CategoriaFornecimento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'status' => StatusHomologacao::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'prazos' => PrazoPagamento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'formas' => FormaPagamento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                ];
            })
            ->form([
                Tabs::make('dependencias')
                    ->tabs([
                        Tab::make('Categorias')
                            ->icon(Heroicon::OutlinedTag)
                            ->schema([
                                Repeater::make('categorias')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome da categoria')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Reagentes / insumos')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar categoria')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Nova categoria')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),

                        Tab::make('Status')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->schema([
                                Repeater::make('status')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do status')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Homologado')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar status')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo status')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),

                        Tab::make('Prazos')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Repeater::make('prazos')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do prazo')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: 30 dias')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar prazo')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo prazo')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),

                        Tab::make('Formas de pagamento')
                            ->icon(Heroicon::OutlinedCreditCard)
                            ->schema([
                                Repeater::make('formas')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome da forma')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Boleto bancário')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar forma')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Nova forma')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->sincronizar(CategoriaFornecimento::class, $data['categorias'] ?? []);
                $this->sincronizar(StatusHomologacao::class, $data['status'] ?? []);
                $this->sincronizar(PrazoPagamento::class, $data['prazos'] ?? []);
                $this->sincronizar(FormaPagamento::class, $data['formas'] ?? []);

                Notification::make()
                    ->title('Dependências atualizadas')
                    ->body('Todos os registros foram salvos com sucesso.')
                    ->success()
                    ->send();
            });
    }

    private function sincronizar(string $model, array $itens): void
    {
        foreach ($itens as $item) {
            if (! empty($item['id'])) {
                $model::where('id', $item['id'])->update(['nome' => $item['nome']]);
            } else {
                $model::create(['nome' => $item['nome']]);
            }
        }
    }
}
