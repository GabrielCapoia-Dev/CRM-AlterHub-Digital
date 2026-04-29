<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('produtos')
            ->where('status', 'em_registro')
            ->update(['status' => 'ativo']);
    }

    public function down(): void
    {
        // Nao desfazemos essa normalizacao para evitar sobrescrever status atuais.
    }
};
