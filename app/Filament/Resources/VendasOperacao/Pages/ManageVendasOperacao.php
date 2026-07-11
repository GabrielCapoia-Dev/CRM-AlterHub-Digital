<?php

namespace App\Filament\Resources\VendasOperacao\Pages;

use App\Filament\Resources\VendasOperacao\VendaOperacaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVendasOperacao extends ManageRecords
{
    protected static string $resource = VendaOperacaoResource::class;

    protected string $view = 'filament.resources.vendas-operacao.pages.manage-vendas-operacao';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $copyFormDefaults = null;

    protected function getHeaderActions(): array
    {
        return [
            VendaOperacaoResource::configureCreateAction(
                CreateAction::make()
                    ->fillForm(fn (): array => $this->copyFormDefaults ?? [
                        'data_venda' => now()->toDateString(),
                        'vendedor_nome' => auth()->user()?->name,
                        'itens' => [
                            [
                                'quantidade' => null,
                                'preco_unitario' => null,
                                'icms_aliquota' => 0,
                                'outros_impostos_aliquota' => 0,
                            ],
                        ],
                    ])
                    ->after(function (): void {
                        $this->copyFormDefaults = null;
                    }),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function openCreateFromCopy(array $payload): void
    {
        $this->copyFormDefaults = $payload;
        $this->mountAction('create');
    }
}
