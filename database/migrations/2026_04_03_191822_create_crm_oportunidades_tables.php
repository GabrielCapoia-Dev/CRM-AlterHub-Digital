<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('slug')->unique();
            $table->integer('ordem');
            $table->string('cor')->nullable();
            $table->boolean('fechamento')->default(false);
            $table->timestamps();

            $table->index('ordem');
        });

        Schema::create('oportunidades', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete();
            $table->foreignId('etapa_id')
                ->constrained('etapas')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('temperatura', ['hot', 'warm', 'cool']);
            $table->decimal('valor_estimado', 10, 2)->nullable();
            $table->text('motivo_fechamento')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('titulo');
            $table->index('temperatura');
        });

        Schema::create('oportunidade_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidade_id')
                ->constrained('oportunidades')
                ->cascadeOnDelete();
            $table->foreignId('produto_id')
                ->constrained('produtos')
                ->cascadeOnDelete();
            $table->decimal('preco_negociado', 10, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('oportunidade_interacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidade_id')
                ->constrained('oportunidades')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->enum('tipo', ['ligacao', 'visita', 'email', 'observacao']);
            $table->text('nota');
            $table->dateTime('ocorreu_em');
            $table->timestamps();

            $table->index('ocorreu_em');
        });

        Schema::create('oportunidade_tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidade_id')
                ->constrained('oportunidades')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('titulo');
            $table->enum('status', ['pendente', 'em_andamento', 'concluida']);
            $table->date('data_prevista')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('data_prevista');
        });

        Schema::create('oportunidade_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oportunidade_id')
                ->constrained('oportunidades')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('etapa_origem_id');
            $table->unsignedBigInteger('etapa_destino_id');
            $table->text('motivo')->nullable();
            $table->dateTime('movido_em');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('etapa_origem_id')
                ->references('id')
                ->on('etapas')
                ->cascadeOnDelete();

            $table->foreign('etapa_destino_id')
                ->references('id')
                ->on('etapas')
                ->cascadeOnDelete();

            $table->index('movido_em');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oportunidade_movimentacoes');
        Schema::dropIfExists('oportunidade_tarefas');
        Schema::dropIfExists('oportunidade_interacoes');
        Schema::dropIfExists('oportunidade_produtos');
        Schema::dropIfExists('oportunidades');
        Schema::dropIfExists('etapas');
    }
};
