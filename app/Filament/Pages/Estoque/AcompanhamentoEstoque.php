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
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class AcompanhamentoEstoque extends Page
{
    use WithPagination;

    /** @var list<int> */
    public const PAGE_SIZE_OPTIONS = [5, 10, 25, 50, 100];

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

    public string $selectedItem = '';

    public string $movementType = '';

    public string $originType = '';

    public string $impactDirection = '';

    public string $movementSearch = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $itemsPerPage = 5;

    public int $movementsPerPage = 5;

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

    public function updated(string $property, mixed $value = null): void
    {
        if ($property === 'itemsPerPage') {
            $this->itemsPerPage = $this->validPageSize((int) $value);
            $this->resetPage('itemsPage');

            return;
        }

        if ($property === 'movementsPerPage') {
            $this->movementsPerPage = $this->validPageSize((int) $value);
            $this->resetPage('movementsPage');

            return;
        }

        if ($property === 'itemType' && filled($this->selectedItem)) {
            $selectedType = str($this->selectedItem)->before(':')->toString();

            if (filled($this->itemType) && $selectedType !== $this->itemType) {
                $this->selectedItem = '';
            }
        }

        if ($property === 'selectedItem' && filled($this->selectedItem)) {
            $this->itemSearch = '';
        }

        $itemFilters = ['itemType', 'itemSearch', 'selectedItem'];
        $movementFilters = [
            ...$itemFilters,
            'movementType',
            'originType',
            'impactDirection',
            'movementSearch',
            'dateFrom',
            'dateTo',
        ];

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
            'selectedItem',
            'movementType',
            'originType',
            'impactDirection',
            'movementSearch',
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
            'selected_item' => $this->selectedItem,
            'movement_type' => $this->movementType,
            'origin_type' => $this->originType,
            'impact_direction' => $this->impactDirection,
            'movement_search' => $this->movementSearch,
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
        return $this->stockMonitoring()->originOptions(
            $this->accessibleItemTypes(),
            $this->filters(),
        );
    }

    /** @return array<string, string> */
    public function itemOptions(): array
    {
        return $this->stockMonitoring()->itemOptions(
            $this->accessibleItemTypes(),
            $this->itemType,
        );
    }

    /** @return list<int> */
    public function pageSizeOptions(): array
    {
        return self::PAGE_SIZE_OPTIONS;
    }

    public function getItemsProperty(): LengthAwarePaginator
    {
        return $this->stockMonitoring()->paginateItems(
            $this->filters(),
            $this->accessibleItemTypes(),
            $this->validPageSize($this->itemsPerPage),
        );
    }

    public function getMovementsProperty(): LengthAwarePaginator
    {
        return $this->stockMonitoring()->paginateMovements(
            $this->filters(),
            $this->accessibleItemTypes(),
            $this->validPageSize($this->movementsPerPage),
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

    /**
     * @return array{
     *     item:object,
     *     eventos_total:int,
     *     eventos_filtrados:int,
     *     entradas_quantidade:float,
     *     saidas_quantidade:float,
     *     variacao_quantidade:float,
     *     primeira_movimentacao:?string,
     *     ultima_movimentacao:?string
     * }|null
     */
    public function getSelectedItemOverviewProperty(): ?array
    {
        return $this->stockMonitoring()->selectedItemOverview(
            $this->filters(),
            $this->accessibleItemTypes(),
        );
    }

    public function selectItem(string $itemKey): void
    {
        if (! array_key_exists($itemKey, $this->itemOptions())) {
            return;
        }

        $this->selectedItem = $itemKey;
        $this->itemSearch = '';
        $this->resetPage('itemsPage');
        $this->resetPage('movementsPage');
    }

    public function clearSelectedItem(): void
    {
        $this->selectedItem = '';
        $this->resetPage('itemsPage');
        $this->resetPage('movementsPage');
    }

    public function exportItemsCsv(): StreamedResponse
    {
        $filters = $this->filters();
        $accessibleTypes = $this->accessibleItemTypes();
        $service = $this->stockMonitoring();

        return $this->csvDownload(
            'posicao-estoque-'.now()->format('Y-m-d-His').'.csv',
            [
                'Categoria',
                'Código interno',
                'Item',
                'Unidade',
                'Estoque físico',
                'Reservado',
                'Disponível',
                'Estoque mínimo',
                'Situação',
            ],
            function () use ($accessibleTypes, $filters, $service): iterable {
                foreach ($service->exportItems($filters, $accessibleTypes) as $item) {
                    yield [
                        $this->itemTypeLabel($item->item_tipo),
                        $item->codigo,
                        $item->nome,
                        $item->unidade,
                        (float) $item->estoque_fisico,
                        (float) $item->estoque_reservado,
                        (float) $item->estoque_disponivel,
                        $item->estoque_minimo === null ? null : (float) $item->estoque_minimo,
                        (int) $item->alerta_estoque === 1 ? 'Atenção' : 'Regular',
                    ];
                }
            },
        );
    }

    public function exportMovementsCsv(): StreamedResponse
    {
        $filters = $this->filters();
        $accessibleTypes = $this->accessibleItemTypes();
        $service = $this->stockMonitoring();

        return $this->csvDownload(
            'historico-estoque-'.now()->format('Y-m-d-His').'.csv',
            [
                'Data e hora',
                'Categoria',
                'Código interno',
                'Item',
                'Tipo',
                'Origem',
                'ID da origem',
                'Documento',
                'Documento da venda',
                'Quantidade',
                'Impacto no estoque',
                'Unidade',
                'Saldo anterior',
                'Saldo atual',
                'Responsável',
                'Destino',
                'Motivo',
                'Observação',
                'Estornada em',
            ],
            function () use ($accessibleTypes, $filters, $service): iterable {
                foreach ($service->exportMovements($filters, $accessibleTypes) as $movement) {
                    yield [
                        $this->exportDate($movement->realizado_em),
                        $this->itemTypeLabel($movement->item_tipo),
                        $movement->codigo,
                        $movement->nome,
                        $this->movementTypeLabel($movement->tipo),
                        $service->originLabel($movement->origem_tipo),
                        $movement->origem_id,
                        $movement->documento_referencia,
                        $movement->venda_documento,
                        (float) $movement->quantidade,
                        (float) $movement->impacto_estoque,
                        $movement->unidade,
                        (float) $movement->saldo_anterior,
                        (float) $movement->saldo_atual,
                        $movement->responsavel,
                        $movement->destino ?: $movement->origem_destino,
                        $movement->motivo,
                        $movement->observacao,
                        $this->exportDate($movement->estornada_em),
                    ];
                }
            },
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

    protected function validPageSize(int $value): int
    {
        return in_array($value, self::PAGE_SIZE_OPTIONS, true) ? $value : 5;
    }

    /**
     * @param  list<string>  $headers
     * @param  callable():iterable<array<int, mixed>>  $rows
     */
    protected function csvDownload(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fwrite($output, "sep=;\r\n");
            fputcsv($output, $this->safeCsvRow($headers), ';', '"', '');

            foreach ($rows() as $row) {
                fputcsv($output, $this->safeCsvRow($row), ';', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /**
     * Impede que valores textuais controlados pelo usuário sejam interpretados
     * como fórmulas ao abrir a exportação no Excel ou em outro editor de planilhas.
     *
     * @param  array<int, mixed>  $row
     * @return array<int, int|float|string>
     */
    protected function safeCsvRow(array $row): array
    {
        return array_map(function (mixed $value): int|float|string {
            if ($value === null) {
                return '';
            }

            if (is_int($value) || is_float($value)) {
                return $value;
            }

            $value = (string) $value;

            $spreadsheetValue = ltrim($value, ' ');

            if (preg_match('/^[=+\-@\t\r\n]/u', $spreadsheetValue) === 1) {
                return "'{$value}";
            }

            return $value;
        }, $row);
    }

    protected function exportDate(mixed $value): string
    {
        return blank($value) ? '' : Carbon::parse($value)->format('Y-m-d H:i:s');
    }
}
