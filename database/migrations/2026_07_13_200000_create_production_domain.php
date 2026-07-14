<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            $table->enum('classificacao', ['revenda', 'fabricado'])
                ->default('revenda')
                ->after('status')
                ->index();
            $table->enum('origem', ['nacional', 'importado'])
                ->default('nacional')
                ->after('classificacao')
                ->index();
        });

        DB::table('produtos')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('produto_insumos')
                    ->whereColumn('produto_insumos.produto_id', 'produtos.id');
            })
            ->update(['classificacao' => 'fabricado']);

        Schema::create('ordens_producao', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('produto_movimentacao_id')
                ->nullable()
                ->constrained('produto_movimentacoes')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('status', 30)->default('planejada')->index();
            $table->decimal('quantidade_planejada', 12, 4);
            $table->decimal('quantidade_produzida', 12, 4)->default(0);
            $table->string('unidade_snapshot', 50)->nullable();
            $table->string('produto_codigo_snapshot')->nullable();
            $table->string('produto_nome_snapshot');
            $table->decimal('custo_unitario_snapshot', 14, 4)->default(0);
            $table->decimal('custo_total_planejado_snapshot', 14, 4)->default(0);
            $table->date('prevista_para')->nullable()->index();
            $table->timestamp('reservada_em')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['produto_id', 'status']);
        });

        Schema::create('ordens_producao_insumos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ordem_producao_id')
                ->constrained('ordens_producao')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('produto_insumo_id')
                ->nullable()
                ->constrained('produto_insumos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('insumo_id')
                ->constrained('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('insumo_movimentacao_id')
                ->nullable()
                ->constrained('insumo_movimentacoes')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->unsignedInteger('ordem')->default(0);
            $table->string('insumo_codigo_snapshot')->nullable();
            $table->string('insumo_nome_snapshot');
            $table->string('unidade_snapshot', 50)->nullable();
            $table->decimal('quantidade_unitaria_snapshot', 12, 4);
            $table->decimal('quantidade_necessaria_snapshot', 12, 4);
            $table->decimal('quantidade_reservada', 12, 4)->default(0);
            $table->decimal('quantidade_consumida', 12, 4)->default(0);
            $table->decimal('custo_unitario_snapshot', 14, 4)->default(0);
            $table->decimal('custo_total_snapshot', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['ordem_producao_id', 'ordem']);
            $table->index(['insumo_id', 'quantidade_reservada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordens_producao_insumos');
        Schema::dropIfExists('ordens_producao');

        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropIndex(['classificacao']);
            $table->dropIndex(['origem']);
            $table->dropColumn(['classificacao', 'origem']);
        });
    }
};
