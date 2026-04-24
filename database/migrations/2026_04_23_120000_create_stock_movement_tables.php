<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumo_movimentacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('insumo_id')
                ->constrained('insumos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('tipo', 30)->index();
            $table->decimal('quantidade', 12, 4);
            $table->decimal('impacto_estoque', 12, 4)->default(0);
            $table->decimal('saldo_anterior', 12, 4)->default(0);
            $table->decimal('saldo_atual', 12, 4)->default(0);
            $table->string('unidade', 50)->nullable();
            $table->string('documento_referencia')->nullable();
            $table->string('motivo')->nullable();
            $table->string('origem_destino')->nullable();
            $table->string('destino')->nullable();
            $table->string('lote')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->decimal('valor_unitario', 12, 4)->nullable();
            $table->decimal('valor_total', 12, 4)->nullable();
            $table->text('observacao')->nullable();
            $table->text('observacao_interna')->nullable();
            $table->timestamp('realizado_em')->index();
            $table->timestamps();

            $table->index(['insumo_id', 'realizado_em']);
        });

        Schema::create('produto_movimentacoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('tipo', 30)->index();
            $table->decimal('quantidade', 12, 4);
            $table->decimal('impacto_estoque', 12, 4)->default(0);
            $table->decimal('saldo_anterior', 12, 4)->default(0);
            $table->decimal('saldo_atual', 12, 4)->default(0);
            $table->string('unidade', 50)->nullable();
            $table->string('documento_referencia')->nullable();
            $table->string('motivo')->nullable();
            $table->string('origem_destino')->nullable();
            $table->string('destino')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->decimal('valor_unitario', 12, 4)->nullable();
            $table->decimal('valor_total', 12, 4)->nullable();
            $table->text('observacao')->nullable();
            $table->text('observacao_interna')->nullable();
            $table->timestamp('realizado_em')->index();
            $table->timestamps();

            $table->index(['produto_id', 'realizado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_movimentacoes');
        Schema::dropIfExists('insumo_movimentacoes');
    }
};
