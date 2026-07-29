<?php

use App\Enum\SeparacaoStatus;
use App\Enum\VendaStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->string('separacao_status', 30)->nullable()->index()->after('status');
            $table->text('separacao_observacao')->nullable()->after('observacao');
            $table->foreignId('separado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('separado_em')->nullable();
            $table->timestamp('retornado_romaneio_em')->nullable();
            $table->text('retorno_romaneio_descricao')->nullable();
        });

        Schema::table('romaneios', function (Blueprint $table): void {
            $table->foreignId('despachado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('despachado_em')->nullable()->index();
        });

        DB::table('romaneios')
            ->where('status', 'ativo')
            ->update(['status' => 'gerado']);

        DB::table('venda_operacao_pedidos')
            ->where('status', VendaStatus::Confirmada->value)
            ->whereNull('separacao_status')
            ->update(['separacao_status' => SeparacaoStatus::Aguardando->value]);

        $pedidosEmRomaneio = DB::table('romaneio_pedidos')
            ->whereNotNull('pedido_ativo_id')
            ->pluck('pedido_ativo_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($pedidosEmRomaneio !== []) {
            DB::table('venda_operacao_pedidos')
                ->whereIn('id', $pedidosEmRomaneio)
                ->update(['separacao_status' => SeparacaoStatus::EmRomaneio->value]);
        }

        DB::table('venda_operacao_pedidos')
            ->whereIn('status', [
                VendaStatus::ParcialmenteDespachada->value,
                VendaStatus::Despachada->value,
                VendaStatus::Concluida->value,
            ])
            ->update(['separacao_status' => SeparacaoStatus::Despachado->value]);
    }

    public function down(): void
    {
        DB::table('romaneios')
            ->where('status', 'gerado')
            ->update(['status' => 'ativo']);

        Schema::table('romaneios', function (Blueprint $table): void {
            $table->dropForeign(['despachado_por']);
            $table->dropColumn(['despachado_por', 'despachado_em']);
        });

        Schema::table('venda_operacao_pedidos', function (Blueprint $table): void {
            $table->dropForeign(['separado_por']);
            $table->dropColumn([
                'separacao_status',
                'separacao_observacao',
                'separado_por',
                'separado_em',
                'retornado_romaneio_em',
                'retorno_romaneio_descricao',
            ]);
        });
    }
};
