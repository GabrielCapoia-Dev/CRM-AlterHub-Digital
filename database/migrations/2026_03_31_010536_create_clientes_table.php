<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('categorias_segmentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            $table->string('codigo_interno')->nullable()->index();

            $table->foreignId('id_status_cliente')
                ->nullable()
                ->constrained('status_clientes')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->foreignId('id_categoria_segmento')
                ->nullable()
                ->constrained('categorias_segmentos')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            // Empresa
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 18)->nullable()->unique();

            // Contato
            $table->string('nome_completo')->nullable();
            $table->string('cargo')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telefone')->nullable();

            // Endereço
            $table->string('cep', 10)->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();

            // Extra
            $table->text('observacao')->nullable();

            $table->timestamps();

            $table->index('razao_social');
            $table->index('nome_fantasia');
            $table->index('uf');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
        Schema::dropIfExists('categorias_segmentos');
        Schema::dropIfExists('status_clientes');
    }
};