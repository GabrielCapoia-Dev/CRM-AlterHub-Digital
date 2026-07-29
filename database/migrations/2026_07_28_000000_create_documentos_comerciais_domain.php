<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_documentos', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 40)->default('padrao')->unique();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('logo_disk')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 40)->nullable();
            $table->string('nome_comercial')->nullable();
            $table->text('texto_complementar')->nullable();
            $table->boolean('exibir_valores_romaneio')->default(true);
            $table->timestamps();
        });

        Schema::table('produtos', function (Blueprint $table): void {
            $table->decimal('peso_unitario_kg', 12, 4)
                ->nullable()
                ->after('estoque_reservado');
        });

        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->decimal('peso_unitario_kg_snapshot', 12, 4)
                ->nullable()
                ->after('unidade_snapshot');
        });

        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->unsignedInteger('quantidade_volumes')
                ->nullable()
                ->after('cliente_documento_snapshot');
            $table->string('cliente_telefone_snapshot')
                ->nullable()
                ->after('cliente_documento_snapshot');
            $table->string('cliente_email_snapshot')
                ->nullable()
                ->after('cliente_telefone_snapshot');
            $table->json('cliente_endereco_snapshot')
                ->nullable()
                ->after('cliente_email_snapshot');
            $table->json('entrega_endereco_snapshot')
                ->nullable()
                ->after('cliente_endereco_snapshot');
            $table->string('condicao_pagamento_snapshot')
                ->nullable()
                ->after('entrega_endereco_snapshot');
            $table->text('condicoes_comerciais')
                ->nullable()
                ->after('condicao_pagamento_snapshot');
            $table->index(['status', 'quantidade_volumes'], 'vop_status_volumes_idx');
        });

        Schema::create('venda_operacao_lotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_id')
                ->constrained('vendas_operacao')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('deleted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('numero_lote', 100);
            $table->decimal('quantidade', 14, 4);
            $table->unsignedSmallInteger('ano_fabricacao')->nullable();
            $table->date('data_fabricacao')->nullable();
            $table->date('data_validade')->nullable()->index();
            $table->text('observacao')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['venda_operacao_id', 'numero_lote'],
                'venda_item_lote_unique',
            );
        });

        Schema::create('venda_pedido_fotos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venda_operacao_pedido_id')
                ->constrained('venda_operacao_pedidos')
                ->restrictOnDelete();
            $table->foreignId('venda_operacao_id')
                ->nullable()
                ->constrained('vendas_operacao')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('disk', 80);
            $table->string('path');
            $table->string('nome_original');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('tamanho_bytes');
            $table->text('descricao')->nullable();
            $table->foreignId('removida_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('removida_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(
                ['venda_operacao_pedido_id', 'deleted_at'],
                'venda_fotos_pedido_deleted_idx',
            );
            $table->index('venda_operacao_id');
        });

        Schema::create('romaneios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('cancelado_por')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('codigo')->nullable()->unique();
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamp('gerado_em')->useCurrent()->index();
            $table->timestamp('cancelado_em')->nullable();
            $table->text('observacao')->nullable();
            $table->text('justificativa_cancelamento')->nullable();
            $table->json('empresa_snapshot')->nullable();
            $table->unsignedInteger('total_pedidos')->default(0);
            $table->unsignedInteger('total_clientes')->default(0);
            $table->unsignedInteger('total_itens')->default(0);
            $table->decimal('quantidade_total', 14, 4)->default(0);
            $table->unsignedInteger('quantidade_volumes_total')->default(0);
            $table->decimal('peso_total_kg', 14, 4)->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->boolean('exibir_valores_comerciais')->default(true);
            $table->timestamps();
        });

        Schema::create('romaneio_pedidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('romaneio_id')
                ->constrained('romaneios')
                ->restrictOnDelete();
            $table->foreignId('venda_operacao_pedido_id')
                ->constrained('venda_operacao_pedidos')
                ->restrictOnDelete();
            $table->foreignId('pedido_ativo_id')
                ->nullable()
                ->unique()
                ->constrained('venda_operacao_pedidos')
                ->restrictOnDelete();
            $table->unsignedBigInteger('cliente_id_snapshot')->nullable();
            $table->string('pedido_codigo_snapshot')->nullable();
            $table->date('pedido_data_snapshot')->nullable();
            $table->string('cliente_nome_snapshot')->nullable();
            $table->string('cliente_documento_snapshot')->nullable();
            $table->string('cliente_telefone_snapshot')->nullable();
            $table->string('cliente_email_snapshot')->nullable();
            $table->json('cliente_endereco_snapshot')->nullable();
            $table->json('entrega_endereco_snapshot')->nullable();
            $table->string('vendedor_nome_snapshot')->nullable();
            $table->string('condicao_pagamento_snapshot')->nullable();
            $table->text('condicoes_comerciais_snapshot')->nullable();
            $table->text('observacao_snapshot')->nullable();
            $table->unsignedInteger('total_itens')->default(0);
            $table->decimal('quantidade_total', 14, 4)->default(0);
            $table->unsignedInteger('quantidade_volumes');
            $table->decimal('peso_total_kg', 14, 4);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(
                ['romaneio_id', 'venda_operacao_pedido_id'],
                'romaneio_pedido_unique',
            );
            $table->index('cliente_id_snapshot');
        });

        Schema::create('romaneio_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('romaneio_id')
                ->constrained('romaneios')
                ->restrictOnDelete();
            $table->foreignId('romaneio_pedido_id')
                ->constrained('romaneio_pedidos')
                ->restrictOnDelete();
            $table->foreignId('venda_operacao_id')
                ->constrained('vendas_operacao')
                ->restrictOnDelete();
            $table->foreignId('produto_id')
                ->nullable()
                ->constrained('produtos')
                ->nullOnDelete();
            $table->string('produto_codigo_snapshot')->nullable();
            $table->string('produto_nome_snapshot');
            $table->string('unidade_snapshot')->nullable();
            $table->decimal('quantidade', 14, 4);
            $table->decimal('peso_unitario_kg', 12, 4);
            $table->decimal('peso_total_kg', 14, 4);
            $table->decimal('preco_unitario', 14, 4);
            $table->decimal('subtotal', 14, 2);
            $table->json('lotes_snapshot')->nullable();
            $table->text('observacao_snapshot')->nullable();
            $table->timestamps();
            $table->unique(
                ['romaneio_pedido_id', 'venda_operacao_id'],
                'romaneio_item_unique',
            );
        });

        Schema::create('romaneio_historicos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('romaneio_id')
                ->constrained('romaneios')
                ->restrictOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('evento', 50)->index();
            $table->string('status_anterior', 20)->nullable();
            $table->string('status_novo', 20)->nullable();
            $table->text('justificativa')->nullable();
            $table->json('metadados')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(
                ['romaneio_id', 'created_at'],
                'romaneio_history_date_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('romaneio_historicos');
        Schema::dropIfExists('romaneio_itens');
        Schema::dropIfExists('romaneio_pedidos');
        Schema::dropIfExists('romaneios');
        Schema::dropIfExists('venda_pedido_fotos');
        Schema::dropIfExists('venda_operacao_lotes');
        Schema::dropIfExists('configuracoes_documentos');

        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->dropIndex('vop_status_volumes_idx');
            $table->dropColumn([
                'quantidade_volumes',
                'cliente_telefone_snapshot',
                'cliente_email_snapshot',
                'cliente_endereco_snapshot',
                'entrega_endereco_snapshot',
                'condicao_pagamento_snapshot',
                'condicoes_comerciais',
            ]);
        });

        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->dropColumn('peso_unitario_kg_snapshot');
        });

        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropColumn('peso_unitario_kg');
        });
    }
};
