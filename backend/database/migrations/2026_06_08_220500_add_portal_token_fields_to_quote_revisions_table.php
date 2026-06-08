<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_revisions', function (Blueprint $table): void {
            $table->string('portal_token_hash')->nullable()->after('submitted_at');
            $table->timestamp('portal_token_expires_at')->nullable()->after('portal_token_hash');
            $table->index('portal_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('quote_revisions', function (Blueprint $table): void {
            $table->dropIndex(['portal_token_hash']);
            $table->dropColumn([
                'portal_token_hash',
                'portal_token_expires_at',
            ]);
        });
    }
};
