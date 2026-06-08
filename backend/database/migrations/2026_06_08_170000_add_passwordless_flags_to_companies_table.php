<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('passwordless_code_enabled')->default(false)->after('is_active');
            $table->boolean('magic_link_enabled')->default(false)->after('passwordless_code_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['passwordless_code_enabled', 'magic_link_enabled']);
        });
    }
};
