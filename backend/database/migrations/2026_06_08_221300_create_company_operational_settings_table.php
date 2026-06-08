<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_operational_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name')->nullable();
            $table->text('portal_intro_text')->nullable();
            $table->unsignedTinyInteger('default_quote_validity_days')->default(7);
            $table->boolean('notify_quote_available')->default(true);
            $table->boolean('notify_quote_expiring')->default(true);
            $table->boolean('notify_ready_for_pickup')->default(true);
            $table->boolean('notify_payment_receipt')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_operational_settings');
    }
};
