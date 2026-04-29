@php
    use App\Filament\Resources\InsumoMovimentacoes\InsumoMovimentacaoResource;
    use App\Models\InsumoMovimentacao;
    use App\Support\Ui\NumericFormat;

    $ultimaAtualizacao = $record?->updated_at?->format('d/m/Y H:i') ?? '-';
    $saldoAtual = $record?->estoqueAtual();
    $estoqueMinimo = $record?->estoque_minimo !== null ? (float) $record->estoque_minimo : null;
    $saldoAtualLabel = $saldoAtual === null ? 'Sem historico' : NumericFormat::decimal($saldoAtual);
    $estoqueMinimoLabel = $estoqueMinimo === null ? '-' : NumericFormat::decimal($estoqueMinimo);
    $movements = $record
        ? $record->insumoMovimentacoes()->with('user')->limit(4)->get()
        : collect();
@endphp

<div class="insumo-history">
    <div class="insumo-history__card">
        <span class="insumo-history__label">Ultima atualizacao do insumo</span>
        <div class="insumo-history__value">{{ $ultimaAtualizacao }}</div>
        <p class="insumo-history__hint">Exibida a partir do ultimo salvamento deste cadastro.</p>
    </div>

    <div class="insumo-history__card">
        <span class="insumo-history__label">Saldo atual e alerta</span>
        <div class="insumo-history__value">{{ $saldoAtualLabel }}</div>
        <p class="insumo-history__hint">Estoque minimo configurado: {{ $estoqueMinimoLabel }}</p>

        @if ($record)
            <div class="mt-3 flex flex-wrap items-center gap-3">
                @if ($record->estoqueEstaBaixo())
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                        Estoque baixo
                    </span>
                @elseif ($record->possuiHistoricoEstoque())
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Estoque dentro do limite
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                        Sem movimentacoes registradas
                    </span>
                @endif

                <a
                    href="{{ InsumoMovimentacaoResource::getUrl('index') }}"
                    class="text-sm font-semibold text-primary-600 hover:text-primary-500"
                >
                    Ver tela de movimentacoes
                </a>
            </div>
        @endif
    </div>

    <div class="insumo-history__card" style="grid-column: 1 / -1;">
        <span class="insumo-history__label">Movimentacoes recentes</span>

        <div class="insumo-history__log">
            @if (! $record)
                <span class="insumo-history__empty">Salve o cadastro para ver historico de movimentacoes.</span>
            @elseif ($movements->isEmpty())
                <span class="insumo-history__empty">Nenhuma movimentacao registrada ainda. Use a tela de movimentacoes para alimentar o saldo do insumo.</span>
            @else
                @foreach ($movements as $movement)
                    @php
                        $impacto = (float) $movement->impacto_estoque;
                        $impactoLabel = NumericFormat::decimal($impacto);
                        $impactoClasses = $impacto > 0
                            ? 'text-emerald-700'
                            : ($impacto < 0 ? 'text-rose-700' : 'text-slate-600');
                    @endphp

                    <div class="flex flex-col gap-2 rounded-xl border border-slate-200 bg-white/90 px-4 py-3 md:flex-row md:items-start md:justify-between">
                        <div class="space-y-1">
                            <div class="text-sm font-semibold text-slate-900">
                                {{ InsumoMovimentacao::tipoOptions()[$movement->tipo] ?? $movement->tipo }}
                            </div>

                            <div class="text-xs text-slate-500">
                                {{ $movement->realizado_em?->format('d/m/Y H:i') }}
                                @if ($movement->documento_referencia)
                                    • {{ $movement->documento_referencia }}
                                @endif
                            </div>

                            @if ($movement->motivo || $movement->observacao)
                                <div class="text-sm text-slate-600">
                                    {{ $movement->motivo ?: $movement->observacao }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-1 text-sm md:text-right">
                            <div class="{{ $impactoClasses }}">
                                Impacto: {{ $impacto > 0 ? '+' : '' }}{{ $impactoLabel }}
                            </div>
                            <div class="text-slate-500">
                                Saldo: {{ NumericFormat::decimal((float) $movement->saldo_atual) }}
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
