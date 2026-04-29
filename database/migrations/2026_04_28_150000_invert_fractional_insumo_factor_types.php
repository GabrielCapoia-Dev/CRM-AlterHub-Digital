<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('insumo_fatores_custo')
            ->where('tipo', 'valor_fixo_brl')
            ->where('valor', '>', 0)
            ->where('valor', '<', 1)
            ->update([
                'tipo' => 'percentual',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('insumo_fatores_custo')
            ->where('tipo', 'percentual')
            ->where('valor', '>', 0)
            ->where('valor', '<', 1)
            ->update([
                'tipo' => 'valor_fixo_brl',
                'updated_at' => now(),
            ]);
    }
};
