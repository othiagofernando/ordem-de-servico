<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->after('customer_id')->constrained('customer_contacts')->nullOnDelete();
            $table->foreignId('equipment_id')->nullable()->after('customer_contact_id')->constrained()->nullOnDelete();
            $table->string('draft_reference')->nullable()->after('reference');
            $table->unsignedBigInteger('order_number')->nullable()->after('draft_reference');
            $table->text('equipment_description')->nullable()->after('status');
            $table->text('reported_problem')->nullable()->after('equipment_description');
            $table->json('intake_checklist')->nullable()->after('reported_problem');
            $table->timestamp('opened_at')->nullable()->after('intake_checklist');

            $table->unique(['company_id', 'draft_reference']);
            $table->unique(['company_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropUnique('service_orders_company_id_draft_reference_unique');
            $table->dropUnique('service_orders_company_id_order_number_unique');

            $table->dropConstrainedForeignId('equipment_id');
            $table->dropConstrainedForeignId('customer_contact_id');
            $table->dropConstrainedForeignId('customer_id');

            $table->dropColumn([
                'draft_reference',
                'order_number',
                'equipment_description',
                'reported_problem',
                'intake_checklist',
                'opened_at',
            ]);
        });
    }
};
