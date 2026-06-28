<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_print_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->json('payload');
            $table->unsignedBigInteger('first_issued_by_membership_id')->nullable();
            $table->foreign('first_issued_by_membership_id', 'sops_first_issued_membership_fk')->references('id')->on('memberships')->nullOnDelete();
            $table->timestamp('first_issued_at')->nullable();
            $table->unsignedBigInteger('last_issued_by_membership_id')->nullable();
            $table->foreign('last_issued_by_membership_id', 'sops_last_issued_membership_fk')->references('id')->on('memberships')->nullOnDelete();
            $table->timestamp('last_issued_at')->nullable();
            $table->unsignedInteger('issue_count')->default(1);
            $table->timestamps();

            $table->unique('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_print_snapshots');
    }
};
