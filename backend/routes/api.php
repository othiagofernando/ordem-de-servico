<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeRequestController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessCodeVerifyController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkConsumeController;
use App\Http\Controllers\Api\V1\Auth\PasswordlessMagicLinkRequestController;
use App\Http\Controllers\Api\V1\Auth\SelectCompanyController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TenantContextController;
use App\Http\Controllers\Api\V1\TenantMembershipController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('api.v1.health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', LoginController::class)->name('api.v1.auth.login');
    Route::post('/select-company', SelectCompanyController::class)->name('api.v1.auth.select-company');
    Route::post('/passwordless/code/request', PasswordlessCodeRequestController::class)
        ->name('api.v1.auth.passwordless.code.request');
    Route::post('/passwordless/code/verify', PasswordlessCodeVerifyController::class)
        ->name('api.v1.auth.passwordless.code.verify');
    Route::post('/passwordless/magic-link/request', PasswordlessMagicLinkRequestController::class)
        ->name('api.v1.auth.passwordless.magic-link.request');
    Route::post('/passwordless/magic-link/consume', PasswordlessMagicLinkConsumeController::class)
        ->name('api.v1.auth.passwordless.magic-link.consume');
});

Route::middleware('auth.token')->group(function (): void {
    Route::get('/tenant/context', TenantContextController::class)->name('api.v1.tenant.context');
    Route::get('/tenant/memberships/{membership}', TenantMembershipController::class)
        ->name('api.v1.tenant.memberships.show');
});
