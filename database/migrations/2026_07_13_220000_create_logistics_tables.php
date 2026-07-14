<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportadoras', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_interno')->nullable()->unique();
            $table->string('razao_social');
            $table->string('nome_fantasia')->nullable();
            $table->string('cnpj', 24)->nullable()->unique();
            $table->string('contato_nome')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telefone')->nullable();
            $table->unsignedSmallInteger('prazo_estimado_dias')->nullable();
            $table->decimal('valor_frete_custo_padrao', 14, 2)->nullable();
            $table->decimal('valor_frete_cobrado_padrao', 14, 2)->nullable();
            $table->json('modalidades_entrega')->nullable();
            $table->boolean('ativo')->default(true)->index();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index('razao_social');
        });

        Schema::create('cliente_transportadora', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('transportadora_id')
                ->constrained('transportadoras')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('codigo_cliente_transportadora')->nullable();
            $table->boolean('preferencial')->default(false);
            // Nullable mirror used to enforce one preferred carrier per client.
            // A generated column prevents MySQL from creating the FK on cliente_id.
            $table->unsignedBigInteger('preferencial_cliente_id')->nullable();
            $table->string('modalidade_entrega_padrao', 30)->nullable();
            $table->decimal('valor_frete_custo_padrao', 14, 2)->nullable();
            $table->decimal('valor_frete_cobrado_padrao', 14, 2)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'transportadora_id']);
            $table->unique(
                'preferencial_cliente_id',
                'cliente_transportadora_preferencial_unique',
            );
            $table->index(['cliente_id', 'preferencial']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_transportadora');
        Schema::dropIfExists('transportadoras');
    }
};
