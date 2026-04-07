<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                Abastecimento e custo
            </p>

            <div class="mt-3 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <div class="space-y-2">
                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        Cadastro de insumos com custo efetivo e origem comercial
                    </h2>

                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Gerencie insumos nacionais e importados no mesmo fluxo, com fornecedor preferencial,
                        cambio manual e fatores adicionais de custo antes da nacionalizacao.
                    </p>
                </div>

                <div class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    Use o botao de criar para abrir o formulario completo em modal e mantenha a listagem visivel
                    enquanto ajusta dependencias e custos.
                </div>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
