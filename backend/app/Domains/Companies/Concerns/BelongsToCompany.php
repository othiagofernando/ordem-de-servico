<?php

namespace App\Domains\Companies\Concerns;

use App\Domains\Companies\Models\Company;
use App\Domains\Companies\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('company_id') || currentCompany() === null) {
                return;
            }

            $model->setAttribute('company_id', currentCompany()->getKey());
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
