<?php

namespace App\Services\Empresas;

use App\Models\Empresas\Fornecedor;
use App\Support\Fiscal\TaxIdentifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * FornecedorService
 *
 * Centraliza toda a lógica de consulta/manipulação de Fornecedores,
 * mantendo as classes de página e controller enxutas.
 */
class FornecedorService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Leitura / Listagem
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Retorna uma página paginada de fornecedores com filtros e ordenação.
     *
     * @param  string|null  $search     Texto livre (razão social, CNPJ ou código interno)
     * @param  int|null     $categoria  ID de CategoriaFornecimento
     * @param  int|null     $status     ID de StatusHomologacao
     * @param  string       $sortCol    Coluna de ordenação
     * @param  string       $sortDir    'asc' | 'desc'
     * @param  int          $perPage    Itens por página
     */
    public function listar(
        ?string $search    = null,
        ?int    $categoria = null,
        ?int    $status    = null,
        string  $sortCol   = 'razao_social',
        string  $sortDir   = 'asc',
        int     $perPage   = 15,
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->when($search,    fn (Builder $q) => $this->aplicarBusca($q, $search))
            ->when($categoria, fn (Builder $q) => $q->where('id_categoria_fornecimento', $categoria))
            ->when($status,    fn (Builder $q) => $q->where('id_status_homologacao', $status))
            ->orderBy($this->colunaSegura($sortCol), $this->direcaoSegura($sortDir))
            ->paginate($perPage);
    }

    /**
     * Busca simples sem paginação — útil para selects/autocomplete.
     *
     * @param  string|null  $search
     * @param  int          $limit
     * @return Collection<int, Fornecedor>
     */
    public function buscar(?string $search = null, int $limit = 30): Collection
    {
        return $this->baseQuery()
            ->when($search, fn (Builder $q) => $this->aplicarBusca($q, $search))
            ->orderBy('razao_social')
            ->limit($limit)
            ->get();
    }

    /**
     * Retorna um único fornecedor pelo UUID, com relacionamentos.
     */
    public function encontrar(string $uuid): Fornecedor
    {
        return Fornecedor::with([
            'categoriaFornecimento',
            'statusHomologacao',
            'prazoPagamento',
            'formaPagamento',
        ])->findOrFail($uuid);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Criação / Atualização
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cria um novo fornecedor a partir dos dados validados.
     *
     * @param  array<string, mixed>  $dados
     */
    public function criar(array $dados): Fornecedor
    {
        return Fornecedor::create($dados);
    }

    /**
     * Atualiza um fornecedor existente.
     *
     * @param  string                $uuid
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(string $uuid, array $dados): Fornecedor
    {
        $fornecedor = Fornecedor::findOrFail($uuid);
        $fornecedor->update($dados);

        return $fornecedor->refresh();
    }

    /**
     * Remove um fornecedor pelo UUID.
     */
    public function deletar(string $uuid): void
    {
        Fornecedor::findOrFail($uuid)->delete();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Query base com eager-loading dos relacionamentos usados na listagem.
     */
    private function baseQuery(): Builder
    {
        return Fornecedor::with([
            'categoriaFornecimento',
            'statusHomologacao',
            'prazoPagamento',
            'formaPagamento',
        ]);
    }

    /**
     * Aplica busca em razão social, CNPJ e código interno.
     *
     * A busca é case-insensitive e suporta termos parciais.
     * O CNPJ é normalizado (apenas dígitos) para permitir busca
     * com ou sem formatação.
     */
    private function aplicarBusca(Builder $query, string $termo): Builder
    {
        // Remove formatação do CNPJ caso o usuário tenha digitado com pontos/barras/hífen
        $termoBruto = TaxIdentifier::normalizeForLookup($termo) ?? '';

        return $query->where(function (Builder $q) use ($termo, $termoBruto) {
            $like = "%{$termo}%";

            $q->where('razao_social',    'like', $like)
              ->orWhere('nome_fantasia',  'like', $like)
              ->orWhere('codigo_interno', 'like', $like)
              ->orWhere('cnpj',           'like', $like);

            // Se o termo tem apenas dígitos, busca também no CNPJ sem formatação
            if ($termoBruto !== '') {
                $q->orWhereRaw(
                    TaxIdentifier::comparableExpression('cnpj') . ' LIKE ?',
                    ["%{$termoBruto}%"]
                );
            }
        });
    }

    /**
     * Garante que apenas colunas permitidas sejam usadas na ordenação
     * (prevenção de SQL injection via query string).
     */
    private function colunaSegura(string $col): string
    {
        $permitidas = [
            'codigo_interno',
            'razao_social',
            'cnpj',
            'created_at',
            'updated_at',
        ];

        return in_array($col, $permitidas, true) ? $col : 'razao_social';
    }

    /**
     * Garante que apenas 'asc' ou 'desc' sejam aceitos.
     */
    private function direcaoSegura(string $dir): string
    {
        return strtolower($dir) === 'desc' ? 'desc' : 'asc';
    }
}
