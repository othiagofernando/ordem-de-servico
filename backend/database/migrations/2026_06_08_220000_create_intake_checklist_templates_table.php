<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_checklist_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->unsignedInteger('version');
            $table->string('title');
            $table->json('items');
            $table->timestamps();

            $table->unique(['company_id', 'category', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_checklist_templates');
    }
};
