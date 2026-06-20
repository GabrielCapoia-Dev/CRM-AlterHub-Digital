<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venda_operacao_pedidos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('oportunidade_id')
                ->nullable()
                ->unique()
                ->constrained('oportunidades')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('clientes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('codigo')->nullable()->unique();
            $table->string('status', 30)->default('ativa')->index();
            $table->date('data_venda')->index();
            $table->unsignedSmallInteger('ano_referencia')->index();
            $table->unsignedTinyInteger('mes_referencia')->index();
            $table->string('cliente_nome_snapshot')->nullable()->index();
            $table->string('cliente_documento_snapshot')->nullable();
            $table->string('vendedor_nome_snapshot')->nullable()->index();
            $table->unsignedInteger('itens_count')->default(0);
            $table->decimal('quantidade_total', 12, 4)->default(0);
            $table->decimal('receita_bruta_total', 14, 2)->default(0);
            $table->decimal('receita_liquida_total', 14, 2)->default(0);
            $table->decimal('custo_total_snapshot', 14, 2)->default(0);
            $table->decimal('lucro_bruto_total', 14, 2)->default(0);
            $table->decimal('lucro_apos_impostos_total', 14, 2)->default(0);
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['ano_referencia', 'mes_referencia']);
            $table->index(['status', 'data_venda']);
        });

        Schema::table('vendas_operacao', function (Blueprint $table) {
            $table->foreignId('venda_operacao_pedido_id')
                ->nullable()
                ->after('user_id')
                ->constrained('venda_operacao_pedidos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index(['venda_operacao_pedido_id', 'data_venda']);
        });

        DB::table('vendas_operacao')
            ->orderBy('id')
            ->get()
            ->each(function ($venda): void {
                $pedidoId = DB::table('venda_operacao_pedidos')->insertGetId([
                    'user_id' => $venda->user_id,
                    'status' => 'ativa',
                    'data_venda' => $venda->data_venda,
                    'ano_referencia' => $venda->ano_referencia,
                    'mes_referencia' => $venda->mes_referencia,
                    'cliente_nome_snapshot' => $venda->cliente_nome,
                    'vendedor_nome_snapshot' => $venda->vendedor_nome,
                    'itens_count' => 1,
                    'quantidade_total' => $venda->quantidade,
                    'receita_bruta_total' => $venda->receita_bruta,
                    'receita_liquida_total' => $venda->receita_liquida,
                    'custo_total_snapshot' => $venda->custo_total_snapshot,
                    'lucro_bruto_total' => $venda->lucro_bruto,
                    'lucro_apos_impostos_total' => $venda->lucro_apos_impostos,
                    'observacao' => $venda->observacao,
                    'created_at' => $venda->created_at,
                    'updated_at' => $venda->updated_at,
                ]);

                DB::table('venda_operacao_pedidos')
                    ->where('id', $pedidoId)
                    ->update(['codigo' => sprintf('VOP-%05d', $pedidoId)]);

                DB::table('vendas_operacao')
                    ->where('id', $venda->id)
                    ->update(['venda_operacao_pedido_id' => $pedidoId]);
            });

        Schema::table('oportunidade_produtos', function (Blueprint $table) {
            $table->decimal('quantidade', 12, 4)
                ->default(1)
                ->after('produto_id');
        });

        Schema::table('oportunidades', function (Blueprint $table) {
            $table->foreignId('venda_operacao_pedido_id')
                ->nullable()
                ->after('user_id')
                ->constrained('venda_operacao_pedidos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('convertida_em')
                ->nullable()
                ->after('venda_operacao_pedido_id');
        });
    }

    public function down(): void
    {
        Schema::table('oportunidades', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_operacao_pedido_id');
            $table->dropColumn('convertida_em');
        });

        Schema::table('oportunidade_produtos', function (Blueprint $table) {
            $table->dropColumn('quantidade');
        });

        Schema::table('vendas_operacao', function (Blueprint $table) {
            $table->dropIndex(['venda_operacao_pedido_id', 'data_venda']);
            $table->dropConstrainedForeignId('venda_operacao_pedido_id');
        });

        Schema::dropIfExists('venda_operacao_pedidos');
    }
};
