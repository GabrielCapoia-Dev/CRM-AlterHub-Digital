<?php

namespace App\Filament\Resources\Insumos\Actions;

use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Status\StatusInsumo;
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
            ->modalWidth('4xl')
            ->modalHeading('Gerenciar dependências')
            ->modalDescription('Cadastre e edite as opções disponíveis nos selects do formulário de insumos.')
            ->modalIcon(Heroicon::OutlinedCog6Tooth)
            ->slideOver(false)
            ->fillForm(function (): array {
                return [
                    'tipos_insumo'       => TipoInsumo::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'armazenamentos'     => TipoArmazenamento::orderBy('nome')->get(['id', 'nome'])->toArray(),
                    'unidades_medida'    => TipoUnidadeMedida::orderBy('nome')->get(['id', 'nome', 'sigla'])->toArray(),
                    'status'             => StatusInsumo::orderBy('nome')->get(['id', 'nome'])->toArray(),
                ];
            })
            ->schema([
                Tabs::make('dependencias')
                    ->tabs([

                        Tab::make('Tipos de insumo')
                            ->icon(Heroicon::OutlinedBeaker)
                            ->schema([
                                Repeater::make('tipos_insumo')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do tipo')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Reagente, Consumível, EPI')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar tipo')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo tipo')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),

                        Tab::make('Armazenamento')
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->schema([
                                Repeater::make('armazenamentos')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome do tipo de armazenamento')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Refrigerado (2–8 °C), Ambiente, Congelado')
                                            ->columnSpanFull(),
                                    ])
                                    ->addActionLabel('+ Adicionar armazenamento')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(fn (array $state): ?string => $state['nome'] ?? 'Novo armazenamento')
                                    ->defaultItems(0)
                                    ->grid(1)
                                    ->extraAttributes(['class' => 'dep-table-repeater']),
                            ]),

                        Tab::make('Unidades de medida')
                            ->icon(Heroicon::OutlinedCalculator)
                            ->schema([
                                Repeater::make('unidades_medida')
                                    ->label('')
                                    ->schema([
                                        TextInput::make('nome')
                                            ->label('Nome')
                                            ->required()
                                            ->maxLength(100)
                                            ->placeholder('Ex.: Mililitro'),

                                        TextInput::make('sigla')
                                            ->label('Sigla')
                                            ->maxLength(20)
                                            ->placeholder('Ex.: mL'),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('+ Adicionar unidade')
                                    ->reorderableWithDragAndDrop(false)
                                    ->collapsible(false)
                                    ->cloneable(false)
                                    ->itemLabel(function (array $state): ?string {
                                        $nome  = $state['nome']  ?? '';
                                        $sigla = $state['sigla'] ?? '';
                                        if ($nome && $sigla) return "{$nome} ({$sigla})";
                                        return $nome ?: 'Nova unidade';
                                    })
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
                                            ->placeholder('Ex.: Ativo, Descontinuado, Em análise')
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
                $this->sincronizar(TipoInsumo::class,       $data['tipos_insumo']    ?? [], ['nome']);
                $this->sincronizar(TipoArmazenamento::class, $data['armazenamentos'] ?? [], ['nome']);
                $this->sincronizarUnidades($data['unidades_medida'] ?? []);
                $this->sincronizar(StatusInsumo::class,     $data['status']          ?? [], ['nome']);

                Notification::make()
                    ->title('Dependências atualizadas')
                    ->body('Todos os registros foram salvos com sucesso.')
                    ->success()
                    ->send();
            });
    }

    private function sincronizar(string $model, array $itens, array $campos): void
    {
        foreach ($itens as $item) {
            $payload = array_intersect_key($item, array_flip($campos));
            if (!empty($item['id'])) {
                $model::where('id', $item['id'])->update($payload);
            } else {
                $model::create($payload);
            }
        }
    }

    private function sincronizarUnidades(array $itens): void
    {
        foreach ($itens as $item) {
            $payload = [
                'nome'  => $item['nome']  ?? '',
                'sigla' => $item['sigla'] ?? null,
            ];
            if (!empty($item['id'])) {
                TipoUnidadeMedida::where('id', $item['id'])->update($payload);
            } else {
                TipoUnidadeMedida::create($payload);
            }
        }
    }
}