<?php

namespace App\Providers;

use App\Domains\Companies\Support\CurrentCompanyContext;
use App\Support\Logging\StructuredLogContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentCompanyContext::class, CurrentCompanyContext::class);
        $this->app->singleton(StructuredLogContext::class, StructuredLogContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
