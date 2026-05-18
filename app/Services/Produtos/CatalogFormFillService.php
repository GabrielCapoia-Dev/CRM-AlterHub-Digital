<?php

namespace App\Services\Produtos;

use App\Models\Categorias\CategoriaProduto;
use App\Models\Categorias\TipoArmazenamento;
use App\Models\Categorias\TipoInsumo;
use App\Models\Categorias\TipoUnidadeMedida;
use App\Models\Empresas\Fornecedor;
use App\Models\Produtos\Insumo;
use App\Models\Status\StatusInsumo;
use DomainException;
use Illuminate\Support\Collection;

class CatalogFormFillService
{
    public function __construct(
        protected ProdutoPricingCalculator $produtoPricingCalculator,
    ) {
    }

    public function insumo(): array
    {
        $timestamp = now()->format('Ymd-His');

        return [
            'nome' => "Insumo Fill {$timestamp}",
            'origem' => 'importado',
            'descricao' => 'Exemplo preenchido automaticamente para acelerar homologacoes e testes de fluxo no cadastro.',
            'tipo_insumo_id' => $this->requiredValue(
                TipoInsumo::query()->orderBy('nome')->value('id'),
                'Cadastre ao menos um tipo de insumo antes de usar o fill.',
            ),
            'tipo_unidade_medida_id' => $this->requiredValue(
                TipoUnidadeMedida::query()->orderBy('nome')->value('id'),
                'Cadastre ao menos uma unidade de medida antes de usar o fill.',
            ),
            'tipo_armazenamento_id' => TipoArmazenamento::query()->orderBy('nome')->value('id'),
            'status_insumo_id' => $this->requiredValue(
                StatusInsumo::query()
                    ->where('nome', 'like', '%registro%')
                    ->value('id')
                    ?? StatusInsumo::query()->orderBy('id')->value('id'),
                'Cadastre ao menos um status de insumo antes de usar o fill.',
            ),
            'ncm' => '38221990',
            'estoque_minimo' => 24,
            'fornecedor_id' => $this->requiredValue(
                Fornecedor::query()->orderBy('razao_social')->value('uuid'),
                'Cadastre ao menos um fornecedor antes de usar o fill.',
            ),
            'custo_moeda_origem' => 18.75,
            'taxa_cambio' => 5.42,
            'moeda_origem' => 'USD',
            'insumoFatoresCusto' => [
                [
                    'nome' => 'Frete internacional',
                    'tipo' => 'valor_fixo_brl',
                    'valor' => 14.5,
                    'ordem' => 0,
                ],
                [
                    'nome' => 'Imposto de importacao',
                    'tipo' => 'percentual',
                    'valor' => 9.6,
                    'ordem' => 1,
                ],
                [
                    'nome' => 'Desconto comercial',
                    'tipo' => 'percentual',
                    'valor' => 0.97,
                    'ordem' => 2,
                ],
            ],
            'observacao' => 'Fill aplicado automaticamente para validacao interna do formulario.',
            'show_existing_factor_picker' => false,
            'existing_factor_template' => null,
        ];
    }

    public function produto(): array
    {
        $timestamp = now()->format('Ymd-His');
        $produtoInsumos = $this->sampleProdutoInsumos();
        $produtoComponentesCusto = $this->sampleProdutoComponentesCusto();
        $summary = $this->produtoPricingCalculator->summarizeState([
            'produtoInsumos' => $produtoInsumos,
            'produtoComponentesCusto' => $produtoComponentesCusto,
        ], refreshSnapshots: false);

        $precoSugerido = $summary['preco_sugerido'] ?? 0.0;
        $precoTabela = round(max($precoSugerido * 1.12, 120), 2);
        $precoMinimo = round($precoTabela * 0.9, 2);
        $unidadePadrao = TipoUnidadeMedida::query()->orderBy('nome')->first();

        return [
            'status' => 'ativo',
            'nome' => "Produto Fill {$timestamp}",
            'categoria_produto_id' => $this->requiredValue(
                CategoriaProduto::query()->orderBy('nome')->value('id'),
                'Cadastre ao menos uma categoria de produto antes de usar o fill.',
            ),
            'marca' => 'AlterHub Labs',
            'unidade_medida' => $this->requiredValue(
                $produtoInsumos[0]['unidade_consumo'] ?? ($unidadePadrao?->sigla ?: $unidadePadrao?->nome),
                'Cadastre ao menos uma unidade de medida antes de usar o fill.',
            ),
            'ncm' => '30029099',
            'estoque_minimo' => 12,
            'preco_tabela' => $precoTabela,
            'preco_minimo' => $precoMinimo,
            'descricao' => 'Produto de exemplo preenchido automaticamente com composicao tecnica e parametros comerciais.',
            'observacao' => 'Fill aplicado automaticamente para validacao do fluxo de precificacao.',
            'produtoInsumos' => $produtoInsumos,
            'produtoComponentesCusto' => $produtoComponentesCusto,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sampleProdutoInsumos(): array
    {
        /** @var Collection<int, Insumo> $insumos */
        $insumos = Insumo::query()
            ->with('tipoUnidadeMedida')
            ->orderBy('nome')
            ->limit(3)
            ->get();

        if ($insumos->isEmpty()) {
            throw new DomainException('Cadastre ao menos um insumo antes de usar o fill do produto.');
        }

        $baseQuantities = [2.5, 1.4, 0.8];

        $lines = $insumos
            ->values()
            ->map(function (Insumo $insumo, int $index) use ($baseQuantities): array {
                return [
                    'insumo_id' => $insumo->id,
                    'quantidade' => $baseQuantities[$index] ?? 1,
                    'unidade_consumo' => $insumo->tipoUnidadeMedida?->sigla
                        ?: $insumo->tipoUnidadeMedida?->nome,
                    'ordem' => $index,
                    'custo_unitario_snapshot' => (float) ($insumo->custo_referencia ?? 0),
                    'custo_total_snapshot' => 0,
                ];
            })
            ->all();

        return $this->produtoPricingCalculator->refreshInsumoSnapshots($lines);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function sampleProdutoComponentesCusto(): array
    {
        return collect($this->produtoPricingCalculator->defaultComponentes())
            ->map(function (array $component): array {
                $component['valor'] = match ($component['nome']) {
                    'COFINS' => 7.6,
                    'PIS' => 1.65,
                    'CSLL' => 1.2,
                    'IR' => 1.2,
                    'IPI' => 3.5,
                    'ICMS' => 12.0,
                    'Comissao' => 4.0,
                    'Frete' => 18.0,
                    'Outras despesas' => 9.5,
                    'Despesas gerais' => 11.0,
                    default => $component['valor'] ?? 0,
                };

                return $component;
            })
            ->values()
            ->all();
    }

    protected function requiredValue(mixed $value, string $message): mixed
    {
        if (blank($value)) {
            throw new DomainException($message);
        }

        return $value;
    }
}
