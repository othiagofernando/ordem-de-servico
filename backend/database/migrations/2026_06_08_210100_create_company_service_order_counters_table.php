<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_service_order_counters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('next_draft_number')->default(1);
            $table->unsignedBigInteger('next_service_order_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_service_order_counters');
    }
};
