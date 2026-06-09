<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->string('customer_contact_line', 160)->nullable()->after('brand_name');
            $table->text('portal_footer_text')->nullable()->after('portal_intro_text');
            $table->string('portal_approved_text', 255)->nullable()->after('portal_footer_text');
            $table->string('portal_rejected_text', 255)->nullable()->after('portal_approved_text');
            $table->text('notification_footer_text')->nullable()->after('notify_payment_receipt');
        });
    }

    public function down(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'customer_contact_line',
                'portal_footer_text',
                'portal_approved_text',
                'portal_rejected_text',
                'notification_footer_text',
            ]);
        });
    }
};
