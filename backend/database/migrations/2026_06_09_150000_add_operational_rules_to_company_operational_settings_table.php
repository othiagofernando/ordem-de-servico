<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->string('service_order_reference_prefix', 20)->nullable()->after('brand_name');
            $table->boolean('block_delivery_with_outstanding_balance')->default(true)->after('default_quote_validity_days');
        });
    }

    public function down(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'service_order_reference_prefix',
                'block_delivery_with_outstanding_balance',
            ]);
        });
    }
};
