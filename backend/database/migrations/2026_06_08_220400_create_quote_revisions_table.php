<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status');
            $table->json('line_items');
            $table->string('currency', 3)->default('BRL');
            $table->unsignedBigInteger('total_cents');
            $table->text('notes')->nullable();
            $table->timestamp('valid_until');
            $table->timestamp('submitted_at');
            $table->timestamp('decisioned_at')->nullable();
            $table->string('decision_token_hash')->nullable();
            $table->string('decision_ip', 45)->nullable();
            $table->text('decision_user_agent')->nullable();
            $table->string('decision_idempotency_key')->nullable();
            $table->timestamps();

            $table->unique(['service_order_id', 'revision_number']);
            $table->index(['service_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_revisions');
    }
};
