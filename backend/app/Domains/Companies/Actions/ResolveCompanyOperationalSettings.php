<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Models\CompanyOperationalSetting;

class ResolveCompanyOperationalSettings
{
    public function handle(Company $company): CompanyOperationalSetting
    {
        $settings = CompanyOperationalSetting::query()
            ->withoutGlobalScopes()
            ->firstOrNew(['company_id' => $company->getKey()]);

        foreach (CompanyOperationalSetting::defaults() as $key => $value) {
            if ($settings->getAttribute($key) === null) {
                $settings->setAttribute($key, $value);
            }
        }

        return $settings;
    }
}
