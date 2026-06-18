<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table) {
            $table->boolean('notify_service_order_opened')->default(false)->after('notify_payment_receipt_text');
            $table->string('notify_service_order_opened_text', 255)->nullable()->after('notify_service_order_opened');
            $table->boolean('notify_service_order_in_progress')->default(false)->after('notify_service_order_opened_text');
            $table->string('notify_service_order_in_progress_text', 255)->nullable()->after('notify_service_order_in_progress');
            $table->boolean('notify_service_order_completed')->default(true)->after('notify_service_order_in_progress_text');
            $table->string('notify_service_order_completed_text', 255)->nullable()->after('notify_service_order_completed');
            $table->boolean('notify_service_order_delivered')->default(true)->after('notify_service_order_completed_text');
            $table->string('notify_service_order_delivered_text', 255)->nullable()->after('notify_service_order_delivered');
            $table->boolean('notify_service_order_cancelled')->default(false)->after('notify_service_order_delivered_text');
            $table->string('notify_service_order_cancelled_text', 255)->nullable()->after('notify_service_order_cancelled');
            $table->boolean('notify_service_order_no_repair')->default(true)->after('notify_service_order_cancelled_text');
            $table->string('notify_service_order_no_repair_text', 255)->nullable()->after('notify_service_order_no_repair');
        });
    }

    public function down(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table) {
            $table->dropColumn([
                'notify_service_order_opened',
                'notify_service_order_opened_text',
                'notify_service_order_in_progress',
                'notify_service_order_in_progress_text',
                'notify_service_order_completed',
                'notify_service_order_completed_text',
                'notify_service_order_delivered',
                'notify_service_order_delivered_text',
                'notify_service_order_cancelled',
                'notify_service_order_cancelled_text',
                'notify_service_order_no_repair',
                'notify_service_order_no_repair_text',
            ]);
        });
    }
};
