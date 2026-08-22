<?php

namespace App\Filament\Pages\Estoque;

use App\Models\InsumoMovimentacao;
use App\Models\ProdutoMovimentacao;
use App\Services\Produtos\EstoqueAcompanhamentoService;
use App\Support\Ui\NumericFormat;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\WithPagination;
use UnitEnum;

class AcompanhamentoEstoque extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Acompanhamento';

    protected static ?string $title = 'Acompanhamento de estoque';

    protected static string|UnitEnum|null $navigationGroup = 'Estoque';

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'acompanhamento-estoque';

    protected string $view = 'filament.pages.estoque.acompanhamento-estoque';

    protected Width|string|null $maxWidth = Width::Full;

    public string $itemType = '';

    public string $itemSearch = '';

    public string $movementType = '';

    public string $originType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('viewAny', ProdutoMovimentacao::class)
            || $user->can('viewAny', InsumoMovimentacao::class);
    }

    public function getHeading(): string
    {
        return '';
    }

    /** @return array<string> */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function updated(string $property): void
    {
        $itemFilters = ['itemType', 'itemSearch'];
        $movementFilters = [...$itemFilters, 'movementType', 'originType', 'dateFrom', 'dateTo'];

        if (in_array($property, $itemFilters, true)) {
            $this->resetPage('itemsPage');
        }

        if (in_array($property, $movementFilters, true)) {
            $this->resetPage('movementsPage');
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'itemType',
            'itemSearch',
            'movementType',
            'originType',
            'dateFrom',
            'dateTo',
        ]);

        $this->resetPage('itemsPage');
        $this->resetPage('movementsPage');
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'item_type' => $this->itemType,
            'item_search' => $this->itemSearch,
            'movement_type' => $this->movementType,
            'origin_type' => $this->originType,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    /** @return list<string> */
    public function accessibleItemTypes(): array
    {
        $user = auth()->user();
        $types = [];

        if ($user?->can('viewAny', ProdutoMovimentacao::class)) {
            $types[] = EstoqueAcompanhamentoService::ITEM_PRODUTO;
        }

        if ($user?->can('viewAny', InsumoMovimentacao::class)) {
            $types[] = EstoqueAcompanhamentoService::ITEM_INSUMO;
        }

        return $types;
    }

    /** @return array<string, string> */
    public function itemTypeOptions(): array
    {
        $available = [
            EstoqueAcompanhamentoService::ITEM_PRODUTO => 'Produtos',
            EstoqueAcompanhamentoService::ITEM_INSUMO => 'Insumos',
        ];

        return array_intersect_key($available, array_flip($this->accessibleItemTypes()));
    }

    /** @return array<string, string> */
    public function movementTypeOptions(): array
    {
        return $this->stockMonitoring()->movementTypeOptions();
    }

    /** @return array<string, string> */
    public function originOptions(): array
    {
        return $this->stockMonitoring()->originOptions($this->accessibleItemTypes());
    }

    public function getItemsProperty(): LengthAwarePaginator
    {
        return $this->stockMonitoring()->paginateItems(
            $this->filters(),
            $this->accessibleItemTypes(),
        );
    }

    public function getMovementsProperty(): LengthAwarePaginator
    {
        return $this->stockMonitoring()->paginateMovements(
            $this->filters(),
            $this->accessibleItemTypes(),
        );
    }

    /** @return array{itens:int, alertas:int, entradas:int, saidas:int, saidas_venda:int} */
    public function getSummaryProperty(): array
    {
        return $this->stockMonitoring()->summary(
            $this->filters(),
            $this->accessibleItemTypes(),
        );
    }

    public function itemTypeLabel(?string $type): string
    {
        return $type === EstoqueAcompanhamentoService::ITEM_INSUMO ? 'Insumo' : 'Produto';
    }

    public function movementTypeLabel(?string $type): string
    {
        return $this->stockMonitoring()->movementTypeLabel($type);
    }

    public function originLabel(?string $origin, ?string $saleDocument = null): string
    {
        $label = $this->stockMonitoring()->originLabel($origin);

        if ($origin === 'venda' && filled($saleDocument)) {
            return "{$label} · {$saleDocument}";
        }

        return $label;
    }

    public function quantity(mixed $value): string
    {
        return NumericFormat::decimal($value);
    }

    public function impact(mixed $value): string
    {
        $number = (float) $value;
        $prefix = $number > 0 ? '+' : '';

        return $prefix.NumericFormat::decimal($number);
    }

    public function humanDate(mixed $value): string
    {
        if (blank($value)) {
            return 'Data não informada';
        }

        return Carbon::parse($value)->format('d/m/Y · H:i');
    }

    protected function stockMonitoring(): EstoqueAcompanhamentoService
    {
        return app(EstoqueAcompanhamentoService::class);
    }
}
