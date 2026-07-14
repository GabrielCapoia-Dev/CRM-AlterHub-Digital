<?php

use App\Enum\EtapaTipo;
use App\Enum\RemessaStatus;
use App\Enum\VendaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertLegacyDataIsMigratable();

        Schema::table('produtos', function (Blueprint $table): void {
            $table->decimal('estoque_fisico', 14, 4)->default(0)->after('estoque_minimo');
            $table->decimal('estoque_reservado', 14, 4)->default(0)->after('estoque_fisico');
            $table->index(['status', 'ativo']);
        });

        Schema::table('insumos', function (Blueprint $table): void {
            $table->decimal('estoque_fisico', 14, 4)->default(0)->after('estoque_minimo');
            $table->decimal('estoque_reservado', 14, 4)->default(0)->after('estoque_fisico');
        });

        $this->backfillStockBalances();

        Schema::table('etapas', function (Blueprint $table): void {
            $table->string('tipo', 20)->default(EtapaTipo::Aberta->value)->after('fechamento')->index();
        });

        DB::table('etapas')->orderBy('id')->get()->each(function (object $etapa): void {
            $slug = mb_strtolower((string) ($etapa->slug ?? $etapa->nome ?? ''));
            $tipo = ! $etapa->fechamento
                ? EtapaTipo::Aberta->value
                : (str_contains($slug, 'perd') || str_contains($slug, 'lost')
                    ? EtapaTipo::Perdida->value
                    : EtapaTipo::Ganha->value);

            DB::table('etapas')->where('id', $etapa->id)->update(['tipo' => $tipo]);
        });

        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->unique()->after('codigo');
            $table->string('status', 30)->default(VendaStatus::Rascunho->value)->change();
            $table->unsignedInteger('versao')->default(1)->after('status');
            $table->timestamp('confirmada_em')->nullable()->after('data_venda');
            $table->timestamp('cancelada_em')->nullable()->after('confirmada_em');
            $table->timestamp('concluida_em')->nullable()->after('cancelada_em');
            $table->timestamp('reaberta_em')->nullable()->after('concluida_em');
            $table->decimal('valor_frete_custo', 14, 2)->default(0)->after('lucro_apos_impostos_total');
            $table->decimal('valor_frete_cobrado', 14, 2)->default(0)->after('valor_frete_custo');
            $table->index(['user_id', 'status', 'data_venda'], 'vop_user_status_date_idx');
        });

        Schema::table('produto_movimentacoes', function (Blueprint $table): void {
            $table->string('origem_tipo', 40)->default('manual')->after('tipo');
            $table->unsignedBigInteger('origem_id')->nullable()->after('origem_tipo');
            $table->string('idempotency_key', 120)->nullable()->after('origem_id')->unique();
            $table->foreignId('estorno_de_id')->nullable()->after('idempotency_key')
                ->constrained('produto_movimentacoes')->restrictOnDelete();
            $table->foreignId('estornado_por')->nullable()->after('estorno_de_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('estornada_em')->nullable()->after('estornado_por');
            $table->index(['origem_tipo', 'origem_id']);
            $table->unique('estorno_de_id');
        });

        Schema::table('insumo_movimentacoes', function (Blueprint $table): void {
            $table->string('origem_tipo', 40)->default('manual')->after('tipo');
            $table->unsignedBigInteger('origem_id')->nullable()->after('origem_tipo');
            $table->string('idempotency_key', 120)->nullable()->after('origem_id')->unique();
            $table->foreignId('estorno_de_id')->nullable()->after('idempotency_key')
                ->constrained('insumo_movimentacoes')->restrictOnDelete();
            $table->foreignId('estornado_por')->nullable()->after('estorno_de_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('estornada_em')->nullable()->after('estornado_por');
            $table->index(['origem_tipo', 'origem_id']);
            $table->unique('estorno_de_id');
        });

        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->unique('produto_movimentacao_id');
            $table->index(['venda_operacao_pedido_id', 'produto_id']);
        });

        Schema::create('produto_reservas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_id')->unique()->constrained('vendas_operacao')->restrictOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('quantidade', 14, 4);
            $table->decimal('quantidade_consumida', 14, 4)->default(0);
            $table->string('status', 20)->default('ativa')->index();
            $table->string('idempotency_key', 120)->unique();
            $table->timestamp('liberada_em')->nullable();
            $table->timestamp('consumida_em')->nullable();
            $table->timestamps();
            $table->index(['produto_id', 'status']);
        });

        Schema::create('venda_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_pedido_id')->constrained('venda_operacao_pedidos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evento', 50)->index();
            $table->string('status_anterior', 30)->nullable();
            $table->string('status_novo', 30)->nullable();
            $table->text('justificativa')->nullable();
            $table->json('metadados')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['venda_operacao_pedido_id', 'created_at'], 'venda_history_date_idx');
        });

        Schema::create('remessas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_pedido_id')->constrained('venda_operacao_pedidos')->restrictOnDelete();
            $table->foreignId('transportadora_id')->nullable()->constrained('transportadoras')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('codigo')->nullable()->unique();
            $table->string('status', 30)->default(RemessaStatus::Rascunho->value)->index();
            $table->string('modalidade_entrega', 30)->default('transportadora');
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->decimal('valor_frete_custo', 14, 2)->default(0);
            $table->decimal('valor_frete_cobrado', 14, 2)->default(0);
            $table->string('codigo_rastreio')->nullable();
            $table->timestamp('despachada_em')->nullable();
            $table->timestamp('entregue_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->index(['venda_operacao_pedido_id', 'status']);
        });

        Schema::create('remessa_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('remessa_id')->constrained('remessas')->cascadeOnDelete();
            $table->foreignId('venda_operacao_id')->constrained('vendas_operacao')->restrictOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->foreignId('produto_movimentacao_id')->nullable()->unique()
                ->constrained('produto_movimentacoes')->restrictOnDelete();
            $table->decimal('quantidade', 14, 4);
            $table->decimal('quantidade_devolvida', 14, 4)->default(0);
            $table->timestamps();
            $table->unique(['remessa_id', 'venda_operacao_id']);
        });

        Schema::create('venda_devolucoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_pedido_id')->constrained('venda_operacao_pedidos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('codigo')->nullable()->unique();
            $table->string('idempotency_key', 120)->unique();
            $table->text('motivo');
            $table->timestamp('recebida_em');
            $table->timestamps();
        });

        Schema::create('venda_devolucao_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_devolucao_id')->constrained('venda_devolucoes')->cascadeOnDelete();
            $table->foreignId('remessa_item_id')->constrained('remessa_itens')->restrictOnDelete();
            $table->foreignId('produto_movimentacao_id')->unique()
                ->constrained('produto_movimentacoes')->restrictOnDelete();
            $table->decimal('quantidade', 14, 4);
            $table->timestamps();
        });

        $this->migrateLegacySales();
    }

    public function down(): void
    {
        Schema::dropIfExists('venda_devolucao_itens');
        Schema::dropIfExists('venda_devolucoes');
        Schema::dropIfExists('remessa_itens');
        Schema::dropIfExists('remessas');
        Schema::dropIfExists('venda_historicos');
        Schema::dropIfExists('produto_reservas');

        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->dropUnique(['produto_movimentacao_id']);
            $table->dropIndex(['venda_operacao_pedido_id', 'produto_id']);
        });

        foreach (['produto_movimentacoes', 'insumo_movimentacoes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['estorno_de_id']);
                $table->dropForeign(['estornado_por']);
                $table->dropUnique(['estorno_de_id']);
                $table->dropUnique(['idempotency_key']);
                $table->dropIndex(['origem_tipo', 'origem_id']);
                $table->dropColumn([
                    'origem_tipo',
                    'origem_id',
                    'idempotency_key',
                    'estorno_de_id',
                    'estornado_por',
                    'estornada_em',
                ]);
            });
        }

        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->dropIndex('vop_user_status_date_idx');
            $table->dropUnique(['idempotency_key']);
            $table->string('status', 30)->default('ativa')->change();
            $table->dropColumn([
                'idempotency_key', 'versao', 'confirmada_em', 'cancelada_em',
                'concluida_em', 'reaberta_em', 'valor_frete_custo', 'valor_frete_cobrado',
            ]);
        });

        Schema::table('etapas', function (Blueprint $table): void {
            $table->dropIndex(['tipo']);
            $table->dropColumn('tipo');
        });
        Schema::table('insumos', fn (Blueprint $table) => $table->dropColumn(['estoque_fisico', 'estoque_reservado']));
        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropIndex(['status', 'ativo']);
            $table->dropColumn(['estoque_fisico', 'estoque_reservado']);
        });
    }

    private function backfillStockBalances(): void
    {
        DB::table('produtos')->orderBy('id')->get(['id'])->each(function (object $produto): void {
            $saldo = (float) DB::table('produto_movimentacoes')
                ->where('produto_id', $produto->id)
                ->sum('impacto_estoque');
            DB::table('produtos')->where('id', $produto->id)->update(['estoque_fisico' => $saldo]);
        });

        DB::table('insumos')->orderBy('id')->get(['id'])->each(function (object $insumo): void {
            $saldo = (float) DB::table('insumo_movimentacoes')
                ->where('insumo_id', $insumo->id)
                ->sum('impacto_estoque');
            DB::table('insumos')->where('id', $insumo->id)->update(['estoque_fisico' => $saldo]);
        });
    }

    private function assertLegacyDataIsMigratable(): void
    {
        if (DB::table('vendas_operacao')
            ->select('produto_movimentacao_id')
            ->whereNotNull('produto_movimentacao_id')
            ->groupBy('produto_movimentacao_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException('Existem linhas de venda compartilhando a mesma movimentacao de estoque. Corrija antes de migrar.');
        }

        $linhaComBaixaInconsistente = DB::table('vendas_operacao')
            ->join(
                'produto_movimentacoes',
                'produto_movimentacoes.id',
                '=',
                'vendas_operacao.produto_movimentacao_id',
            )
            ->whereNotNull('vendas_operacao.produto_movimentacao_id')
            ->where(function ($query): void {
                $query
                    ->whereNull('vendas_operacao.produto_id')
                    ->orWhereColumn(
                        'vendas_operacao.produto_id',
                        '!=',
                        'produto_movimentacoes.produto_id',
                    )
                    ->orWhere('vendas_operacao.quantidade', '<=', 0);
            })
            ->value('vendas_operacao.id');

        if ($linhaComBaixaInconsistente) {
            throw new RuntimeException(
                "A linha de venda legada {$linhaComBaixaInconsistente} possui produto, quantidade ou baixa de estoque inconsistente. Corrija antes de migrar.",
            );
        }

        $pedidoInconsistente = DB::table('vendas_operacao')
            ->join('venda_operacao_pedidos', 'venda_operacao_pedidos.id', '=', 'vendas_operacao.venda_operacao_pedido_id')
            ->where('venda_operacao_pedidos.status', 'ativa')
            ->groupBy('vendas_operacao.venda_operacao_pedido_id')
            ->havingRaw('SUM(CASE WHEN produto_movimentacao_id IS NULL THEN 1 ELSE 0 END) > 0')
            ->havingRaw('SUM(CASE WHEN produto_movimentacao_id IS NOT NULL THEN 1 ELSE 0 END) > 0')
            ->value('vendas_operacao.venda_operacao_pedido_id');

        if ($pedidoInconsistente) {
            throw new RuntimeException(
                "A venda legada {$pedidoInconsistente} possui baixa apenas em parte das linhas. Corrija antes de migrar.",
            );
        }
    }

    private function migrateLegacySales(): void
    {
        DB::table('venda_operacao_pedidos')
            ->where('status', 'ativa')
            ->orderBy('id')
            ->get()
            ->each(function (object $pedido): void {
                $hasMovement = DB::table('vendas_operacao')
                    ->where('venda_operacao_pedido_id', $pedido->id)
                    ->whereNotNull('produto_movimentacao_id')
                    ->exists();

                DB::table('venda_operacao_pedidos')->where('id', $pedido->id)->update([
                    'status' => $hasMovement ? VendaStatus::Despachada->value : VendaStatus::Confirmada->value,
                    'confirmada_em' => $pedido->created_at ?? now(),
                ]);

                if (! $hasMovement) {
                    $this->migrateLegacyReservation($pedido);

                    return;
                }

                $remessaId = DB::table('remessas')->insertGetId([
                    'venda_operacao_pedido_id' => $pedido->id,
                    'codigo' => sprintf('REM-LEG-%05d', $pedido->id),
                    'status' => RemessaStatus::Despachada->value,
                    'modalidade_entrega' => 'transportadora',
                    'despachada_em' => $pedido->created_at ?? now(),
                    'observacao' => 'Remessa criada automaticamente para preservar baixa de estoque legada.',
                    'created_at' => $pedido->created_at ?? now(),
                    'updated_at' => now(),
                ]);

                DB::table('vendas_operacao')
                    ->where('venda_operacao_pedido_id', $pedido->id)
                    ->whereNotNull('produto_movimentacao_id')
                    ->orderBy('id')
                    ->get()
                    ->each(function (object $linha) use ($remessaId): void {
                        DB::table('remessa_itens')->insert([
                            'remessa_id' => $remessaId,
                            'venda_operacao_id' => $linha->id,
                            'produto_id' => $linha->produto_id,
                            'produto_movimentacao_id' => $linha->produto_movimentacao_id,
                            'quantidade' => $linha->quantidade,
                            'quantidade_devolvida' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        DB::table('produto_movimentacoes')
                            ->where('id', $linha->produto_movimentacao_id)
                            ->update([
                                'origem_tipo' => 'remessa_legada',
                                'origem_id' => $remessaId,
                            ]);
                    });
            });
    }

    private function migrateLegacyReservation(object $pedido): void
    {
        $linhas = DB::table('vendas_operacao')
            ->where('venda_operacao_pedido_id', $pedido->id)
            ->orderBy('produto_id')
            ->orderBy('id')
            ->get();

        if ($linhas->isEmpty()) {
            DB::table('venda_operacao_pedidos')->where('id', $pedido->id)->update([
                'status' => VendaStatus::Rascunho->value,
                'confirmada_em' => null,
            ]);
            DB::table('venda_historicos')->insert([
                'venda_operacao_pedido_id' => $pedido->id,
                'evento' => 'migracao_bloqueada',
                'status_anterior' => 'ativa',
                'status_novo' => VendaStatus::Rascunho->value,
                'justificativa' => 'Venda legada sem itens. Revise os dados antes de confirmar.',
                'created_at' => now(),
            ]);

            return;
        }

        $linhaInvalida = $linhas->first(
            fn (object $linha): bool => $linha->produto_id === null
                || round((float) $linha->quantidade, 4) <= 0,
        );

        if ($linhaInvalida) {
            DB::table('venda_operacao_pedidos')->where('id', $pedido->id)->update([
                'status' => VendaStatus::Rascunho->value,
                'confirmada_em' => null,
            ]);
            DB::table('venda_historicos')->insert([
                'venda_operacao_pedido_id' => $pedido->id,
                'evento' => 'migracao_bloqueada',
                'status_anterior' => 'ativa',
                'status_novo' => VendaStatus::Rascunho->value,
                'justificativa' => sprintf(
                    'Venda legada com item invalido (%s). Revise produto e quantidade antes de confirmar.',
                    $linhaInvalida->id,
                ),
                'created_at' => now(),
            ]);

            return;
        }

        $necessarioPorProduto = $linhas
            ->groupBy('produto_id')
            ->map(fn ($itens): float => round((float) $itens->sum('quantidade'), 4));

        foreach ($necessarioPorProduto as $produtoId => $necessario) {
            $produto = DB::table('produtos')->where('id', $produtoId)->first();
            $disponivel = $produto
                ? round((float) $produto->estoque_fisico - (float) $produto->estoque_reservado, 4)
                : 0;

            if ($produto && $necessario <= $disponivel) {
                continue;
            }

            DB::table('venda_operacao_pedidos')->where('id', $pedido->id)->update([
                'status' => VendaStatus::Rascunho->value,
                'confirmada_em' => null,
            ]);
            DB::table('venda_historicos')->insert([
                'venda_operacao_pedido_id' => $pedido->id,
                'evento' => 'migracao_bloqueada',
                'status_anterior' => 'ativa',
                'status_novo' => VendaStatus::Rascunho->value,
                'justificativa' => sprintf(
                    'Venda legada sem baixa e sem saldo suficiente para reserva no produto %s. Necessario: %s; disponivel: %s.',
                    $produtoId,
                    $necessario,
                    $disponivel,
                ),
                'created_at' => now(),
            ]);

            return;
        }

        foreach ($linhas as $linha) {
            DB::table('produto_reservas')->insert([
                'venda_operacao_id' => $linha->id,
                'produto_id' => $linha->produto_id,
                'quantidade' => $linha->quantidade,
                'quantidade_consumida' => 0,
                'status' => 'ativa',
                'idempotency_key' => "migracao:reserva:venda:{$pedido->id}:item:{$linha->id}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('produtos')
                ->where('id', $linha->produto_id)
                ->increment('estoque_reservado', $linha->quantidade);
        }
    }
};
