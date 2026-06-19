<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('category', 100)->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('unit', 20)->default('un');
            $table->decimal('stock_quantity', 10, 3)->default(0);
            $table->decimal('min_stock_quantity', 10, 3)->default(0);
            $table->unsignedInteger('cost_price_cents')->default(0);
            $table->unsignedInteger('sale_price_cents')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_order_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->unsignedInteger('unit_cost_price_cents');
            $table->unsignedInteger('unit_sale_price_cents');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_parts');
        Schema::dropIfExists('parts');
    }
};
