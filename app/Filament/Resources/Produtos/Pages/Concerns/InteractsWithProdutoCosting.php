<?php

namespace App\Filament\Resources\Produtos\Pages\Concerns;

use App\Services\Produtos\ProdutoPricingCalculator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait InteractsWithProdutoCosting
{
    protected function produtoCostingActions(): array
    {
        return [
            Action::make('refreshCurrentCosts')
                ->label('Atualizar custos atuais')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshCurrentCosts()),

            Action::make('applySuggestedPrice')
                ->label('Aplicar preço sugerido')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->action(fn () => $this->applySuggestedPrice()),
        ];
    }

    protected function prepareProdutoData(array $data): array
    {
        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence($data);

        $this->data = array_replace($this->data ?? [], $prepared);
        $this->form->fill($this->data);

        return $prepared;
    }

    protected function refreshCurrentCosts(): void
    {
        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence($this->data ?? []);

        $this->data = array_replace($this->data ?? [], $prepared);
        $this->form->fill($this->data);

        Notification::make()
            ->title('Snapshots de custos atualizados com o cadastro atual dos insumos.')
            ->success()
            ->send();
    }

    protected function applySuggestedPrice(): void
    {
        $prepared = app(ProdutoPricingCalculator::class)->prepareForPersistence($this->data ?? []);
        $prepared['preco_tabela'] = $prepared['preco_sugerido'];

        $this->data = array_replace($this->data ?? [], $prepared);
        $this->form->fill($this->data);

        Notification::make()
            ->title('Preço sugerido aplicado ao preço base do formulário.')
            ->success()
            ->send();
    }
}
