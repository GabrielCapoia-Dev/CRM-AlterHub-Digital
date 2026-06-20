@if (! $selectedOpportunity)
    <div class="crm-drawer-empty">Salve a oportunidade para liberar a vinculacao de produtos.</div>
@else
    <div class="crm-drawer-stack">
        <section class="crm-drawer-section">
            <div class="crm-section-heading">
                <h4>Produtos associados</h4>
                <p>Itens do catalogo vinculados a esta oportunidade, com preco negociado e observacoes.</p>
            </div>

            <div class="crm-entity-list">
                @forelse ($selectedOpportunity->oportunidadeProdutos as $linkedProduct)
                    <article class="crm-entity-card" wire:key="product-link-{{ $linkedProduct->id }}">
                        <div>
                            <h4>{{ $linkedProduct->produto?->nome ?? 'Produto removido' }}</h4>

                            @if ($linkedProduct->produto?->codigo_interno)
                                <span class="crm-product-code">{{ $linkedProduct->produto->codigo_interno }}</span>
                            @endif

                            <p>
                                Quantidade: {{ number_format((float) $linkedProduct->quantidade, 4, ',', '.') }}
                                {{ $linkedProduct->produto?->unidade_medida ? ' ' . $linkedProduct->produto->unidade_medida : '' }}
                                |
                                {{ $linkedProduct->preco_negociado ? 'Preco negociado: R$ ' . number_format((float) $linkedProduct->preco_negociado, 2, ',', '.') : 'Sem preco negociado' }}
                            </p>

                            @if ($linkedProduct->observacao)
                                <small>{{ $linkedProduct->observacao }}</small>
                            @endif
                        </div>

                        <div class="crm-entity-actions">
                            @can('update', $linkedProduct)
                                <button type="button" class="crm-btn crm-btn-secondary" wire:click="editProductLink({{ $linkedProduct->id }})">
                                    Editar
                                </button>
                            @endcan

                            @can('delete', $linkedProduct)
                                <button type="button" class="crm-btn crm-btn-danger" wire:click="deleteProductLink({{ $linkedProduct->id }})">
                                    Excluir
                                </button>
                            @endcan
                        </div>
                    </article>
                @empty
                    <div class="crm-drawer-empty">Nenhum produto vinculado a esta oportunidade.</div>
                @endforelse
            </div>
        </section>

        @if (auth()->user()?->can('create', \App\Models\OportunidadeProduto::class) || filled($productForm['id']))
            <form class="crm-drawer-section" wire:submit.prevent="saveProductLink">
                <div class="crm-section-heading">
                    <h4>{{ filled($productForm['id']) ? 'Editar vinculacao' : 'Vincular novo produto' }}</h4>
                    <p>Associe itens do catalogo principal ao contexto desta negociacao.</p>
                </div>

                <div class="crm-drawer-grid">
                    <label class="crm-field crm-field-full">
                        <span>Produto</span>
                        <select wire:model.defer="productForm.produto_id">
                            <option value="">Selecione</option>
                            @foreach ($products as $product)
                                <option value="{{ $product['id'] }}">{{ $product['nome'] }}</option>
                            @endforeach
                        </select>
                        @error('productForm.produto_id')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Preco negociado</span>
                        <input type="number" step="0.01" min="0" wire:model.defer="productForm.preco_negociado">
                        @error('productForm.preco_negociado')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field">
                        <span>Quantidade</span>
                        <input type="number" step="0.0001" min="0.0001" wire:model.defer="productForm.quantidade">
                        @error('productForm.quantidade')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>

                    <label class="crm-field crm-field-full">
                        <span>Observacao</span>
                        <textarea rows="4" wire:model.defer="productForm.observacao"></textarea>
                        @error('productForm.observacao')
                            <small class="crm-field-error">{{ $message }}</small>
                        @enderror
                    </label>
                </div>

                <div class="crm-form-actions">
                    <button type="submit" class="crm-btn crm-btn-primary">
                        {{ filled($productForm['id']) ? 'Salvar produto' : 'Adicionar produto' }}
                    </button>

                    @if (filled($productForm['id']))
                        <button type="button" class="crm-btn crm-btn-secondary" wire:click="resetProductForm">
                            Cancelar edicao
                        </button>
                    @endif
                </div>
            </form>
        @endif
    </div>
@endif
