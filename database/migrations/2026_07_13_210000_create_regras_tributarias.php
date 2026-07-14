<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regras_tributarias', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->char('uf_destino', 2);
            $table->string('ncm', 8)->nullable();
            $table->string('ncm_escopo', 8)
                ->storedAs("COALESCE(ncm, '')");
            $table->unsignedInteger('versao');
            $table->date('vigencia_inicio');
            $table->date('vigencia_fim')->nullable();
            $table->decimal('aliquota_icms', 7, 4)->default(0);
            $table->decimal('aliquota_icms_st', 7, 4)->default(0);
            $table->decimal('aliquota_ipi', 7, 4)->default(0);
            $table->decimal('aliquota_pis', 7, 4)->default(0);
            $table->decimal('aliquota_cofins', 7, 4)->default(0);
            $table->decimal('aliquota_fcp', 7, 4)->default(0);
            $table->decimal('reducao_base_calculo', 7, 4)->default(0);
            $table->boolean('ativo')->default(true)->index();
            $table->json('metadados')->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['uf_destino', 'ncm', 'vigencia_inicio'], 'regra_tributaria_destino_ncm_vigencia');
            $table->unique(
                ['uf_destino', 'ncm_escopo', 'versao'],
                'regra_tributaria_destino_ncm_versao',
            );
        });

        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->foreignId('regra_tributaria_id')
                ->nullable()
                ->after('custo_total_snapshot')
                ->constrained('regras_tributarias')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->unsignedInteger('regra_tributaria_versao_snapshot')
                ->nullable()
                ->after('regra_tributaria_id');
            $table->char('uf_destino_snapshot', 2)
                ->nullable()
                ->after('regra_tributaria_versao_snapshot');
            $table->string('ncm_snapshot', 8)
                ->nullable()
                ->after('uf_destino_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('vendas_operacao', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('regra_tributaria_id');
            $table->dropColumn([
                'regra_tributaria_versao_snapshot',
                'uf_destino_snapshot',
                'ncm_snapshot',
            ]);
        });

        Schema::dropIfExists('regras_tributarias');
    }
};
