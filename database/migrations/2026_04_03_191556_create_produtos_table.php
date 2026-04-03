<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_interno')->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('unidade_medida')->nullable();
            $table->decimal('preco_tabela', 10, 2)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
