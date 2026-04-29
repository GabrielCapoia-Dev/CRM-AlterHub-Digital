@php
    $summary = $summary ?? ['has_insumo' => false, 'message' => 'Selecione um insumo para ver o resumo financeiro.'];
@endphp

<div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
    @if (! ($summary['has_insumo'] ?? false))
        <div class="space-y-1">
            <p class="text-sm font-semibold text-slate-900">Resumo financeiro da movimentacao</p>
            <p class="text-sm text-slate-500">{{ $summary['message'] ?? 'Selecione um insumo para ver o resumo financeiro.' }}</p>
        </div>
    @else
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-1">
                <p class="text-sm font-semibold text-slate-900">Resumo financeiro da movimentacao</p>
                <p class="text-sm text-slate-500">
                    Tipo {{ strtolower($summary['tipo_label']) }} com base no custo atual do insumo.
                </p>
            </div>

            <div class="rounded-xl bg-white px-4 py-3 text-sm text-slate-600 shadow-sm ring-1 ring-slate-200">
                <div><strong class="text-slate-900">Estoque atual:</strong> {{ $summary['estoque_atual'] }}</div>
                <div><strong class="text-slate-900">Quantidade informada:</strong> {{ $summary['quantidade'] }}</div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Valor na origem</p>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $summary['valor_origem'] }}</div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cambio</p>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $summary['cambio'] }}</div>
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Custo efetivo</p>
                <div class="mt-2 text-lg font-semibold text-slate-900">{{ $summary['custo_efetivo'] }}</div>
                <p class="mt-1 text-xs text-slate-500">Valor convertido antes do custo final nacionalizado.</p>
            </div>

            <div class="rounded-xl bg-emerald-50 p-4 shadow-sm ring-1 ring-emerald-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700">Custo final real</p>
                <div class="mt-2 text-lg font-semibold text-emerald-900">{{ $summary['custo_final'] }}</div>
                <p class="mt-1 text-xs text-emerald-700">Valor unitario usado para o impacto financeiro.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Fatores de importacao</p>

                @if (($summary['origem'] ?? 'nacional') !== 'importado')
                    <p class="mt-3 text-sm text-slate-500">Nao se aplica a insumos nacionais.</p>
                @elseif (blank($summary['fatores'] ?? []))
                    <p class="mt-3 text-sm text-slate-500">Nenhum fator adicional cadastrado para este insumo.</p>
                @else
                    <div class="mt-3 space-y-3">
                        @foreach (($summary['fatores'] ?? []) as $factor)
                            <div class="flex items-start justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2">
                                <div>
                                    <div class="text-sm font-semibold text-slate-900">{{ $factor['nome'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $factor['tipo'] }}</div>
                                </div>

                                <div class="text-sm font-semibold text-slate-900">{{ $factor['valor'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Impacto financeiro</p>
                <div class="mt-3 text-sm text-slate-500">Quantidade considerada financeiramente</div>
                <div class="mt-1 text-base font-semibold text-slate-900">{{ $summary['quantidade_financeira'] }}</div>
                <div class="mt-4 text-sm text-slate-500">Formula</div>
                <div class="mt-1 text-base font-semibold text-slate-900">{{ $summary['impacto_formula'] }}</div>
                <div class="mt-4 text-sm text-slate-500">Valor total final</div>
                <div class="mt-1 text-2xl font-semibold text-slate-950">{{ $summary['impacto_total'] }}</div>
            </div>
        </div>
    @endif
</div>
