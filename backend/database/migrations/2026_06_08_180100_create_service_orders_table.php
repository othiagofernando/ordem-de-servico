<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->string('title');
            $table->string('status')->default('open');
            $table->foreignId('assigned_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
