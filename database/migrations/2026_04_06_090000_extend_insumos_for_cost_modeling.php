<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->foreignUuid('fornecedor_id')
                ->nullable()
                ->after('codigo_interno')
                ->constrained('fornecedores', 'uuid')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->string('origem', 20)
                ->default('nacional')
                ->after('status_insumo_id')
                ->index();

            $table->string('moeda_origem', 3)
                ->nullable()
                ->after('origem');

            $table->decimal('custo_moeda_origem', 12, 4)
                ->nullable()
                ->after('custo_referencia');

            $table->decimal('taxa_cambio', 12, 6)
                ->nullable()
                ->after('custo_moeda_origem');

            $table->decimal('valor_convertido_brl', 12, 4)
                ->nullable()
                ->after('taxa_cambio');

            $table->decimal('custo_nacionalizado', 12, 4)
                ->nullable()
                ->after('valor_convertido_brl');
        });

        Schema::create('insumo_fatores_custo', function (Blueprint $table) {
            $table->id();

            $table->foreignId('insumo_id')
                ->constrained('insumos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('nome');
            $table->string('tipo', 30);
            $table->decimal('valor', 12, 4)->default(0);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['insumo_id', 'ordem']);
        });

        DB::table('insumos')->update([
            'origem' => 'nacional',
            'valor_convertido_brl' => DB::raw('custo_referencia'),
            'custo_nacionalizado' => DB::raw('custo_referencia'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('insumo_fatores_custo');

        Schema::table('insumos', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropColumn([
                'fornecedor_id',
                'origem',
                'moeda_origem',
                'custo_moeda_origem',
                'taxa_cambio',
                'valor_convertido_brl',
                'custo_nacionalizado',
            ]);
        });
    }
};
