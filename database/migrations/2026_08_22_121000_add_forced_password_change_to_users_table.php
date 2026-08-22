<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_reset_at')->nullable();
            $table->foreignId('password_reset_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('password_changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('password_reset_by');
            $table->dropColumn([
                'must_change_password',
                'password_reset_at',
                'password_changed_at',
            ]);
        });
    }
};
