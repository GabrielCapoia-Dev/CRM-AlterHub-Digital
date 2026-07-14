<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->json('motivos_aprovacao')->nullable()->after('motivo_recusa');
        });
    }

    public function down(): void
    {
        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->dropColumn('motivos_aprovacao');
        });
    }
};
