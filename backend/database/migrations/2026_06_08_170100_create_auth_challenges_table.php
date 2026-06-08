<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_challenges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('token_hash')->unique();
            $table->string('code_hash')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_challenges');
    }
};
