<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('cnpj', 40)->change();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cnpj', 40)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->string('cnpj', 18)->change();
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->string('cnpj', 18)->nullable()->change();
        });
    }
};
