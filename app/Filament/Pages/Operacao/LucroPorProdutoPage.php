<?php

namespace App\Filament\Pages\Operacao;

use App\Enum\PermissoesEnum;
use App\Filament\Clusters\AcompanhamentoCluster;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class LucroPorProdutoPage extends BaseOperacaoPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Custos e lucro por produto';

    protected static ?string $title = 'Custos e lucro por produto';

    protected static ?string $cluster = AcompanhamentoCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'produtos';

    protected string $view = 'filament.pages.operacao.lucro-por-produto';

    public string $sort = 'lucro_liquido';

    public ?int $selectedProdutoId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(PermissoesEnum::ListarLucroPorProduto->value) ?? false;
    }

    public function selectProduto(int $produtoId): void
    {
        $this->selectedProdutoId = $produtoId;
    }

    public function getRowsProperty(): array
    {
        $rows = $this->analytics()->getProfitByProductRows($this->filters());

        return match ($this->sort) {
            'receita' => collect($rows)->sortByDesc('receita_bruta')->values()->all(),
            'margem' => collect($rows)->sortByDesc(fn (array $row): float => (float) ($row['margem_lucro_liquido_perc'] ?? -INF))->values()->all(),
            'volume' => collect($rows)->sortByDesc('qtd')->values()->all(),
            'pior' => collect($rows)->sortBy('lucro_liquido')->values()->all(),
            default => collect($rows)->sortByDesc('lucro_liquido')->values()->all(),
        };
    }

    public function getDrilldownProperty(): array
    {
        if (! $this->selectedProdutoId) {
            return [
                'vendas' => [],
                'despesas' => [],
            ];
        }

        return $this->analytics()->getProductDrilldown($this->filters(), $this->selectedProdutoId);
    }
}
