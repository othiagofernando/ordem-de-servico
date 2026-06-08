<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable()->after('technical_completed_at');
            $table->foreignId('delivered_by_membership_id')
                ->nullable()
                ->after('delivered_at')
                ->constrained('memberships')
                ->nullOnDelete();
            $table->string('received_by_name')->nullable()->after('delivered_by_membership_id');
            $table->text('delivery_notes')->nullable()->after('received_by_name');
            $table->timestamp('terminalized_at')->nullable()->after('delivery_notes');
            $table->foreignId('terminalized_by_membership_id')
                ->nullable()
                ->after('terminalized_at')
                ->constrained('memberships')
                ->nullOnDelete();
            $table->text('terminal_notes')->nullable()->after('terminalized_by_membership_id');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('delivered_by_membership_id');
            $table->dropConstrainedForeignId('terminalized_by_membership_id');
            $table->dropColumn([
                'delivered_at',
                'received_by_name',
                'delivery_notes',
                'terminalized_at',
                'terminal_notes',
            ]);
        });
    }
};
