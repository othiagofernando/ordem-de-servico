<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_quote_revision_id')->nullable()->constrained('quote_revisions')->nullOnDelete();
            $table->foreignId('created_by_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('charge_type');
            $table->string('description');
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_charges');
    }
};
