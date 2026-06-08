<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TenantMembershipController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::middleware('auth.token')->group(function (): void {
    Route::get('/tenant/context', TenantContextController::class)->name('api.v1.tenant.context');
    Route::get('/tenant/memberships/{membership}', TenantMembershipController::class)
        ->name('api.v1.tenant.memberships.show');
});
