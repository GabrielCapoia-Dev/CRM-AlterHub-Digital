<x-filament-panels::page>
    <style>
        .produto-modal-window {
            border-radius: 1.5rem;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.16);
        }

        .produto-modal-window .fi-modal-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            background:
                linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98)),
                linear-gradient(135deg, rgba(15, 23, 42, 0.04), rgba(59, 130, 246, 0.08));
        }

        .produto-modal-window .fi-modal-content {
            padding: 1.25rem 1.5rem 1.5rem;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 24%),
                linear-gradient(180deg, rgba(248, 250, 252, 0.92), rgba(255, 255, 255, 1));
        }

        .produto-modal-window .fi-modal-footer {
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            background: rgba(255, 255, 255, 0.98);
        }

        .produto-modal-window .fi-sc-component > .fi-section,
        .produto-modal-window .fi-sc-component > .fi-section-content-ctn > .fi-section {
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.05);
            background: rgba(255, 255, 255, 0.96);
        }

        .produto-modal-window .fi-fo-repeater {
            border-radius: 1rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            overflow: hidden;
            background: rgba(255, 255, 255, 0.98);
        }

        .produto-modal-window .fi-fo-repeater table {
            width: 100%;
        }
    </style>

    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                        Estoque
                    </p>

                    <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                        Produtos
                    </h2>
                </div>

                <div class="rounded-xl bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    Catalogo tecnico-comercial com estoque, precos e formacao de custos no mesmo fluxo.
                </div>
            </div>
        </section>

        {{ $this->content }}
    </div>
</x-filament-panels::page>
