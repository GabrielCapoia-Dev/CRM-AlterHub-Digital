<?php

namespace App\Filament\Pages\Fornecedor;

use App\Models\Categorias\CategoriaFornecimento;
use App\Models\Empresas\Fornecedor;
use App\Models\Status\StatusHomologacao;
use App\Services\FornecedorService;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Página Filament: Gestão de Fornecedores
 *
 * Responsabilidades:
 *  - Renderizar a view da listagem
 *  - Manter estado dos filtros / busca / ordenação / paginação via Livewire
 *  - Delegar consultas ao FornecedorService
 *  - Expor helpers de status para a view
 */
class FornecedorList extends Page
{
    // ─────────────────────────────────────────────────────────────────────────
    // Config da página Filament
    // ─────────────────────────────────────────────────────────────────────────

    protected static string|BackedEnum|null $navigationIcon  = Heroicon::BuildingOffice2;
    protected static ?string $navigationLabel = 'Fornecedores';
    protected static ?int    $navigationSort  = 10;
    protected string  $view = 'filament.pages.fornecedor-list';

    // ─────────────────────────────────────────────────────────────────────────
    // Propriedades reativas (Livewire)
    // ─────────────────────────────────────────────────────────────────────────

    /** Termo livre: razão social, CNPJ ou código interno */
    public string $search          = '';

    /** ID de CategoriaFornecimento (0 = todos) */
    public int|string $filtroCategoria = '';

    /** ID de StatusHomologacao (0 = todos) */
    public int|string $filtroStatus    = '';

    /** Coluna que está sendo ordenada */
    public string $sortCol  = 'razao_social';

    /** Direção da ordenação: 'asc' | 'desc' */
    public string $sortDir  = 'asc';

    /** Itens por página */
    public int $perPage = 15;

    // ─────────────────────────────────────────────────────────────────────────
    // Query string — persiste filtros na URL
    // ─────────────────────────────────────────────────────────────────────────

    protected $queryString = [
        'search'          => ['except' => ''],
        'filtroCategoria' => ['except' => ''],
        'filtroStatus'    => ['except' => ''],
        'sortCol'         => ['except' => 'razao_social'],
        'sortDir'         => ['except' => 'asc'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Computed: dados para a view
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna a página atual de fornecedores.
     * Propriedade computada chamada automaticamente na view via $fornecedores.
     */
    public function getFornecedoresProperty(): LengthAwarePaginator
    {
        return app(FornecedorService::class)->listar(
            search: $this->search,
            categoria: $this->filtroCategoria ?: null,
            status: $this->filtroStatus    ?: null,
            sortCol: $this->sortCol,
            sortDir: $this->sortDir,
            perPage: $this->perPage,
        );
    }

    /** Todas as categorias para popular o <select> de filtro. */
    public function getCategoriasProperty()
    {
        return CategoriaFornecimento::orderBy('nome')->get();
    }

    /** Todos os status para popular o <select> de filtro. */
    public function getStatusListProperty()
    {
        return StatusHomologacao::orderBy('nome')->get();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Ações da view
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Alterna ordenação: se clicar na mesma coluna, inverte a direção;
     * caso contrário, inicia ordenação ascendente pela nova coluna.
     */
    public function sortBy(string $column): void
    {
        $allowed = ['codigo_interno', 'razao_social', 'cnpj', 'created_at'];

        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortCol === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortCol = $column;
            $this->sortDir = 'asc';
        }
    }

    /** Reseta todos os filtros e a busca livre. */
    public function limparFiltros(): void
    {
        $this->reset(['search', 'filtroCategoria', 'filtroStatus']);
        $this->resetPage();
    }

    /**
     * Abre o modal de criação (uuid = null) ou edição (uuid = string).
     * Dispara evento para o componente de modal ouvir via Alpine / Livewire.
     */
    public function openModal(?string $uuid): void
    {
        $this->dispatch('open-fornecedor-modal', uuid: $uuid);
    }

    /**
     * Dispara evento de confirmação antes de excluir.
     * O modal de confirmação deve chamar deleteFornecedor() ao confirmar.
     */
    public function confirmDelete(string $uuid): void
    {
        $this->dispatch('confirm-delete-fornecedor', uuid: $uuid);
    }

    /** Executa a exclusão após confirmação. */
    public function deleteFornecedor(string $uuid): void
    {
        $fornecedor = Fornecedor::findOrFail($uuid);
        $fornecedor->delete();

        $this->dispatch('notify', [
            'type'    => 'success',
            'message' => 'Fornecedor excluído com sucesso.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Listeners Livewire
    // ─────────────────────────────────────────────────────────────────────────

    protected $listeners = [
        'fornecedor-saved'   => '$refresh',   // recarrega a listagem após salvar
        'delete-confirmed'   => 'deleteFornecedor',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Reset de página ao mudar filtros
    // ─────────────────────────────────────────────────────────────────────────

    public function updatedSearch(): void
    {
        $this->resetPage();
    }
    public function updatedFiltroCategoria(): void
    {
        $this->resetPage();
    }
    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dados passados para a view
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Injetar variáveis extras na view além das propriedades públicas.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'fornecedores' => $this->fornecedores,
            'categorias'   => $this->categorias,
            'statusList'   => $this->statusList,
        ];
    }
}
