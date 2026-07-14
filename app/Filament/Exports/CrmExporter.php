<?php

namespace App\Filament\Exports;

use App\Filament\Exports\Support\SpreadsheetSanitizer;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

abstract class CrmExporter extends Exporter
{
    /**
     * @return array<mixed>
     */
    public function __invoke(Model $record): array
    {
        return array_map(
            SpreadsheetSanitizer::sanitize(...),
            parent::__invoke($record),
        );
    }

    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public function getFileName(Export $export): string
    {
        return sprintf(
            '%s_%s_%s',
            $this->filePrefix(),
            now()->format('Y-m-d_His'),
            $export->getKey(),
        );
    }

    public function getJobQueue(): ?string
    {
        $queue = config('crm.exports.queue');

        return filled($queue) ? (string) $queue : null;
    }

    public function getJobConnection(): ?string
    {
        $connection = config('crm.exports.connection');

        return filled($connection) ? (string) $connection : null;
    }

    public function getJobBatchName(): ?string
    {
        return "{$this->filePrefix()}-{$this->export->getKey()}";
    }

    public function getXlsxCellStyle(): ?Style
    {
        return (new Style)
            ->setFontName('Aptos')
            ->setFontSize(10)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
    }

    public function getXlsxHeaderCellStyle(): ?Style
    {
        return (new Style)
            ->setFontName('Aptos Display')
            ->setFontSize(11)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor(Color::rgb(30, 64, 175))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
    }

    public function getXlsxWriterOptions(): ?Options
    {
        $options = new Options;
        $widths = $this->columnWidths();

        foreach (array_keys($this->columnMap) as $index => $column) {
            $width = (float) ($widths[$column] ?? 18);
            $options->setColumnWidth(max(8, min($width, 60)), $index + 1);
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $values
     */
    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        return parent::makeXlsxRow(
            array_map(SpreadsheetSanitizer::sanitize(...), $values),
            $style,
        );
    }

    public function configureXlsxWriterBeforeClose(Writer $writer): Writer
    {
        $sheetView = (new SheetView)
            ->setFreezeRow(2);

        $sheet = $writer->getCurrentSheet();
        $sheet->setSheetView($sheetView);
        $sheet->setName((string) str($this->sheetName())->limit(31, ''));

        return $writer;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $failedRows = $export->getFailedRowsCount();
        $body = trans_choice(
            ':count linha exportada com sucesso.|:count linhas exportadas com sucesso.',
            $export->successful_rows,
            ['count' => $export->successful_rows],
        );

        if ($failedRows > 0) {
            $body .= ' '.trans_choice(
                ':count linha não pôde ser exportada.|:count linhas não puderam ser exportadas.',
                $failedRows,
                ['count' => $failedRows],
            );
        }

        return $body;
    }

    /**
     * Apply policy authorization defensively inside queued export queries.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected static function scopeAuthorized(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || Gate::forUser($user)->denies('viewAny', static::getModel())) {
            return $query->whereRaw('1 = 0');
        }

        return $query;
    }

    abstract protected function filePrefix(): string;

    abstract protected function sheetName(): string;

    /**
     * @return array<string, int|float>
     */
    abstract protected function columnWidths(): array;
}
