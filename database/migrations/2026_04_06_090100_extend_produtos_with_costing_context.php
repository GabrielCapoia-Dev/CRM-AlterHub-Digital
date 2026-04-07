<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_produto', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->timestamps();
        });

        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('categoria_produto_id')
                ->nullable()
                ->after('codigo_interno')
                ->constrained('categorias_produto')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('marca')
                ->nullable()
                ->after('nome');

            $table->text('observacao')
                ->nullable()
                ->after('descricao');

            $table->string('status', 30)
                ->default('em_registro')
                ->after('ativo')
                ->index();

            $table->string('ncm', 10)
                ->nullable()
                ->after('unidade_medida')
                ->index();

            $table->decimal('estoque_minimo', 12, 4)
                ->nullable()
                ->after('ncm');

            $table->decimal('preco_minimo', 12, 2)
                ->nullable()
                ->after('preco_tabela');

            $table->decimal('custo_base_formacao', 12, 4)
                ->nullable()
                ->after('preco_minimo');

            $table->decimal('preco_sugerido', 12, 2)
                ->nullable()
                ->after('custo_base_formacao');
        });

        DB::table('produtos')->update([
            'status' => DB::raw("case when ativo = 1 then 'ativo' else 'inativo' end"),
        ]);
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropForeign(['categoria_produto_id']);
            $table->dropColumn([
                'categoria_produto_id',
                'marca',
                'observacao',
                'status',
                'ncm',
                'estoque_minimo',
                'preco_minimo',
                'custo_base_formacao',
                'preco_sugerido',
            ]);
        });

        Schema::dropIfExists('categorias_produto');
    }
};
