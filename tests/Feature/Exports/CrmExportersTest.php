<?php

namespace Tests\Feature\Exports;

use App\Enum\PermissoesEnum;
use App\Filament\Exports\Actions\CrmExportActions;
use App\Filament\Exports\CrmExporter;
use App\Filament\Exports\EstoqueAtualExporter;
use App\Filament\Exports\InsumoMovimentacaoExporter;
use App\Filament\Exports\ProdutoExporter;
use App\Filament\Exports\ProdutoMovimentacaoExporter;
use App\Filament\Exports\VendaOperacaoExporter;
use App\Models\Acesso\User;
use App\Models\Produto;
use App\Models\VendaOperacaoPedido;
use App\Services\Operacao\VendaOperacaoService;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class CrmExportersTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_export_dependencies_and_owner_policy_are_configured(): void
    {
        $this->assertTrue(Schema::hasTable('exports'));
        $this->assertTrue(Schema::hasTable('notifications'));
        $this->assertTrue(Schema::hasTable('job_batches'));
        $this->assertInstanceOf(User::class, app(Authenticatable::class));

        $owner = $this->createUser('owner');
        $otherUser = $this->createUser('other');
        $export = new Export(['user_id' => $owner->getKey()]);

        $this->assertTrue(Gate::forUser($owner)->allows('view', $export));
        $this->assertFalse(Gate::forUser($otherUser)->allows('view', $export));
    }

    public function test_all_exporters_are_xlsx_only_and_define_consistent_layouts(): void
    {
        foreach ($this->exporterClasses() as $exporterClass) {
            $export = (new Export)->forceFill(['id' => 42]);
            $columnMap = collect($exporterClass::getColumns())
                ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])
                ->all();

            $exporter = new $exporterClass($export, $columnMap, []);

            $this->assertSame([ExportFormat::Xlsx], $exporter->getFormats());
            $this->assertCount(count($columnMap), $exporter->getXlsxWriterOptions()?->getColumnWidths() ?? []);
            $this->assertTrue($exporter->getXlsxHeaderCellStyle()?->isFontBold() ?? false);
            $this->assertStringContainsString('42', $exporter->getFileName($export));
        }
    }

    public function test_export_actions_enforce_xlsx_and_configurable_bounded_chunks(): void
    {
        config()->set('crm.exports.chunk_sizes.produtos', 321);
        config()->set('crm.exports.queue', 'exports');
        config()->set('crm.exports.connection', 'database');

        $configured = CrmExportActions::produtos();
        $minimum = CrmExportActions::produtos(0);
        $maximum = CrmExportActions::produtos(99999);
        $exporter = new ProdutoExporter(new Export, [], []);

        $this->assertSame(ProdutoExporter::class, $configured->getExporter());
        $this->assertSame([ExportFormat::Xlsx], $configured->getFormats());
        $this->assertSame(321, $configured->getChunkSize());
        $this->assertSame(1, $minimum->getChunkSize());
        $this->assertSame(5000, $maximum->getChunkSize());
        $this->assertSame('exports', $exporter->getJobQueue());
        $this->assertSame('database', $exporter->getJobConnection());
    }

    public function test_export_action_authorization_follows_the_model_policy(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(PermissoesEnum::ListarProdutosCRM->value, 'web');

        $allowed = $this->createUser('allowed-export');
        $allowed->givePermissionTo(PermissoesEnum::ListarProdutosCRM->value);
        $denied = $this->createUser('denied-export');

        $this->actingAs($allowed);
        $this->assertTrue(CrmExportActions::produtos()->isAuthorized());

        $this->actingAs($denied);
        $this->assertFalse(CrmExportActions::produtos()->isAuthorized());
    }

    public function test_product_detail_export_is_restricted_to_the_current_record(): void
    {
        $produto = (new Produto)->forceFill(['id' => 123]);
        $action = CrmExportActions::produtoDetalhe()->record($produto);
        $property = new \ReflectionProperty($action, 'modifyQueryUsing');
        $modifyQuery = $property->getValue($action);

        $query = $action->evaluate($modifyQuery, [
            'query' => Produto::query(),
            'options' => [],
        ]);

        $this->assertSame(ProdutoExporter::class, $action->getExporter());
        $this->assertSame(1, $action->getChunkSize());
        $this->assertSame([123], $query->getBindings());
    }

    public function test_formula_sanitization_is_applied_to_every_exported_text_cell(): void
    {
        $exporter = new ProdutoExporter(
            (new Export)->forceFill(['id' => 1]),
            ['codigo_interno' => 'Código', 'nome' => 'Produto'],
            [],
        );

        $produto = (new Produto)->forceFill([
            'codigo_interno' => '=HYPERLINK("https://example.test")',
            'nome' => '+SUM(1,1)',
        ]);

        $this->assertSame([
            "'=HYPERLINK(\"https://example.test\")",
            "'+SUM(1,1)",
        ], $exporter($produto));
    }

    public function test_queries_fail_closed_and_sales_keep_the_user_visibility_scope(): void
    {
        auth()->logout();

        $this->assertStringContainsString('1 = 0', ProdutoExporter::modifyQuery(Produto::query())->toSql());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate(PermissoesEnum::ListarVendasOperacao->value, 'web');

        $seller = $this->createUser('seller');
        $seller->givePermissionTo(PermissoesEnum::ListarVendasOperacao->value);
        $this->actingAs($seller);
        $this->mock(
            VendaOperacaoService::class,
            fn (MockInterface $mock) => $mock
                ->shouldReceive('podeVerTodasVendas')
                ->once()
                ->with($seller)
                ->andReturnFalse(),
        );

        $query = VendaOperacaoExporter::modifyQuery(VendaOperacaoPedido::query());

        $this->assertContains($seller->getKey(), $query->getBindings());

        $sql = str_replace(['"', chr(96)], '', $query->toSql());
        $this->assertStringContainsString('user_id = ?', $sql);
    }

    public function test_xlsx_writer_applies_widths_sheet_name_and_frozen_header(): void
    {
        $exporter = new ProdutoExporter(
            (new Export)->forceFill(['id' => 7]),
            ['codigo_interno' => 'Código', 'nome' => 'Produto'],
            [],
        );
        $path = tempnam(sys_get_temp_dir(), 'crm-export-').'.xlsx';
        $writer = new Writer($exporter->getXlsxWriterOptions());

        try {
            $writer->openToFile($path);
            $writer->addRow(Row::fromValues(['Código', 'Produto'], $exporter->getXlsxHeaderCellStyle()));
            $exporter->configureXlsxWriterBeforeClose($writer);
            $writer->close();

            $archive = new ZipArchive;
            $this->assertTrue($archive->open($path));

            $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
            $workbook = $archive->getFromName('xl/workbook.xml');
            $archive->close();

            $this->assertIsString($worksheet);
            $this->assertStringContainsString('ySplit="1"', $worksheet);
            $this->assertStringContainsString('<cols>', $worksheet);
            $this->assertIsString($workbook);
            $this->assertStringContainsString('name="Produtos"', $workbook);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * @return array<class-string<CrmExporter>>
     */
    private function exporterClasses(): array
    {
        return [
            ProdutoExporter::class,
            EstoqueAtualExporter::class,
            ProdutoMovimentacaoExporter::class,
            InsumoMovimentacaoExporter::class,
            VendaOperacaoExporter::class,
        ];
    }

    private function createUser(string $prefix): User
    {
        return User::query()->create([
            'name' => ucfirst($prefix),
            'email' => $prefix.'.'.Str::random(8).'@example.test',
            'email_verified_at' => now(),
            'email_approved' => true,
            'password' => 'password',
        ]);
    }
}
