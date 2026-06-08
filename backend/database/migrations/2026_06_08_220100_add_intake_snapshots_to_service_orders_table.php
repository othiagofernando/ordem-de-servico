<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('intake_checklist_template_id')
                ->nullable()
                ->after('equipment_id')
                ->constrained('intake_checklist_templates')
                ->nullOnDelete();
            $table->unsignedInteger('intake_checklist_template_version')
                ->nullable()
                ->after('intake_checklist');
            $table->json('intake_checklist_template_snapshot')
                ->nullable()
                ->after('intake_checklist_template_version');
            $table->json('equipment_snapshot')
                ->nullable()
                ->after('intake_checklist_template_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('intake_checklist_template_id');
            $table->dropColumn([
                'intake_checklist_template_version',
                'intake_checklist_template_snapshot',
                'equipment_snapshot',
            ]);
        });
    }
};
