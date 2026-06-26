<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oportunidade_produtos', function (Blueprint $table) {
            $table->decimal('desconto_percentual', 5, 2)
                ->default(0)
                ->after('preco_negociado');

            $table->foreignId('desconto_aprovado_por')
                ->nullable()
                ->after('desconto_percentual')
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('desconto_aprovado_em')
                ->nullable()
                ->after('desconto_aprovado_por');
        });
    }

    public function down(): void
    {
        Schema::table('oportunidade_produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desconto_aprovado_por');
            $table->dropColumn([
                'desconto_percentual',
                'desconto_aprovado_em',
            ]);
        });
    }
};
