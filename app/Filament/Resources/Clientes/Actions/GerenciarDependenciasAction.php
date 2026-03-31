<?php

namespace App\Filament\Resources\Clientes\Actions;

use App\Models\Status\StatusCliente;
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
            ->modalDescription('Cadastre e edite as opções disponíveis nos selects do formulário de clientes.')
            ->modalIcon(Heroicon::OutlinedCog6Tooth)
            ->slideOver(false)
            ->fillForm(function (): array {
                return [
                    'status' => StatusCliente::orderBy('nome')->get(['id', 'nome'])->toArray(),
                ];
            })
            ->form([
                Tabs::make('dependencias')
                    ->tabs([

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
                                            ->placeholder('Ex.: Ativo')
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

                    ])
                    ->columnSpanFull(),
            ])
            ->action(function (array $data): void {
                $this->sincronizar(StatusCliente::class, $data['status'] ?? []);

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
            if (!empty($item['id'])) {
                $model::where('id', $item['id'])->update(['nome' => $item['nome']]);
            } else {
                $model::create(['nome' => $item['nome']]);
            }
        }
    }
}