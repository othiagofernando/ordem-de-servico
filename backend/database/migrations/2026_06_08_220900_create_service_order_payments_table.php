<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->integer('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('payment_method');
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->json('receipt_snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_payments');
    }
};
