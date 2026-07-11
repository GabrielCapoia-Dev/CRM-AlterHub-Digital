<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_operacao_pedidos', function (Blueprint $table) {
            $table->foreignId('aprovado_por')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('aprovado_em')
                ->nullable()
                ->after('aprovado_por');

            $table->text('motivo_recusa')
                ->nullable()
                ->after('observacao');

            $table->foreignId('origem_pedido_id')
                ->nullable()
                ->after('oportunidade_id')
                ->constrained('venda_operacao_pedidos')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        Schema::table('vendas_operacao', function (Blueprint $table) {
            $table->decimal('preco_tabela_snapshot', 14, 2)
                ->nullable()
                ->after('preco_unitario');

            $table->decimal('preco_minimo_snapshot', 14, 2)
                ->nullable()
                ->after('preco_tabela_snapshot');

            $table->decimal('desconto_percentual', 8, 2)
                ->default(0)
                ->after('preco_minimo_snapshot');

            $table->boolean('desconto_requer_aprovacao')
                ->default(false)
                ->after('desconto_percentual');

            $table->foreignId('desconto_aprovado_por')
                ->nullable()
                ->after('desconto_requer_aprovacao')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamp('desconto_aprovado_em')
                ->nullable()
                ->after('desconto_aprovado_por');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_operacao', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desconto_aprovado_por');
            $table->dropColumn([
                'preco_tabela_snapshot',
                'preco_minimo_snapshot',
                'desconto_percentual',
                'desconto_requer_aprovacao',
                'desconto_aprovado_em',
            ]);
        });

        Schema::table('venda_operacao_pedidos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('aprovado_por');
            $table->dropConstrainedForeignId('origem_pedido_id');
            $table->dropColumn([
                'aprovado_em',
                'motivo_recusa',
            ]);
        });
    }
};
