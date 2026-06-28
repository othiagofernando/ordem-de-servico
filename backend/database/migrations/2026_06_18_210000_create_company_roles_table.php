<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->timestamps();

            $table->index('company_id');
        });

        Schema::table('memberships', function (Blueprint $table): void {
            $table->foreignId('company_role_id')
                ->nullable()
                ->after('role')
                ->constrained('company_roles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table): void {
            $table->dropForeignIdFor(\App\Domains\Auth\Models\CompanyRole::class, 'company_role_id');
            $table->dropColumn('company_role_id');
        });

        Schema::dropIfExists('company_roles');
    }
};
