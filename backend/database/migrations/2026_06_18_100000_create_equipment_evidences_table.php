<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment')->cascadeOnDelete();
            $table->foreignId('uploaded_by_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('file_name');
            $table->string('storage_key');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('caption', 500)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'equipment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_evidences');
    }
};
