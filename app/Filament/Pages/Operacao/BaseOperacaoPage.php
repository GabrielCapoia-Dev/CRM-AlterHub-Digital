<?php

namespace App\Filament\Pages\Operacao;

use App\Services\Operacao\OperacaoAnalyticsService;
use App\Support\Ui\NumericFormat;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Carbon;

abstract class BaseOperacaoPage extends Page
{
    protected Width|string|null $maxWidth = Width::Full;

    public string $periodMode = 'mes';

    public string $month = '';

    public string $year = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $produtoId = '';

    public string $categoriaDespesa = '';

    public string $categoriaProdutoId = '';

    public string $vendedorNome = '';

    public string $clienteNome = '';

    public function mount(): void
    {
        $now = now();

        $this->month = (string) $now->month;
        $this->year = (string) $now->year;
        $this->dateFrom = $now->startOfMonth()->toDateString();
        $this->dateTo = $now->endOfMonth()->toDateString();
    }

    public function getHeading(): string
    {
        return '';
    }

    /**
     * @return array<string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function filters(): array
    {
        return [
            'period_mode' => $this->periodMode,
            'month' => $this->month,
            'year' => $this->year,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'produto_id' => $this->produtoId,
            'categoria_despesa' => $this->categoriaDespesa,
            'categoria_produto_id' => $this->categoriaProdutoId,
            'vendedor_nome' => $this->vendedorNome,
            'cliente_nome' => $this->clienteNome,
        ];
    }

    public function monthOptions(): array
    {
        return [
            '1' => '01',
            '2' => '02',
            '3' => '03',
            '4' => '04',
            '5' => '05',
            '6' => '06',
            '7' => '07',
            '8' => '08',
            '9' => '09',
            '10' => '10',
            '11' => '11',
            '12' => '12',
        ];
    }

    public function yearOptions(): array
    {
        return collect($this->analytics()->availableYears())
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }

    public function productOptions(): array
    {
        return $this->analytics()->produtoOptions();
    }

    public function expenseCategoryOptions(): array
    {
        return $this->analytics()->categoriaDespesaOptions();
    }

    public function productCategoryOptions(): array
    {
        return $this->analytics()->categoriaProdutoOptions();
    }

    public function sellerOptions(): array
    {
        return $this->analytics()->vendedorOptions($this->filters());
    }

    public function clientOptions(): array
    {
        return $this->analytics()->clienteOptions($this->filters());
    }

    public function money(mixed $value): string
    {
        return NumericFormat::money($value);
    }

    public function qty(mixed $value): string
    {
        return NumericFormat::decimal($value);
    }

    public function pct(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return NumericFormat::percent($value);
    }

    public function humanDate(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return Carbon::parse($value)->format('d/m/Y');
    }

    public function barWidth(mixed $value, mixed $max): string
    {
        $numericValue = abs((float) $value);
        $numericMax = max(abs((float) $max), 0.0001);
        $width = ($numericValue / $numericMax) * 100;

        return number_format(max(6, min(100, $width)), 2, '.', '');
    }

    protected function analytics(): OperacaoAnalyticsService
    {
        return app(OperacaoAnalyticsService::class);
    }
}
