<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('warranty_origin_service_order_id')
                ->nullable()
                ->after('terminal_notes')
                ->constrained('service_orders')
                ->nullOnDelete();
            $table->string('warranty_signal_status')->nullable()->after('warranty_origin_service_order_id');
            $table->string('warranty_signal_reason')->nullable()->after('warranty_signal_status');
            $table->timestamp('warranty_signal_expires_at')->nullable()->after('warranty_signal_reason');
            $table->string('warranty_decision_status')->nullable()->after('warranty_signal_expires_at');
            $table->boolean('warranty_decision_override')->default(false)->after('warranty_decision_status');
            $table->text('warranty_decision_notes')->nullable()->after('warranty_decision_override');
            $table->timestamp('warranty_decided_at')->nullable()->after('warranty_decision_notes');
            $table->foreignId('warranty_decided_by_membership_id')
                ->nullable()
                ->after('warranty_decided_at')
                ->constrained('memberships')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warranty_origin_service_order_id');
            $table->dropConstrainedForeignId('warranty_decided_by_membership_id');
            $table->dropColumn([
                'warranty_signal_status',
                'warranty_signal_reason',
                'warranty_signal_expires_at',
                'warranty_decision_status',
                'warranty_decision_override',
                'warranty_decision_notes',
                'warranty_decided_at',
            ]);
        });
    }
};
