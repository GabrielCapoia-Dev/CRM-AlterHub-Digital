<?php

namespace Tests\Feature\Documentos;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\DocumentoConfiguracao;
use App\Models\Produto;
use App\Models\ProdutoMovimentacao;
use App\Models\Romaneio;
use App\Models\RomaneioItem;
use App\Models\RomaneioPedido;
use App\Models\VendaOperacao;
use App\Models\VendaOperacaoLote;
use App\Models\VendaOperacaoPedido;
use App\Models\VendaPedidoFoto;
use App\Services\Documentos\PedidoPdfService;
use App\Services\Documentos\RomaneioPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DocumentosPdfServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Permission::findOrCreate(PermissoesEnum::VisualizarAnexosPedido->value, 'web');
    }

    public function test_document_routes_are_named_and_protected_by_authentication(): void
    {
        foreach ([
            'documentos.pedidos.pdf',
            'documentos.pedidos.fotos.visualizar',
            'documentos.pedidos.fotos.baixar',
            'documentos.romaneios.pdf',
        ] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "A rota {$name} nao foi registrada.");
            $this->assertContains('auth', $route->gatherMiddleware());
        }
    }

    public function test_invalid_company_configuration_blocks_pdf_without_audit_or_stock_changes(): void
    {
        [$pedido, , $user] = $this->criarPedidoConfirmado();
        $this->concederPermissoes($user, [
            PermissoesEnum::ListarVendasOperacao,
            PermissoesEnum::GerarPdfPedido,
        ]);
        $estoqueAntes = $this->snapshotMovimentacoesEstoque();

        DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
            'logo_disk' => 'local',
            'logo_path' => 'documentos/logotipos/inexistente.png',
            'razao_social' => 'Empresa de Teste',
            'cnpj' => '00.000.000/0000-00',
        ]);

        $this->actingAs($user)
            ->get(route('documentos.pedidos.pdf', $pedido))
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        try {
            app(PedidoPdfService::class)->gerar($pedido);
            $this->fail('A configuracao institucional invalida deveria impedir o PDF.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cnpj', $exception->errors());
            $this->assertArrayHasKey('logo_path', $exception->errors());
        }

        $this->assertSame($estoqueAntes, $this->snapshotMovimentacoesEstoque());
        $this->assertDatabaseCount('venda_historicos', 0);
    }

    public function test_pedido_pdf_download_is_streamed_with_binary_pdf_headers(): void
    {
        $this->configurarEmpresa();
        [$pedido, , $user] = $this->criarPedidoConfirmado();
        $this->concederPermissoes($user, [
            PermissoesEnum::ListarVendasOperacao,
            PermissoesEnum::GerarPdfPedido,
        ]);

        $response = $this->actingAs($user)
            ->get(route('documentos.pedidos.pdf', [
                'pedido' => $pedido,
                'download' => 1,
            ]));

        $response
            ->assertOk()
            ->assertStreamed()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Transfer-Encoding', 'binary')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith(
            'attachment;',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_pedido_pdf_renders_lots_and_photos_audits_reprint_and_never_touches_stock(): void
    {
        $this->configurarEmpresa();
        [$pedido, $item, $user] = $this->criarPedidoConfirmado();
        $this->concederPermissoes($user, [
            PermissoesEnum::ListarVendasOperacao,
            PermissoesEnum::VisualizarAnexosPedido,
        ]);

        VendaOperacaoLote::query()->create([
            'venda_operacao_id' => $item->id,
            'user_id' => $user->id,
            'numero_lote' => 'LOTE-ACUCAR-01',
            'quantidade' => 2,
            'ano_fabricacao' => 2026,
            'data_fabricacao' => '2026-06-01',
            'data_validade' => '2027-06-01',
            'observacao' => 'Lote liberado pelo controle de qualidade.',
        ]);

        $fotoArquivo = UploadedFile::fake()->image('mercadoria-separada.png', 120, 90);
        $fotoPath = "pedidos/{$pedido->id}/fotos/mercadoria-separada.png";
        Storage::disk('local')->put($fotoPath, $fotoArquivo->getContent());
        VendaPedidoFoto::query()->create([
            'venda_operacao_pedido_id' => $pedido->id,
            'venda_operacao_id' => $item->id,
            'user_id' => $user->id,
            'disk' => 'local',
            'path' => $fotoPath,
            'nome_original' => 'mercadoria-separada.png',
            'mime_type' => 'image/png',
            'tamanho_bytes' => strlen($fotoArquivo->getContent()),
            'descricao' => 'Volumes conferidos e identificados.',
        ]);

        $estoqueAntes = $this->snapshotMovimentacoesEstoque();
        $service = app(PedidoPdfService::class);
        $primeiraVia = $service->gerar($pedido, $user);
        $segundaVia = $service->gerar($pedido, $user);
        $user->revokePermissionTo(PermissoesEnum::VisualizarAnexosPedido->value);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viaSemPermissaoDeAnexo = $service->gerar($pedido, $user);

        $this->assertStringStartsWith('%PDF-', $primeiraVia->bytes);
        $this->assertStringStartsWith('%PDF-', $segundaVia->bytes);
        $this->assertStringStartsWith('%PDF-', $viaSemPermissaoDeAnexo->bytes);
        $this->assertSame('pedido-vop-90001-cliente-arvore-2026-07-16.pdf', $primeiraVia->filename);
        $this->assertSame($estoqueAntes, $this->snapshotMovimentacoesEstoque());

        $historicos = $pedido->historicos()
            ->whereIn('evento', ['pdf_gerado', 'pdf_reimpresso'])
            ->reorder('id')
            ->get();

        $this->assertSame(['pdf_gerado', 'pdf_reimpresso', 'pdf_reimpresso'], $historicos->pluck('evento')->all());
        $this->assertSame(1, $historicos->first()->metadados['fotos_incluidas']);
        $this->assertSame(1, $historicos->get(1)->metadados['fotos_incluidas']);
        $this->assertSame(0, $historicos->last()->metadados['fotos_incluidas']);
        $this->assertSame($user->id, $historicos->first()->user_id);
    }

    public function test_romaneio_pdf_uses_snapshot_audits_reprint_and_never_touches_stock(): void
    {
        $this->configurarEmpresa(exibirValoresRomaneio: false);
        [$pedido, $item, $user] = $this->criarPedidoConfirmado();

        $romaneio = Romaneio::query()->create([
            'user_id' => $user->id,
            'codigo' => 'ROM-000901',
            'status' => Romaneio::STATUS_ATIVO,
            'gerado_em' => '2026-07-28 15:30:00',
            'observacao' => 'Carga conferida.',
            'total_pedidos' => 1,
            'total_clientes' => 1,
            'total_itens' => 1,
            'quantidade_total' => 2,
            'quantidade_volumes_total' => 3,
            'peso_total_kg' => 5.5,
            'valor_total' => 30,
            'exibir_valores_comerciais' => false,
        ]);
        $romaneioPedido = RomaneioPedido::query()->create([
            'romaneio_id' => $romaneio->id,
            'venda_operacao_pedido_id' => $pedido->id,
            'pedido_ativo_id' => $pedido->id,
            'cliente_id_snapshot' => null,
            'pedido_codigo_snapshot' => $pedido->codigo,
            'pedido_data_snapshot' => $pedido->data_venda,
            'cliente_nome_snapshot' => $pedido->cliente_nome_snapshot,
            'cliente_documento_snapshot' => $pedido->cliente_documento_snapshot,
            'cliente_telefone_snapshot' => $pedido->cliente_telefone_snapshot,
            'cliente_email_snapshot' => $pedido->cliente_email_snapshot,
            'entrega_endereco_snapshot' => $pedido->entrega_endereco_snapshot,
            'vendedor_nome_snapshot' => $pedido->vendedor_nome_snapshot,
            'condicao_pagamento_snapshot' => $pedido->condicao_pagamento_snapshot,
            'observacao_snapshot' => $pedido->observacao,
            'total_itens' => 1,
            'quantidade_total' => 2,
            'quantidade_volumes' => 3,
            'peso_total_kg' => 5.5,
            'valor_total' => 30,
        ]);
        RomaneioItem::query()->create([
            'romaneio_id' => $romaneio->id,
            'romaneio_pedido_id' => $romaneioPedido->id,
            'venda_operacao_id' => $item->id,
            'produto_id' => $item->produto_id,
            'produto_codigo_snapshot' => 'PROD-DOC-01',
            'produto_nome_snapshot' => $item->produto_nome_snapshot,
            'unidade_snapshot' => 'UN',
            'quantidade' => 2,
            'peso_unitario_kg' => 2.75,
            'peso_total_kg' => 5.5,
            'preco_unitario' => 15,
            'subtotal' => 30,
            'lotes_snapshot' => [[
                'numero_lote' => 'LOTE-ROM-01',
                'data_validade' => '2027-06-01',
            ]],
            'observacao_snapshot' => 'Separado sem avarias.',
        ]);

        $estoqueAntes = $this->snapshotMovimentacoesEstoque();
        $service = app(RomaneioPdfService::class);
        $primeiraVia = $service->gerar($romaneio, $user);
        $segundaVia = $service->gerar($romaneio, $user);

        $this->assertStringStartsWith('%PDF-', $primeiraVia->bytes);
        $this->assertStringStartsWith('%PDF-', $segundaVia->bytes);
        $this->assertSame('romaneio-rom-000901-2026-07-28.pdf', $primeiraVia->filename);
        $this->assertSame($estoqueAntes, $this->snapshotMovimentacoesEstoque());

        $historicos = $romaneio->historicos()->reorder('id')->get();

        $this->assertSame(['pdf_gerado', 'pdf_reimpresso'], $historicos->pluck('evento')->all());
        $this->assertFalse($historicos->first()->metadados['exibir_valores']);
        $this->assertSame($user->id, $historicos->first()->user_id);
    }

    protected function configurarEmpresa(bool $exibirValoresRomaneio = true): DocumentoConfiguracao
    {
        $logo = UploadedFile::fake()->image('logo.png', 180, 70);
        $path = 'documentos/logotipos/logo.png';
        Storage::disk('local')->put($path, $logo->getContent());

        return DocumentoConfiguracao::query()->create([
            'chave' => DocumentoConfiguracao::CHAVE_PADRAO,
            'logo_disk' => 'local',
            'logo_path' => $path,
            'razao_social' => 'AlterHub Alimentos Ltda.',
            'cnpj' => '04.252.011/0001-10',
            'nome_comercial' => 'AlterHub',
            'texto_complementar' => 'Produtos para laticínios',
            'exibir_valores_romaneio' => $exibirValoresRomaneio,
        ]);
    }

    /**
     * @return array{VendaOperacaoPedido, VendaOperacao, User}
     */
    protected function criarPedidoConfirmado(): array
    {
        $user = User::factory()->create(['name' => 'Vendedora Ação']);
        $produto = Produto::query()->create([
            'codigo_interno' => 'PROD-DOC-01',
            'nome' => 'Açúcar para fermentação',
            'status' => 'ativo',
            'unidade_medida' => 'un',
            'custo_base_formacao' => 10,
            'preco_tabela' => 15,
            'preco_minimo' => 12,
            'peso_unitario_kg' => 2.75,
        ]);
        $pedido = VendaOperacaoPedido::query()->create([
            'user_id' => $user->id,
            'codigo' => 'VOP-90001',
            'status' => VendaOperacaoPedido::STATUS_CONFIRMADA,
            'data_venda' => '2026-07-16',
            'confirmada_em' => '2026-07-16 10:47:00',
            'cliente_nome_snapshot' => 'Cliente Árvore',
            'cliente_documento_snapshot' => '123.456.789-09',
            'cliente_telefone_snapshot' => '(69) 99963-2935',
            'cliente_email_snapshot' => 'cliente@example.test',
            'cliente_endereco_snapshot' => [
                'logradouro' => 'Rua das Flores',
                'numero' => '57',
                'bairro' => 'Jardim Zona Sul',
                'cidade' => 'Ariquemes',
                'uf' => 'RO',
                'cep' => '76876-813',
            ],
            'entrega_endereco_snapshot' => [
                'logradouro' => 'Rua das Flores',
                'numero' => '57',
                'complemento' => 'Lote 05-F3',
                'bairro' => 'Jardim Zona Sul',
                'cidade' => 'Ariquemes',
                'uf' => 'RO',
                'cep' => '76876-813',
            ],
            'condicao_pagamento_snapshot' => 'Boleto',
            'condicoes_comerciais' => 'Pagamento em 28 dias.',
            'vendedor_nome_snapshot' => 'Vendedora Ação',
            'itens_count' => 1,
            'quantidade_total' => 2,
            'receita_bruta_total' => 30,
            'receita_liquida_total' => 30,
            'custo_total_snapshot' => 20,
            'lucro_bruto_total' => 10,
            'lucro_apos_impostos_total' => 10,
            'valor_frete_cobrado' => 5,
            'observacao' => 'Entregar no periodo da manha.',
        ]);
        $movimentacao = ProdutoMovimentacao::query()->create([
            'produto_id' => $produto->id,
            'user_id' => $user->id,
            'tipo' => 'saida',
            'origem_tipo' => 'venda_confirmada',
            'origem_id' => $pedido->id,
            'idempotency_key' => "teste-documento-pedido-{$pedido->id}",
            'quantidade' => 2,
            'impacto_estoque' => -2,
            'saldo_anterior' => 10,
            'saldo_atual' => 8,
            'unidade' => 'un',
            'realizado_em' => '2026-07-16 10:47:00',
        ]);
        $item = VendaOperacao::query()->create([
            'user_id' => $user->id,
            'venda_operacao_pedido_id' => $pedido->id,
            'produto_id' => $produto->id,
            'produto_movimentacao_id' => $movimentacao->id,
            'produto_codigo_snapshot' => $produto->codigo_interno,
            'produto_nome_snapshot' => $produto->nome,
            'unidade_snapshot' => 'UN',
            'peso_unitario_kg_snapshot' => 2.75,
            'data_venda' => '2026-07-16',
            'quantidade' => 2,
            'preco_unitario' => 15,
            'receita_bruta' => 30,
            'custo_unitario_snapshot' => 10,
            'custo_total_snapshot' => 20,
            'receita_liquida' => 30,
            'lucro_bruto' => 10,
            'lucro_apos_impostos' => 10,
            'cliente_nome' => 'Cliente Árvore',
            'vendedor_nome' => 'Vendedora Ação',
            'observacao' => 'Manter em local seco.',
        ]);

        return [$pedido, $item, $user];
    }

    /** @return list<array<string, mixed>> */
    protected function snapshotMovimentacoesEstoque(): array
    {
        return DB::table('produto_movimentacoes')
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): array => (array) $row)
            ->all();
    }

    /** @param  list<PermissoesEnum>  $permissoes */
    private function concederPermissoes(User $user, array $permissoes): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissoes as $permissao) {
            Permission::findOrCreate($permissao->value, 'web');
        }

        $user->givePermissionTo(array_map(
            fn (PermissoesEnum $permissao): string => $permissao->value,
            $permissoes,
        ));
    }
}
