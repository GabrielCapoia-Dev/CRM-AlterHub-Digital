<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('despesas_operacionais', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('descricao');
            $table->string('categoria', 60)->index();
            $table->string('subcategoria')->nullable();
            $table->string('tipo', 20)->index();
            $table->decimal('valor', 14, 2);
            $table->date('data_competencia')->index();
            $table->unsignedSmallInteger('ano_referencia')->index();
            $table->unsignedTinyInteger('mes_referencia')->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['ano_referencia', 'mes_referencia']);
            $table->index(['produto_id', 'data_competencia']);
        });

        Schema::create('vendas_operacao', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('produto_movimentacao_id')
                ->nullable()
                ->constrained('produto_movimentacoes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('produto_codigo_snapshot')->nullable();
            $table->string('produto_nome_snapshot');
            $table->string('produto_categoria_snapshot')->nullable();
            $table->string('unidade_snapshot', 50)->nullable();
            $table->date('data_venda')->index();
            $table->unsignedSmallInteger('ano_referencia')->index();
            $table->unsignedTinyInteger('mes_referencia')->index();
            $table->decimal('quantidade', 12, 4);
            $table->decimal('preco_unitario', 12, 4);
            $table->decimal('receita_bruta', 14, 2);
            $table->decimal('custo_unitario_snapshot', 12, 4);
            $table->decimal('custo_total_snapshot', 14, 2);
            $table->decimal('icms_aliquota', 6, 2)->default(0);
            $table->decimal('icms_valor', 14, 2)->default(0);
            $table->decimal('outros_impostos_aliquota', 6, 2)->default(0);
            $table->decimal('outros_impostos_valor', 14, 2)->default(0);
            $table->decimal('receita_liquida', 14, 2);
            $table->decimal('lucro_bruto', 14, 2);
            $table->decimal('lucro_apos_impostos', 14, 2);
            $table->string('cliente_nome')->nullable()->index();
            $table->string('vendedor_nome')->nullable()->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['ano_referencia', 'mes_referencia']);
            $table->index(['produto_id', 'data_venda']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_operacao');
        Schema::dropIfExists('despesas_operacionais');
    }
};
