<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_service_orders_per_month')->nullable();
            $table->unsignedInteger('max_attachments_storage_mb')->nullable();
            $table->unsignedBigInteger('reference_price_cents')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('plans')->insert([
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'max_users' => 2,
                'max_service_orders_per_month' => 100,
                'max_attachments_storage_mb' => 512,
                'reference_price_cents' => 9900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'profissional',
                'name' => 'Profissional',
                'max_users' => 10,
                'max_service_orders_per_month' => null,
                'max_attachments_storage_mb' => 4096,
                'reference_price_cents' => 29900,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'max_users' => null,
                'max_service_orders_per_month' => null,
                'max_attachments_storage_mb' => null,
                'reference_price_cents' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
