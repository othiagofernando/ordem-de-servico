<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('execution_started_at')->nullable()->after('diagnosis_completed_at');
            $table->foreignId('execution_started_by_membership_id')
                ->nullable()
                ->after('execution_started_at')
                ->constrained('memberships')
                ->nullOnDelete();
            $table->json('final_test_results')->nullable()->after('execution_started_by_membership_id');
            $table->timestamp('technical_completed_at')->nullable()->after('final_test_results');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('execution_started_by_membership_id');
            $table->dropColumn([
                'execution_started_at',
                'final_test_results',
                'technical_completed_at',
            ]);
        });
    }
};
