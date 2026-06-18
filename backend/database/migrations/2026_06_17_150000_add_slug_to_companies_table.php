<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('name');
        });

        DB::table('companies')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get()
            ->each(function (object $company): void {
                $base = Str::slug($company->name ?: 'empresa') ?: 'empresa';
                $slug = $base;
                $suffix = 2;

                while (DB::table('companies')
                    ->where('slug', $slug)
                    ->where('id', '!=', $company->id)
                    ->exists()) {
                    $slug = $base.'-'.$suffix;
                    $suffix++;
                }

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update(['slug' => $slug]);
            });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('slug')->nullable(false)->change();
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
