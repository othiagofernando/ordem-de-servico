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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action'); // 'created_role', 'deleted_permission', 'assigned_role', etc
            $table->string('resource_type'); // 'role', 'permission', 'user'
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->json('changes')->nullable(); // before/after values
            $table->enum('scope', ['global', 'tenant'])->default('global');
            $table->unsignedBigInteger('tenant_id')->nullable()->index(); // If scope=tenant, which tenant
            $table->timestamps();

            $table->index(['actor_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
