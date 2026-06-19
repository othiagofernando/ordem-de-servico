<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('scope', ['global', 'tenant'])->default('global')->after('guard_name');
            $table->boolean('is_super_admin')->default(false)->after('scope');
            $table->boolean('is_admin')->default(false)->after('is_super_admin');
            $table->boolean('is_active')->default(true)->after('is_admin');
            $table->softDeletes();

            // Create unique constraint for active roles
            $table->unique(['name', 'guard_name', 'scope', 'is_active'], 'roles_unique_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_unique_active');
            $table->dropSoftDeletes();
            $table->dropColumn(['scope', 'is_super_admin', 'is_admin', 'is_active']);
        });
    }
};
