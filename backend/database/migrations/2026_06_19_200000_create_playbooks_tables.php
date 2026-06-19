<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playbooks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('description', 500)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('emoji', 10)->default('📖');
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('playbook_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playbook_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('title');
            $table->text('content');
            $table->string('tip', 500)->nullable();
            $table->enum('tip_type', ['info', 'warning', 'success'])->default('info');
            $table->timestamps();
            $table->index(['playbook_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playbook_steps');
        Schema::dropIfExists('playbooks');
    }
};
