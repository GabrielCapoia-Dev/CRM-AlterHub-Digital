<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracoes_documentos', function (Blueprint $table): void {
            $table->boolean('smtp_enabled')->default(false)->after('exibir_valores_romaneio');
            $table->string('smtp_host')->nullable()->after('smtp_enabled');
            $table->unsignedSmallInteger('smtp_port')->nullable()->after('smtp_host');
            $table->string('smtp_encryption', 20)->nullable()->after('smtp_port');
            $table->string('smtp_username')->nullable()->after('smtp_encryption');
            $table->text('smtp_password')->nullable()->after('smtp_username');
            $table->string('mail_from_address')->nullable()->after('smtp_password');
            $table->string('mail_from_name')->nullable()->after('mail_from_address');
        });
    }

    public function down(): void
    {
        Schema::table('configuracoes_documentos', function (Blueprint $table): void {
            $table->dropColumn([
                'smtp_enabled',
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_username',
                'smtp_password',
                'mail_from_address',
                'mail_from_name',
            ]);
        });
    }
};
