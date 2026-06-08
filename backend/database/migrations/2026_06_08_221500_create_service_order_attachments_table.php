<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            $table->foreignId('uploaded_by_membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->string('attachment_type');
            $table->string('file_name');
            $table->string('storage_key')->unique();
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size_bytes');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'service_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_attachments');
    }
};
