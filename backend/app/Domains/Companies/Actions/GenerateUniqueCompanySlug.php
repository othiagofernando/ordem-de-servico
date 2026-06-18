<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Companies\Models\Company;
use Illuminate\Support\Str;

class GenerateUniqueCompanySlug
{
    public function handle(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'empresa';
        $slug = $base;
        $suffix = 2;

        while (Company::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
