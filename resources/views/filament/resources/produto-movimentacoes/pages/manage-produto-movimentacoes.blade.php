<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                        Estoque
                    </p>

                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        Movimentacoes de produtos
                    </h2>
                </div>

                <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    Historico consolidado das movimentacoes que alteram o saldo dos produtos.
                </div>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
