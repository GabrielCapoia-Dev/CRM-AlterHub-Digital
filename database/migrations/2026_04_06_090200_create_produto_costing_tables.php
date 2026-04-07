<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_insumos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('insumo_id')
                ->constrained('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('quantidade', 12, 4);
            $table->string('unidade_consumo', 50)->nullable();
            $table->unsignedInteger('ordem')->default(0);
            $table->decimal('custo_unitario_snapshot', 12, 4)->default(0);
            $table->decimal('custo_total_snapshot', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['produto_id', 'ordem']);
        });

        Schema::create('produto_componentes_custo', function (Blueprint $table) {
            $table->id();

            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('nome');
            $table->string('categoria', 30);
            $table->string('tipo', 30);
            $table->decimal('valor', 12, 4)->default(0);
            $table->boolean('obrigatorio')->default(false);
            $table->boolean('is_margem')->default(false);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['produto_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_componentes_custo');
        Schema::dropIfExists('produto_insumos');
    }
};
