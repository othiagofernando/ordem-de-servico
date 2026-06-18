<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('trade_name')->nullable()->after('name');
            $table->string('address_line_1')->nullable()->after('phone');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('district')->nullable()->after('address_line_2');
            $table->string('city')->nullable()->after('district');
            $table->string('state', 2)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('website')->nullable()->after('postal_code');
            $table->string('logo_path')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'trade_name',
                'address_line_1',
                'address_line_2',
                'district',
                'city',
                'state',
                'postal_code',
                'website',
                'logo_path',
            ]);
        });
    }
};
