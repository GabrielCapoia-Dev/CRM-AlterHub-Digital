<x-filament-panels::page>
    <style>
        .insumo-modal-window {
            border-radius: 1.5rem;
            overflow: visible;
            border: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.18);
        }

        .insumo-modal-window .fi-modal-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            background:
                linear-gradient(180deg, rgba(248, 250, 252, 0.98), rgba(255, 255, 255, 0.98)),
                linear-gradient(135deg, rgba(22, 78, 99, 0.06), rgba(59, 130, 246, 0.08));
        }

        .insumo-modal-window .fi-modal-heading {
            font-size: 1.125rem;
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .insumo-modal-window .fi-modal-description {
            max-width: 70ch;
            color: rgb(71 85 105);
        }

        .insumo-modal-window .fi-modal-content {
            padding: 1.25rem 1.5rem 1.5rem;
            overflow: visible;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 24%),
                linear-gradient(180deg, rgba(248, 250, 252, 0.9), rgba(255, 255, 255, 1));
        }

        .insumo-modal-window .fi-modal-footer {
            padding: 1rem 1.5rem 1.25rem;
            border-top: 1px solid rgba(226, 232, 240, 0.9);
            background: rgba(255, 255, 255, 0.98);
        }

        .insumo-modal-window .fi-modal-footer-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
        }

        .insumo-modal-window .insumo-footer-delete {
            margin-right: auto;
        }

        .insumo-modal-window .fi-sc-component > .fi-section,
        .insumo-modal-window .fi-sc-component > .fi-section-content-ctn > .fi-section {
            position: relative;
            overflow: visible;
            border-radius: 1.25rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            box-shadow: 0 14px 32px rgba(15, 23, 42, 0.05);
            background: rgba(255, 255, 255, 0.96);
        }

        .insumo-modal-window .insumo-subsection {
            border-radius: 1rem;
            background: rgb(248 250 252);
        }

        .insumo-modal-window .fi-fo-field-wrp-helper-text {
            color: rgb(100 116 139);
        }

        .insumo-modal-window .fi-fo-select-wrp,
        .insumo-modal-window .fi-fo-select,
        .insumo-modal-window .fi-select-input,
        .insumo-modal-window .fi-select-input-ctn {
            position: relative;
            overflow: visible;
        }

        .insumo-modal-window .fi-fo-select-wrp:focus-within,
        .insumo-modal-window .insumo-existing-factor-select,
        .insumo-modal-window .fi-select-input-ctn:focus-within {
            z-index: 80;
        }

        .insumo-modal-window .fi-dropdown-panel {
            z-index: 9999;
            max-height: min(18rem, calc(100vh - 7rem));
            overflow-y: auto;
            overscroll-behavior: contain;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
        }

        .insumo-modal-window .fi-dropdown-list,
        .insumo-modal-window .fi-select-input-options-ctn {
            max-height: none;
        }

        .insumo-modal-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            margin-bottom: 1rem;
            border-radius: 999px;
            border: 1px solid rgba(37, 99, 235, 0.16);
            background: rgba(239, 246, 255, 0.92);
            color: rgb(30 64 175);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .insumo-modal-chip__label {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.72rem;
        }

        .insumo-cost-summary {
            display: grid;
            gap: 1rem;
        }

        .insumo-cost-summary__grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .insumo-cost-summary__card,
        .insumo-cost-summary__final,
        .insumo-history__card {
            border-radius: 1rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            background: rgba(248, 250, 252, 0.95);
            padding: 1rem;
        }

        .insumo-cost-summary__label,
        .insumo-history__label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            color: rgb(51 65 85);
            margin-bottom: 0.5rem;
        }

        .insumo-cost-summary__output,
        .insumo-history__value {
            font-size: 1rem;
            font-weight: 700;
            color: rgb(15 23 42);
        }

        .insumo-cost-summary__highlight {
            font-size: 1.35rem;
            font-weight: 800;
            color: rgb(15 23 42);
        }

        .insumo-cost-summary__hint,
        .insumo-history__hint {
            margin-top: 0.5rem;
            margin-bottom: 0;
            font-size: 0.8rem;
            color: rgb(100 116 139);
        }

        .insumo-cost-summary__factors,
        .insumo-history__log {
            display: grid;
            gap: 0.65rem;
            min-height: 5.5rem;
            padding: 0.85rem;
            border-radius: 0.9rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            background: rgba(255, 255, 255, 0.92);
        }

        .insumo-cost-summary__factor {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 1rem;
            font-size: 0.9rem;
            color: rgb(30 41 59);
        }

        .insumo-cost-summary__factor span {
            display: block;
            margin-top: 0.15rem;
            font-size: 0.75rem;
            color: rgb(100 116 139);
        }

        .insumo-cost-summary__empty,
        .insumo-history__empty {
            color: rgb(100 116 139);
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .insumo-history {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .insumo-modal-window .fi-fo-table-repeater {
            border-radius: 1rem;
            border: 1px solid rgba(226, 232, 240, 0.92);
            background: rgba(255, 255, 255, 0.98);
            overflow: hidden;
        }

        .insumo-modal-window .fi-fo-table-repeater table {
            width: 100%;
        }

        .insumo-modal-window .fi-fo-table-repeater-add {
            padding: 0.9rem 1rem 1rem;
            border-top: 1px solid rgba(226, 232, 240, 0.92);
            background: rgba(248, 250, 252, 0.92);
        }

        .insumo-modal-window .insumo-existing-factor-picker-trigger {
            white-space: nowrap;
        }

        @media (max-width: 1024px) {
            .insumo-cost-summary__grid,
            .insumo-history {
                grid-template-columns: minmax(0, 1fr);
            }
        }
    </style>
    <script>
        (() => {
            if (window.__insumoInlineFactorPickerBound) {
                return;
            }

            window.__insumoInlineFactorPickerBound = true;

            window.addEventListener('insumo-existing-factor-picker-opened', () => {
                requestAnimationFrame(() => {
                    const trigger = document.querySelector('.insumo-existing-factor-select .fi-select-input-btn, .insumo-existing-factor-select select');

                    if (!(trigger instanceof HTMLElement)) {
                        return;
                    }

                    trigger.focus();

                    if (trigger.classList.contains('fi-select-input-btn')) {
                        trigger.click();
                    }
                });
            });

            window.addEventListener('insumo-existing-factor-selected', () => {
                requestAnimationFrame(() => {
                    const trigger = document.querySelector('.insumo-existing-factor-picker-trigger');

                    if (trigger instanceof HTMLElement) {
                        trigger.focus();
                    }
                });
            });
        })();
    </script>

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
