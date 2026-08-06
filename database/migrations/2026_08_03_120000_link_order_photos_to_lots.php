<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_pedido_fotos', function (Blueprint $table): void {
            $table->foreignId('venda_operacao_lote_id')
                ->nullable()
                ->after('venda_operacao_id')
                ->constrained('venda_operacao_lotes')
                ->restrictOnDelete();
            $table->index(
                ['venda_operacao_lote_id', 'deleted_at'],
                'venda_fotos_lote_deleted_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('venda_pedido_fotos', function (Blueprint $table): void {
            $table->dropIndex('venda_fotos_lote_deleted_idx');
            $table->dropForeign(['venda_operacao_lote_id']);
            $table->dropColumn('venda_operacao_lote_id');
        });
    }
};
