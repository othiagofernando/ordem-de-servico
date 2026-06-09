<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->string('notify_quote_available_text', 255)->nullable()->after('notify_quote_available');
            $table->string('notify_quote_expiring_text', 255)->nullable()->after('notify_quote_expiring');
            $table->string('notify_ready_for_pickup_text', 255)->nullable()->after('notify_ready_for_pickup');
            $table->string('notify_payment_receipt_text', 255)->nullable()->after('notify_payment_receipt');
        });
    }

    public function down(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'notify_quote_available_text',
                'notify_quote_expiring_text',
                'notify_ready_for_pickup_text',
                'notify_payment_receipt_text',
            ]);
        });
    }
};
