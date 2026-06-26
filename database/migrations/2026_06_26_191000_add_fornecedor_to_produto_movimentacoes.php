<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produto_movimentacoes', function (Blueprint $table) {
            $table->uuid('fornecedor_id')
                ->nullable()
                ->after('user_id');

            $table->foreign('fornecedor_id')
                ->references('uuid')
                ->on('fornecedores')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->index('fornecedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('produto_movimentacoes', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropIndex(['fornecedor_id']);
            $table->dropColumn('fornecedor_id');
        });
    }
};
