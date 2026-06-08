<?php

namespace App\Domains\Companies\Scopes;

use App\Domains\Companies\Support\CurrentCompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $company = app(CurrentCompanyContext::class)->company();

        if ($company === null) {
            return;
        }

        $builder->where($model->qualifyColumn('company_id'), $company->getKey());
    }
}
