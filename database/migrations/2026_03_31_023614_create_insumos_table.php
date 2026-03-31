<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | TABELAS DE DOMÍNIO
        |--------------------------------------------------------------------------
        */

        Schema::create('tipos_insumo', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('tipos_armazenamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('tipos_unidade_medida', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('sigla', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('status_insumos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | INSUMOS
        |--------------------------------------------------------------------------
        */

        Schema::create('insumos', function (Blueprint $table) {
            $table->id();

            $table->string('codigo_interno')->nullable()->unique()->index();

            // Relacionamentos
            $table->foreignId('tipo_insumo_id')
                ->nullable()
                ->constrained('tipos_insumo')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('tipo_armazenamento_id')
                ->nullable()
                ->constrained('tipos_armazenamento')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('tipo_unidade_medida_id')
                ->nullable()
                ->constrained('tipos_unidade_medida')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('status_insumo_id')
                ->nullable()
                ->constrained('status_insumos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Identificação
            $table->string('nome');
            $table->text('descricao')->nullable();

            // Dados técnicos / comerciais
            $table->string('ncm', 10)->nullable()->index();
            $table->decimal('custo_referencia', 12, 4)->nullable();
            $table->decimal('estoque_minimo', 12, 4)->nullable();

            // Extra
            $table->text('observacao')->nullable();

            $table->timestamps();

            $table->index('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
        Schema::dropIfExists('status_insumos');
        Schema::dropIfExists('tipos_unidade_medida');
        Schema::dropIfExists('tipos_armazenamento');
        Schema::dropIfExists('tipos_insumo');
    }
};