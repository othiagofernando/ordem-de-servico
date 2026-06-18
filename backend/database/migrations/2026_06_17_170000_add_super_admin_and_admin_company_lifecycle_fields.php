<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('super_admin')->default(false)->after('password');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('is_active');
            $table->softDeletes()->after('updated_at');
        });

        DB::table('companies')->update([
            'status' => DB::raw("case when is_active = 1 then 'active' else 'suspended' end"),
        ]);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });

            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->change();
            });

            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            });

            return;
        }

        Schema::table('api_tokens', function (Blueprint $table): void {
            $table->dropForeign(['company_id']);
            $table->foreignId('company_id')->nullable()->change();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
            });

            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable(false)->change();
            });

            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        } else {
            Schema::table('api_tokens', function (Blueprint $table): void {
                $table->dropForeign(['company_id']);
                $table->foreignId('company_id')->nullable(false)->change();
                $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            });
        }

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn('status');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('super_admin');
        });
    }
};
