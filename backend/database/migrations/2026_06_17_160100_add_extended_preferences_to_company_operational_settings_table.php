<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->text('document_footer_text')->nullable()->after('portal_rejected_text');
            $table->boolean('intake_checklists_enabled')->default(true)->after('document_footer_text');
            $table->string('currency', 3)->default('BRL')->after('intake_checklists_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('company_operational_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'document_footer_text',
                'intake_checklists_enabled',
                'currency',
            ]);
        });
    }
};
