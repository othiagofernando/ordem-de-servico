<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->text('diagnosis_internal_notes')->nullable()->after('equipment_snapshot');
            $table->text('diagnosis_customer_summary')->nullable()->after('diagnosis_internal_notes');
            $table->timestamp('diagnosis_completed_at')->nullable()->after('diagnosis_customer_summary');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'diagnosis_internal_notes',
                'diagnosis_customer_summary',
                'diagnosis_completed_at',
            ]);
        });
    }
};
