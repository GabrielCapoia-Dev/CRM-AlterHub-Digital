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

        Schema::create('categorias_fornecimento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('status_homologacao', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        Schema::create('prazos_pagamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('dias')->nullable();
            $table->timestamps();
        });

        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->timestamps();
        });

        /*
        |--------------------------------------------------------------------------
        | FORNECEDORES
        |--------------------------------------------------------------------------
        */

        Schema::create('fornecedores', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            // Identificação
            $table->uuid('uuid')->unique();
            $table->string('codigo_interno')->nullable()->index();

            // Relacionamentos
            $table->foreignId('id_categoria_fornecimento')
                ->constrained('categorias_fornecimento')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_status_homologacao')
                ->constrained('status_homologacao')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_prazo_pagamento')
                ->constrained('prazos_pagamento')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('id_forma_pagamento')
                ->constrained('formas_pagamento')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            // Empresa
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 18)->unique();
            $table->string('inscricao_estadual')->nullable();

            // Contato
            $table->string('nome_completo');
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
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Índices úteis
            $table->index(['razao_social']);
            $table->index(['nome_fantasia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fornecedores');
        Schema::dropIfExists('formas_pagamento');
        Schema::dropIfExists('prazos_pagamento');
        Schema::dropIfExists('status_homologacao');
        Schema::dropIfExists('categorias_fornecimento');
    }
};
