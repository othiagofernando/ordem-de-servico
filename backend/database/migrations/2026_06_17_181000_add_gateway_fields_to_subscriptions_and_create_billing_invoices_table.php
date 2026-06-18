<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->string('gateway_customer_id')->nullable()->after('status');
            $table->string('gateway_subscription_id')->nullable()->after('gateway_customer_id');
            $table->string('gateway_name')->nullable()->after('gateway_subscription_id');
        });

        Schema::create('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('gateway_invoice_id')->nullable()->unique();
            $table->string('status');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('invoice_url')->nullable();
            $table->string('pdf_url')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropColumn(['gateway_customer_id', 'gateway_subscription_id', 'gateway_name']);
        });
    }
};
